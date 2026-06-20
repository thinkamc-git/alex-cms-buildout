// cms/_assets/tiptap-setup.js — Tiptap editor bootstrap for the Articles
// edit view. Loaded as an ES module from /cms/articles/edit?id=N.
//
// CDN strategy: esm.sh resolves ESM imports + peer deps in the browser, so
// we can ship a no-bundler setup. Versions are pinned — never @latest.
//
// Phase 6a Decisions:
//   toolbar order:  bold, italic, H2, H3, ul, ol, link, blockquote, code, m, image
//   muted-word:     <span class="m">…</span> custom inline mark
//   inline images:  multipart POST to /cms/articles/upload-image, X-CSRF-Token
//   allowlist:      matches the toolbar EXACTLY (enforced server-side in
//                   lib/sanitize.php — the client only renders what Tiptap
//                   knows about, so client + server agree by construction)
//
// HTML Embed (added later, Articles toolbar only): pastes raw markup
// (e.g. an exported SVG) into a `div.html-embed` atom node. This is the
// one deliberate exception to the allowlist — lib/sanitize.php passes its
// contents through untouched. See the HtmlEmbed node + sanitize.php for
// the reasoning.
//
// Heading levels are restricted to [2, 3] because the article template
// reserves <h1> for the title block — body headings must be H2 or H3.

import { Editor, Mark, Node, mergeAttributes }
  from 'https://esm.sh/@tiptap/core@2.6.6';
import StarterKit
  from 'https://esm.sh/@tiptap/starter-kit@2.6.6';
import Link
  from 'https://esm.sh/@tiptap/extension-link@2.6.6';
import Image
  from 'https://esm.sh/@tiptap/extension-image@2.6.6';

/**
 * At most one Figure is ever "in edit mode" at a time — see the Figure
 * NodeView below. Tracking the DOM (not a position) survives positions
 * shifting as the document changes elsewhere.
 */
let editingFigureDom = null;

/**
 * Set right before inserting a freshly-uploaded image (see
 * pickAndUploadImage) so the Figure NodeView that mounts for it can tell
 * "I'm brand new" apart from "I'm existing content being parsed on page
 * load" — the two cases share the same addNodeView() factory, and only
 * the former should auto-open edit mode. Matched by src and consumed
 * (reset to null) on the first mount that claims it.
 */
let pendingFigureAutoEdit = null;

/**
 * Mirrors the dataSize/dataRounded/dataBorder attribute → DOM-attribute
 * mapping that the attribute-level renderHTML functions below express for
 * the toDOM/getHTML() serialization path. Figure's NodeView (further down)
 * builds its DOM by hand, so it needs the same mapping applied manually —
 * kept as one shared function so the two paths can't drift apart.
 */
function figureApplyAttrs(dom, attrs) {
  if (attrs.dataSize && attrs.dataSize !== 'default') {
    dom.setAttribute('data-size', attrs.dataSize);
  } else {
    dom.removeAttribute('data-size');
  }
  if (attrs.dataRounded === false) {
    dom.setAttribute('data-rounded', '0');
  } else {
    dom.removeAttribute('data-rounded');
  }
  if (attrs.dataBorder === false) {
    dom.setAttribute('data-border', '0');
  } else {
    dom.removeAttribute('data-border');
  }
}

/**
 * Custom Figure block node — renders `<figure data-size><img><figcaption>`.
 *
 * The image src/alt live on the node attributes (single source of truth);
 * the figcaption is rendered as inline-editable content. `data-size` is one
 * of `default | wide | full`; matches the figure-size rules in
 * site/_templates/style-articles.css. Inserted via the `setFigure({src})`
 * command added below. Existing bare `<img>` content still parses through
 * the standalone Image extension — both schemas coexist.
 */
const Figure = Node.create({
  name: 'figure',
  group: 'block',
  content: 'inline*',           // the inline content IS the figcaption text
  draggable: true,
  selectable: true,
  isolating: true,              // protect from join/lift collapsing the figure into surrounding blocks

  addAttributes() {
    return {
      src:  { default: null },
      alt:  { default: '' },
      dataSize: {
        default: 'default',
        parseHTML: el => el.getAttribute('data-size') || 'default',
        renderHTML: attrs => {
          if (!attrs.dataSize || attrs.dataSize === 'default') return {};
          return { 'data-size': attrs.dataSize };
        },
      },
      // Rounded/Border toggles. Default true for both — matches every
      // figure that existed before this feature shipped, so no migration.
      // Stored only when OFF (data-rounded="0" / data-border="0"), same
      // lean-output convention as dataSize's "default" being implicit.
      dataRounded: {
        default: true,
        parseHTML: el => el.getAttribute('data-rounded') !== '0',
        renderHTML: attrs => (attrs.dataRounded === false ? { 'data-rounded': '0' } : {}),
      },
      dataBorder: {
        default: true,
        parseHTML: el => el.getAttribute('data-border') !== '0',
        renderHTML: attrs => (attrs.dataBorder === false ? { 'data-border': '0' } : {}),
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'figure',
      contentElement: 'figcaption',  // extract caption text into node content
      getAttrs: el => {
        const img = el.querySelector('img');
        if (!img) return false;       // skip non-image figures (defensive)
        return {
          src: img.getAttribute('src'),
          alt: img.getAttribute('alt') || '',
          dataSize: el.getAttribute('data-size') || 'default',
          dataRounded: el.getAttribute('data-rounded') !== '0',
          dataBorder: el.getAttribute('data-border') !== '0',
        };
      },
    }];
  },

  renderHTML({ node, HTMLAttributes }) {
    return [
      'figure',
      HTMLAttributes,
      ['img', { src: node.attrs.src, alt: node.attrs.alt }],
      ['figcaption', 0],         // 0 = where inline (caption) content goes
    ];
  },

  /**
   * Custom NodeView, editing-view only (renderHTML above still drives
   * getHTML()/serialization — NodeViews don't touch that path).
   *
   * Why a NodeView at all: Figure isn't an atom (the figcaption is real
   * editable content), so clicking the rendered <img> — which sits
   * outside the figcaption's contentDOM — depends on ProseMirror's
   * default click-to-position resolution to land a NodeSelection. That
   * resolution isn't guaranteed to fire a selection-change on the very
   * first click (e.g. while the editor doesn't yet have focus), which
   * read as "have to click the image a few times before anything shows."
   * getPos() gives a position ProseMirror keeps live-updated for this
   * exact node instance, so a button calling setNodeSelection(getPos())
   * is deterministic regardless of focus state or which pixel was
   * clicked.
   *
   * Edit mode: the caption is only editable, and the size/Rounded/Border
   * panels (see updateFigurePanel) only appear, while a figure is
   * explicitly in edit mode — entered via the Edit button, exited via
   * Save (keep changes), Cancel (revert), or clicking elsewhere (treated
   * as Save). `editingFigureDom` (module-scoped, declared below) tracks
   * which single figure — at most one at a time — is currently editing,
   * so updateFigurePanel can gate panel visibility on it and so entering
   * edit mode on one figure auto-commits whichever other one was open.
   */
  addNodeView() {
    return ({ node, getPos, editor }) => {
      const dom = document.createElement('figure');
      figureApplyAttrs(dom, node.attrs);

      const img = document.createElement('img');
      img.src = node.attrs.src || '';
      img.alt = node.attrs.alt || '';
      dom.appendChild(img);

      // Soft gradient band behind the top-row controls (Edit/Save/Cancel,
      // and the Rounded/Border toggles once those move on-image too) —
      // gives the white pills contrast against arbitrary image content
      // without needing a hard-edged backdrop. Same hover/is-editing
      // reveal as the controls themselves; never intercepts clicks.
      const scrim = document.createElement('div');
      scrim.className = 'tt-fig-top-scrim';
      scrim.setAttribute('aria-hidden', 'true');
      dom.appendChild(scrim);

      const controls = document.createElement('div');
      controls.className = 'tt-fig-edit-controls';

      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'tt-fig-btn tt-fig-edit-btn';
      editBtn.setAttribute('aria-label', 'Edit image');
      editBtn.textContent = 'Edit';

      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'tt-fig-btn tt-fig-edit-cancel';
      cancelBtn.textContent = 'Cancel';
      cancelBtn.hidden = true;

      controls.append(editBtn, cancelBtn);
      dom.appendChild(controls);

      const figcaption = document.createElement('figcaption');
      figcaption.contentEditable = 'false';
      // Drives the collapse/placeholder CSS off the node's actual text
      // content rather than the DOM :empty pseudo-class — ProseMirror
      // injects its own placeholder elements (a trailing <br>, sometimes
      // a separator <img>) into an empty contentDOM so the cursor still
      // has somewhere to sit, which means figcaption is never truly
      // :empty in the live view even with zero authored caption text.
      figcaption.classList.toggle('is-empty', node.textContent === '');
      dom.appendChild(figcaption);
      // Clicking the caption itself is also an entry point into edit mode
      // (not just the Edit button) — contentEditable is false outside edit
      // mode, so a plain click wouldn't otherwise place a caret or do
      // anything. mousedown (not click) so we can preventDefault and flip
      // contentEditable on before the browser tries to resolve a caret
      // position against the old (non-editable) state.
      figcaption.addEventListener('mousedown', (ev) => {
        if (dom.classList.contains('is-editing')) return; // already editable — let it place the caret normally
        ev.preventDefault();
        enterEdit();
        figcaption.focus();
      });

      // Snapshot of {attrs, content} taken when Edit is clicked — content
      // is a ProseMirror Fragment, immutable, so holding a reference to
      // it is a safe, cheap snapshot (no deep-cloning needed).
      let snapshot = null;

      // updateFigurePanel(editor, mount) shows/positions the size and
      // Rounded/Border panels based purely on editingFigureDom — called
      // directly here (not left as a side effect of dispatching a
      // transaction) so the panel state is synced deterministically,
      // synchronously, every time editingFigureDom changes.
      function syncPanels() {
        updateFigurePanel(editor, editor.view.dom.parentElement);
      }

      function enterEdit() {
        if (editingFigureDom && editingFigureDom !== dom) {
          editingFigureDom.__ttExitEdit?.(true); // commit whichever other figure was open
        }
        const pos = getPos();
        const current = editor.state.doc.nodeAt(pos);
        if (!current) return;
        snapshot = { attrs: { ...current.attrs }, content: current.content };
        figcaption.contentEditable = 'true';
        editBtn.textContent = 'Save';
        cancelBtn.hidden = false;
        dom.classList.add('is-editing');
        editingFigureDom = dom;
        editor.chain().setNodeSelection(pos).run();
        syncPanels();
      }

      function exitEdit(commit) {
        if (!commit && snapshot) {
          const pos = getPos();
          const current = editor.state.doc.nodeAt(pos);
          if (current) {
            const restored = current.type.create(snapshot.attrs, snapshot.content, current.marks);
            editor.view.dispatch(editor.state.tr.replaceWith(pos, pos + current.nodeSize, restored));
          }
        }
        snapshot = null;
        figcaption.contentEditable = 'false';
        editBtn.textContent = 'Edit';
        cancelBtn.hidden = true;
        dom.classList.remove('is-editing');
        if (editingFigureDom === dom) editingFigureDom = null;
        syncPanels();
      }

      // Exposed so the document-level "clicked away" listener (see
      // setupTiptap) can commit this figure from outside without needing
      // its own copy of this state.
      dom.__ttExitEdit = exitEdit;

      editBtn.addEventListener('mousedown', (ev) => ev.preventDefault());
      editBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (dom.classList.contains('is-editing')) {
          exitEdit(true);
        } else {
          enterEdit();
        }
      });
      cancelBtn.addEventListener('mousedown', (ev) => ev.preventDefault());
      cancelBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        exitEdit(false);
      });

      // A freshly-uploaded image opens straight into edit mode — skips
      // the extra "now click Edit" step to set its size/caption/style
      // right after inserting it. Deferred a tick so this NodeView
      // finishes mounting (and the insert transaction's view update
      // finishes) before enterEdit() dispatches its own transaction.
      if (pendingFigureAutoEdit && pendingFigureAutoEdit === node.attrs.src) {
        pendingFigureAutoEdit = null;
        setTimeout(() => enterEdit(), 0);
      }

      return {
        dom,
        contentDOM: figcaption,
        update(updatedNode) {
          if (updatedNode.type.name !== 'figure') return false;
          img.src = updatedNode.attrs.src || '';
          img.alt = updatedNode.attrs.alt || '';
          figureApplyAttrs(dom, updatedNode.attrs);
          figcaption.classList.toggle('is-empty', updatedNode.textContent === '');
          return true;
        },
        destroy() {
          if (editingFigureDom === dom) editingFigureDom = null;
        },
        // Figure defines a contentDOM (figcaption), so ProseMirror's
        // default ignoreMutation treats ANY DOM mutation anywhere in
        // `dom` as a possible content change and reconciles it — which
        // for our own Edit/Save/Cancel chrome (class toggles, hidden
        // flips, contentEditable flips) meant PM destroyed and rebuilt
        // this whole NodeView mid-click, snapping "Save" back to "Edit".
        // Real caption edits land inside figcaption as childList/
        // characterData changes; everything else is UI chrome to ignore.
        ignoreMutation(mutation) {
          if (mutation.type === 'attributes') return true;
          return !figcaption.contains(mutation.target);
        },
      };
    };
  },

  addCommands() {
    return {
      setFigure: (attrs) => ({ commands }) =>
        commands.insertContent({
          type: this.name,
          attrs,
          content: [],            // empty caption — placeholder hint via CSS
        }),
      setFigureSize: (size) => ({ commands }) =>
        commands.updateAttributes(this.name, { dataSize: size }),
      toggleFigureRounded: () => ({ editor, commands }) =>
        commands.updateAttributes(this.name, { dataRounded: !editor.getAttributes(this.name).dataRounded }),
      toggleFigureBorder: () => ({ editor, commands }) =>
        commands.updateAttributes(this.name, { dataBorder: !editor.getAttributes(this.name).dataBorder }),
    };
  },
});

/**
 * HTML Embed block — `<div class="html-embed" data-size>RAW HTML</div>`.
 *
 * For manually-authored markup the toolbar can't otherwise produce (an
 * exported SVG icon, a hand-written diagram). Stored as an atom node so
 * Tiptap never tries to make its contents editable text.
 *
 * renderHTML returns a real DOM element (not the array mini-DSL) with
 * `innerHTML` pre-set to the raw markup — ProseMirror's DOMOutputSpec
 * supports a Node return and inserts it as-is, which is the only way to
 * get parsed (not text-escaped) markup into the serialized output. A
 * fresh element is created on every call, so the "one node, one parent"
 * caveat of that escape hatch doesn't apply here.
 *
 * Trusted-content note: unlike the rest of the Tiptap allowlist, this
 * block's markup passes through lib/sanitize.php untouched by design —
 * see the matching comment there. Single-author site; the author is the
 * only source of this content.
 */
const HtmlEmbed = Node.create({
  name: 'htmlEmbed',
  group: 'block',
  atom: true,
  draggable: true,
  selectable: true,
  isolating: true,

  addAttributes() {
    return {
      html: { default: '' },
      dataSize: {
        default: 'default',
        parseHTML: el => el.getAttribute('data-size') || 'default',
        renderHTML: attrs => {
          if (!attrs.dataSize || attrs.dataSize === 'default') return {};
          return { 'data-size': attrs.dataSize };
        },
      },
    };
  },

  parseHTML() {
    return [{
      tag: 'div.html-embed',
      getAttrs: el => ({
        html: el.innerHTML,
        dataSize: el.getAttribute('data-size') || 'default',
      }),
    }];
  },

  renderHTML({ node, HTMLAttributes }) {
    const dom = document.createElement('div');
    const attrs = mergeAttributes(HTMLAttributes, { class: 'html-embed' });
    for (const [name, value] of Object.entries(attrs)) {
      if (value !== null && value !== undefined) dom.setAttribute(name, String(value));
    }
    dom.innerHTML = node.attrs.html || '';
    return dom;
  },

  addNodeView() {
    return ({ node }) => {
      const dom = document.createElement('div');
      dom.className = 'html-embed';
      if (node.attrs.dataSize && node.attrs.dataSize !== 'default') {
        dom.setAttribute('data-size', node.attrs.dataSize);
      }
      dom.innerHTML = node.attrs.html || '';
      return { dom };
    };
  },

  addCommands() {
    return {
      setHtmlEmbed: (attrs) => ({ commands }) =>
        commands.insertContent({ type: this.name, attrs }),
      setHtmlEmbedSize: (size) => ({ commands }) =>
        commands.updateAttributes(this.name, { dataSize: size }),
    };
  },
});

/**
 * Custom inline mark for muted-word: <span class="m">…</span>.
 *
 * Tiptap stores this as a mark (not a node) so it composes with bold/italic
 * the way the design system expects ("a muted *word* in bold").
 */
const Muted = Mark.create({
  name: 'muted',
  parseHTML() {
    return [{ tag: 'span.m' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes, { class: 'm' }), 0];
  },
  addCommands() {
    return {
      toggleMuted: () => ({ commands }) => commands.toggleMark(this.name),
    };
  },
});

/**
 * Entry point. The edit view calls setupTiptap({...}) once, after DOM ready.
 *
 * params.mount      — div the editor renders into
 * params.fallback   — hidden <textarea name="body"> that the form posts;
 *                     we keep its value in sync with the editor on every update
 * params.toolbar    — container holding the <button data-cmd="..."> elements
 * params.uploadUrl  — POST endpoint for inline image uploads
 * params.csrfToken  — string, sent as X-CSRF-Token header on uploads
 */
export function setupTiptap({ mount, fallback, toolbar, uploadUrl, csrfToken }) {
  if (!mount || !fallback || !toolbar) {
    console.warn('[tiptap-setup] missing required mount/fallback/toolbar');
    return null;
  }

  const editor = new Editor({
    element: mount,
    // Phase 21.x: stamp the contenteditable with `article-prose` so the
    // public template's stylesheet (style-articles.css) styles the editor
    // body too — same CSS, same rendering, no drift.
    editorProps: {
      attributes: { class: 'article-prose' },
    },
    extensions: [
      StarterKit.configure({
        heading:    { levels: [2, 3] },
        codeBlock:  false,           // toolbar offers inline code only
        horizontalRule: false,       // not in the allowlist
      }),
      Link.configure({
        openOnClick: false,
        autolink: false,
        protocols: ['http', 'https', 'mailto'],
        HTMLAttributes: { rel: null, target: null },  // bare <a href>
      }),
      // Image extension stays for backward compat — older articles with
      // bare <img> tags still parse correctly. New images get inserted
      // through Figure (see pickAndUploadImage below).
      Image.configure({ inline: false }),
      Figure,
      HtmlEmbed,
      Muted,
    ],
    content: fallback.value,
    onUpdate({ editor }) {
      fallback.value = editor.getHTML();
      // Mirror a synthetic input event so listeners like preview-tab-guard
      // (which tracks dirtiness via input/change) detect TipTap edits.
      fallback.dispatchEvent(new Event('input', { bubbles: true }));
      updateToolbarState(editor, toolbar);
      updateFigurePanel(editor, mount);
    },
    onSelectionUpdate({ editor }) {
      updateToolbarState(editor, toolbar);
      updateFigurePanel(editor, mount);
    },
  });

  // Initial value already lives in the textarea; mirror toolbar state once.
  fallback.value = editor.getHTML();
  updateToolbarState(editor, toolbar);

  // Wire toolbar buttons.
  toolbar.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-cmd]');
    if (!btn) return;
    ev.preventDefault();
    runCommand(btn.dataset.cmd, editor, { uploadUrl, csrfToken });
  });

  // "Clicked away" commits whichever figure is currently in edit mode —
  // deliberately a plain DOM check, not anything derived from
  // editor.state.selection (see the long comment above updateFigurePanel
  // for why that path was unreliable). The size/toggle panels themselves
  // are exempt — clicking their buttons is a legitimate in-edit-mode
  // action, not "away".
  document.addEventListener('mousedown', (ev) => {
    if (!editingFigureDom || editingFigureDom.contains(ev.target)) return;
    if (ev.target.closest?.('.tt-fig-panel')) return;
    editingFigureDom.__ttExitEdit?.(true);
  });

  // The size/toggle panels are positioned via absolute px math computed
  // from the figure's/host's rendered rects (see updateFigurePanel) —
  // that math goes stale the moment the layout reflows for any other
  // reason (window resize, a side panel opening/closing), leaving the
  // panel floating wherever it was instead of tracking the image.
  window.addEventListener('resize', () => updateFigurePanel(editor, mount));

  return editor;
}

function runCommand(cmd, editor, ctx) {
  const chain = editor.chain().focus();
  switch (cmd) {
    case 'bold':       chain.toggleBold().run(); break;
    case 'italic':     chain.toggleItalic().run(); break;
    case 'h2':         chain.toggleHeading({ level: 2 }).run(); break;
    case 'h3':         chain.toggleHeading({ level: 3 }).run(); break;
    case 'ul':         chain.toggleBulletList().run(); break;
    case 'ol':         chain.toggleOrderedList().run(); break;
    case 'blockquote': chain.toggleBlockquote().run(); break;
    case 'code':       chain.toggleCode().run(); break;
    case 'muted':      chain.toggleMuted().run(); break;
    case 'link':       promptForLink(editor); break;
    case 'image':      pickAndUploadImage(editor, ctx); break;
    case 'html-embed': promptForHtmlEmbed(editor); break;
    default:
      console.warn('[tiptap-setup] unknown command:', cmd);
  }
}

function promptForLink(editor) {
  const previous = editor.getAttributes('link').href || '';
  const url = window.prompt('Link URL (empty to remove):', previous);
  if (url === null) return; // cancel
  if (url === '') {
    editor.chain().focus().extendMarkRange('link').unsetLink().run();
    return;
  }
  editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function pickAndUploadImage(editor, { uploadUrl, csrfToken }) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml';
  input.addEventListener('change', async () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const overlay = showUploadOverlay(editor, file.name);
    try {
      const url = await uploadImageWithProgress(file, { uploadUrl, csrfToken }, (pct) => {
        overlay.setProgress(pct);
      });
      overlay.setStatus('Done', 'ok');
      overlay.remove();
      // Insert as a Figure (figure + img + empty figcaption) so the author
      // gets a caption slot + size toggle. Older bare <img> content still
      // renders via the Image extension.
      pendingFigureAutoEdit = url;
      editor.chain().focus().setFigure({ src: url, alt: '' }).run();
    } catch (e) {
      overlay.setStatus(
        'Upload failed: ' + (e && e.message ? e.message : 'unknown error'),
        'err'
      );
      // Leave the overlay up for a couple seconds so the user can read it.
      setTimeout(() => overlay.remove(), 3500);
    }
  });
  input.click();
}

/**
 * Insert an HTML Embed block. Opens a textarea modal for pasting raw
 * markup (e.g. an exported SVG), then inserts it as a new htmlEmbed node —
 * same insertion gesture as pickAndUploadImage, minus the upload step.
 */
function promptForHtmlEmbed(editor) {
  showHtmlEmbedModal((html) => {
    const trimmed = (html || '').trim();
    if (trimmed === '') return;
    editor.chain().focus().setHtmlEmbed({ html: trimmed }).run();
  });
}

/**
 * Minimal textarea modal for pasting HTML/SVG markup. Inline-styled like
 * showUploadOverlay below — this file ships standalone and shouldn't
 * depend on style-cms.css being loaded.
 */
function showHtmlEmbedModal(onInsert) {
  const overlay = document.createElement('div');
  overlay.style.cssText =
    'position:fixed;inset:0;background:rgba(0,0,0,0.4);' +
    'display:flex;align-items:center;justify-content:center;z-index:1000';

  const box = document.createElement('div');
  box.style.cssText =
    'background:#fff;border-radius:8px;padding:20px;width:min(640px,90vw);' +
    'box-shadow:0 12px 32px rgba(0,0,0,0.18);display:flex;flex-direction:column;' +
    'gap:12px;font-family:inherit';

  const title = document.createElement('div');
  title.textContent = 'Insert HTML / SVG';
  title.style.cssText = 'font-weight:600;font-size:14px;color:#222';

  const textarea = document.createElement('textarea');
  textarea.placeholder = '<svg ...>...</svg>';
  textarea.spellcheck = false;
  textarea.style.cssText =
    'width:100%;min-height:220px;resize:vertical;box-sizing:border-box;' +
    'font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.5;' +
    'padding:10px;border:1px solid #ddd;border-radius:6px;color:#222';

  const row = document.createElement('div');
  row.style.cssText = 'display:flex;justify-content:flex-end;gap:8px';

  const cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.textContent = 'Cancel';
  cancelBtn.style.cssText =
    'padding:6px 14px;border:1px solid #ddd;border-radius:6px;background:#fff;' +
    'cursor:pointer;font-size:13px;color:#333';

  const insertBtn = document.createElement('button');
  insertBtn.type = 'button';
  insertBtn.textContent = 'Insert';
  insertBtn.style.cssText =
    'padding:6px 14px;border:none;border-radius:6px;background:#2563eb;color:#fff;' +
    'cursor:pointer;font-size:13px';

  row.append(cancelBtn, insertBtn);
  box.append(title, textarea, row);
  overlay.appendChild(box);
  document.body.appendChild(overlay);
  textarea.focus();

  function close() {
    overlay.remove();
    document.removeEventListener('keydown', onKeydown);
  }
  function onKeydown(ev) {
    if (ev.key === 'Escape') close();
  }
  document.addEventListener('keydown', onKeydown);

  overlay.addEventListener('mousedown', (ev) => {
    if (ev.target === overlay) close();
  });
  cancelBtn.addEventListener('click', close);
  insertBtn.addEventListener('click', () => {
    const value = textarea.value;
    close();
    onInsert(value);
  });
}

/**
 * Phase 13: XHR-based upload so we get progress events. fetch() doesn't
 * expose upload progress in any browser yet — XMLHttpRequest does, even
 * though the rest of the codebase prefers fetch().
 *
 *   uploadUrl   POST endpoint
 *   csrfToken   sent as X-CSRF-Token header
 *   onProgress  called with percent (0..100) during the upload phase
 */
function uploadImageWithProgress(file, { uploadUrl, csrfToken }, onProgress) {
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    fd.append('image', file);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', uploadUrl);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.withCredentials = true;
    xhr.responseType = 'json';
    if (xhr.upload) {
      xhr.upload.addEventListener('progress', (ev) => {
        if (ev.lengthComputable) {
          onProgress(Math.round((ev.loaded / ev.total) * 100));
        }
      });
    }
    xhr.addEventListener('load', () => {
      const json = xhr.response || {};
      if (xhr.status >= 200 && xhr.status < 300 && json.ok && json.url) {
        onProgress(100);
        resolve(json.url);
      } else {
        reject(new Error(json.error || ('HTTP ' + xhr.status)));
      }
    });
    xhr.addEventListener('error', () => reject(new Error('Network error')));
    xhr.addEventListener('abort', () => reject(new Error('Upload cancelled')));
    xhr.send(fd);
  });
}

/**
 * Pop an inline progress overlay anchored to the editor's mount node.
 * Returns a small handle with `setProgress(pct)`, `setStatus(text, kind)`,
 * and `remove()`. The overlay paints itself with inline styles so this
 * works without any CSS additions in style-cms.css.
 */
function showUploadOverlay(editor, filename) {
  const host = editor.view && editor.view.dom && editor.view.dom.parentElement;
  if (!host) {
    return { setProgress() {}, setStatus() {}, remove() {} };
  }
  // Make sure the host is a positioning context.
  const cs = window.getComputedStyle(host);
  if (cs.position === 'static') host.style.position = 'relative';

  const wrap = document.createElement('div');
  wrap.setAttribute('role', 'status');
  wrap.setAttribute('aria-live', 'polite');
  wrap.style.cssText =
    'position:absolute;left:12px;right:12px;bottom:12px;' +
    'background:#fff;border:1px solid #ddd;border-radius:6px;' +
    'box-shadow:0 4px 14px rgba(0,0,0,0.08);' +
    'padding:10px 14px;font-size:13px;font-family:inherit;color:#333;' +
    'z-index:50;display:flex;flex-direction:column;gap:6px;pointer-events:none';

  const label = document.createElement('div');
  label.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:12px';

  const name = document.createElement('span');
  name.textContent = 'Uploading ' + (filename || 'image');
  name.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1';

  const pct = document.createElement('span');
  pct.textContent = '0%';
  pct.style.cssText = 'font-variant-numeric:tabular-nums;color:#666;font-size:12px';

  label.appendChild(name);
  label.appendChild(pct);

  const track = document.createElement('div');
  track.style.cssText = 'width:100%;height:6px;background:#eee;border-radius:3px;overflow:hidden';
  const fill = document.createElement('div');
  fill.style.cssText = 'height:100%;width:0%;background:#2563eb;transition:width 120ms linear';
  track.appendChild(fill);

  wrap.appendChild(label);
  wrap.appendChild(track);
  host.appendChild(wrap);

  return {
    setProgress(p) {
      const v = Math.max(0, Math.min(100, p|0));
      fill.style.width = v + '%';
      pct.textContent = v + '%';
    },
    setStatus(text, kind) {
      name.textContent = text;
      if (kind === 'err') {
        fill.style.background = '#c44';
        pct.textContent = '';
      } else if (kind === 'ok') {
        fill.style.background = '#16a34a';
      }
    },
    remove() {
      if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
    },
  };
}

/**
 * Two independent floating panels, shared by Figure (images) and HTML
 * Embed — both carry the same default/wide/full `dataSize` attribute:
 *
 *   - Size panel (Column width / Full-browser width). For Figure in edit
 *     mode, sits top-right within the image, immediately to the left of
 *     the Rounded border/Border toggle panel (one visual row, anchored
 *     off the toggle panel's own rendered width — see editingFigureDom
 *     branch below). For HTML Embed (no edit-mode concept, selection-
 *     based instead), it floats above the selected block via a CSS
 *     transform — see the lift-above-anchor note further down.
 *   - Toggle panel: Rounded border/Border on-off toggles — top-right
 *     corner of the image. Figure only; HTML Embed has no border styling
 *     to toggle, so this one stays hidden when an htmlEmbed is selected.
 *
 * Both anchor via a CSS transform (translate(±100%, -100% - gap), see
 * tiptap.css), not a JS-measured height/width — reading offsetHeight here
 * would return 0 while the panel is still display:none (before
 * .is-visible is added), silently mis-positioning it on every hidden →
 * shown transition. That was the "have to click it a few times before it
 * shows" bug: the panel WAS showing, just overlapping the image's own top
 * edge instead of floating above it. The transform approach sizes itself
 * at paint time, so there's nothing to mis-measure.
 *
 * Both panels are created lazily on first use and reused thereafter.
 * Caption editing isn't here — `<figcaption>` is part of the figure
 * node's inline content, so the author just clicks into it and types.
 *
 * Figure's branch below is driven ENTIRELY by `editingFigureDom` — never
 * by re-deriving "what's selected" from editor.state.selection. Earlier
 * versions tried to confirm "is this figure still the selection" via
 * position resolution (domAtPos, then nodeDOM) on every selection-update,
 * and that resolution proved unreliable enough around node-selection
 * boundaries to spuriously read as "clicked away" in the very same tick
 * edit mode opened — visible as the Edit button flickering and
 * immediately reverting, twice, with two different resolution strategies.
 * `editingFigureDom` is state *we* set and own (in the Figure NodeView's
 * enterEdit/exitEdit), so checking it directly has no such ambiguity.
 * "Clicked away" is detected by a plain DOM mousedown listener (see
 * setupTiptap) instead — no ProseMirror position math involved at all.
 */
function updateFigurePanel(editor, mount) {
  const host = mount && mount.parentElement;
  if (!host) return;

  if (window.getComputedStyle(host).position === 'static') {
    host.style.position = 'relative';
  }

  let sizePanel = host.querySelector(':scope > .tt-fig-panel');
  if (!sizePanel) {
    sizePanel = document.createElement('div');
    sizePanel.className = 'tt-fig-panel';
    sizePanel.setAttribute('role', 'toolbar');
    sizePanel.setAttribute('aria-label', 'Size controls');
    sizePanel.innerHTML =
      '<button type="button" class="tt-fig-btn" data-size="default">Column width</button>' +
      '<button type="button" class="tt-fig-btn" data-size="full">Full-browser width</button>';
    sizePanel.addEventListener('mousedown', (ev) => ev.preventDefault());
    host.appendChild(sizePanel);
  }

  let togglePanel = host.querySelector(':scope > .tt-fig-toggles');
  if (!togglePanel) {
    togglePanel = document.createElement('div');
    togglePanel.className = 'tt-fig-panel tt-fig-toggles';
    togglePanel.setAttribute('role', 'toolbar');
    togglePanel.setAttribute('aria-label', 'Image style toggles');
    togglePanel.innerHTML =
      '<button type="button" class="tt-fig-btn" data-toggle="rounded">Round corners</button>' +
      '<button type="button" class="tt-fig-btn" data-toggle="border">Border</button>';
    togglePanel.addEventListener('mousedown', (ev) => ev.preventDefault());
    host.appendChild(togglePanel);
  }

  if (editingFigureDom) {
    const hostRect = host.getBoundingClientRect();
    const figRect  = editingFigureDom.getBoundingClientRect();
    // Both panels sit within the image, alongside the Edit/Save/Cancel
    // controls — no lift-above transform, no card chrome (see .is-inline
    // / .tt-fig-toggles in tiptap.css). Three visual groups: Save/Cancel
    // (top-left, fixed), then Column width / Full-browser width, then
    // Rounded border / Border — both right-aligned. When the image is
    // wide enough they sit in one row (size immediately left of
    // toggles); when it's too narrow for both, size drops to a second
    // line below the toggles instead of running off the image's edge.
    sizePanel.classList.add('is-inline');

    togglePanel.onclick = (ev) => {
      const btn = ev.target.closest('[data-toggle]:not(:disabled)');
      if (!btn) return;
      const cmd = btn.dataset.toggle === 'rounded' ? 'toggleFigureRounded' : 'toggleFigureBorder';
      editor.chain()[cmd]().run();
    };
    const attrs = editor.getAttributes('figure');
    const currentSize = attrs.dataSize || 'default';
    // Full-browser width always renders flush corners on the public page
    // (see .article-prose figure[data-size="full"] img in blocks.css —
    // border-radius is forced to 0 there regardless of data-rounded), so
    // Rounded has nothing to affect at this size. Disable it rather than
    // leave a toggle that visibly does nothing.
    const roundedBtn = togglePanel.querySelector('[data-toggle="rounded"]');
    roundedBtn.disabled = currentSize === 'full';
    roundedBtn.classList.toggle('is-active', attrs.dataRounded !== false && currentSize !== 'full');
    const borderBtn = togglePanel.querySelector('[data-toggle="border"]');
    const borderOn = attrs.dataBorder !== false;
    borderBtn.classList.toggle('is-active', borderOn);
    borderBtn.textContent = borderOn ? 'Border on' : 'Border off';

    // No .focus() here — updateAttributes() reads editor.state.selection,
    // not DOM focus, so forcing focus on every toggle click only risked
    // re-triggering the same caret-resync flicker enterEdit() had.
    sizePanel.onclick = (ev) => {
      const btn = ev.target.closest('[data-size]');
      if (!btn) return;
      editor.chain().setFigureSize(btn.dataset.size || 'default').run();
    };
    for (const btn of sizePanel.querySelectorAll('[data-size]')) {
      btn.classList.toggle('is-active', btn.dataset.size === currentSize);
    }

    // Both need to be display:flex (not display:none) before measuring —
    // offsetWidth/offsetHeight read 0 on a hidden element.
    togglePanel.classList.add('is-visible');
    sizePanel.classList.add('is-visible');

    // 8px inset to match .tt-fig-edit-controls' var(--space-8).
    const topInset = (figRect.top - hostRect.top + 8) + 'px';
    const rightInset = hostRect.right - figRect.right + 8;
    const gap = 8; // matches var(--space-8) used elsewhere on this row
    togglePanel.style.top   = topInset;
    togglePanel.style.right = rightInset + 'px';

    const fitsSideBySide =
      sizePanel.offsetWidth + gap + togglePanel.offsetWidth + rightInset <= figRect.width;
    sizePanel.style.left = '';
    sizePanel.style.right = rightInset + 'px';
    sizePanel.style.top = fitsSideBySide
      ? topInset
      : (figRect.top - hostRect.top + 8 + togglePanel.offsetHeight + gap) + 'px';
    if (fitsSideBySide) {
      sizePanel.style.right = (rightInset + togglePanel.offsetWidth + gap) + 'px';
    }
    return;
  }

  sizePanel.classList.remove('is-inline');
  togglePanel.classList.remove('is-visible');

  // No figure being edited — fall back to HTML Embed's simpler
  // selection-based size panel. A true atom node; selecting it via a
  // plain click has always been reliable (no edit-mode complexity, no
  // figcaption-content ambiguity), so selection-based showing is fine.
  if (!editor.isActive('htmlEmbed')) {
    sizePanel.classList.remove('is-visible');
    return;
  }
  const selectedEl = editor.view.nodeDOM(editor.state.selection.from);
  if (!(selectedEl instanceof Element && selectedEl.matches?.('.html-embed'))) {
    sizePanel.classList.remove('is-visible');
    return;
  }

  sizePanel.onclick = (ev) => {
    const btn = ev.target.closest('[data-size]');
    if (!btn) return;
    editor.chain().focus().setHtmlEmbedSize(btn.dataset.size || 'default').run();
  };
  const hostRect = host.getBoundingClientRect();
  const elRect    = selectedEl.getBoundingClientRect();
  sizePanel.style.top   = (elRect.top - hostRect.top) + 'px';
  sizePanel.style.left  = (elRect.left - hostRect.left) + 'px';
  sizePanel.style.right = '';
  sizePanel.classList.add('is-visible');
  const current = editor.getAttributes('htmlEmbed').dataSize || 'default';
  for (const btn of sizePanel.querySelectorAll('[data-size]')) {
    btn.classList.toggle('is-active', btn.dataset.size === current);
  }
}

/**
 * Reflect the editor's current state into toolbar button `.is-active` flags.
 * No raw classes — we use the .is-active state class per ENGINEERING.md §6.3.
 */
function updateToolbarState(editor, toolbar) {
  const checks = {
    bold:       () => editor.isActive('bold'),
    italic:     () => editor.isActive('italic'),
    h2:         () => editor.isActive('heading', { level: 2 }),
    h3:         () => editor.isActive('heading', { level: 3 }),
    ul:         () => editor.isActive('bulletList'),
    ol:         () => editor.isActive('orderedList'),
    blockquote: () => editor.isActive('blockquote'),
    code:       () => editor.isActive('code'),
    muted:      () => editor.isActive('muted'),
    link:       () => editor.isActive('link'),
    image:      () => false,
    'html-embed': () => false,
  };
  for (const btn of toolbar.querySelectorAll('button[data-cmd]')) {
    const cmd = btn.dataset.cmd;
    const fn = checks[cmd];
    if (!fn) continue;
    btn.classList.toggle('is-active', fn());
  }
}
