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
  input.accept = 'image/jpeg,image/png,image/webp,image/gif';
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
 * Floating figure-controls panel.
 *
 * Pinned above the currently-selected figure with two size-preset pills:
 * Column (default — column width) and Full Browser (data-size="full"). The
 * panel is created lazily on first use and reused thereafter. Hidden when
 * selection is outside any figure.
 *
 * Caption editing isn't here — `<figcaption>` is part of the figure node's
 * inline content, so the author just clicks into it and types.
 */
function updateFigurePanel(editor, mount) {
  const host = mount && mount.parentElement;
  if (!host) return;

  // Lazy-create the panel on first call. Anchored absolutely to the host
  // (mount's parent — the .tiptap-wrap), which is positioned for sticky.
  let panel = host.querySelector(':scope > .tt-fig-panel');
  if (!panel) {
    if (window.getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }
    panel = document.createElement('div');
    panel.className = 'tt-fig-panel';
    panel.setAttribute('role', 'toolbar');
    panel.setAttribute('aria-label', 'Image controls');
    panel.innerHTML =
      '<button type="button" class="tt-fig-btn" data-size="default">Column</button>' +
      '<button type="button" class="tt-fig-btn" data-size="full">Full browser</button>';
    panel.addEventListener('mousedown', (ev) => {
      // Keep the editor selection while clicking the toolbar.
      ev.preventDefault();
    });
    panel.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.tt-fig-btn');
      if (!btn) return;
      const size = btn.dataset.size || 'default';
      editor.chain().focus().setFigureSize(size).run();
    });
    host.appendChild(panel);
  }

  if (!editor.isActive('figure')) {
    panel.classList.remove('is-visible');
    return;
  }

  // Position the panel above the selected figure. Use the DOM node for the
  // current selection's parent figure element.
  const { from } = editor.state.selection;
  const domAtPos = editor.view.domAtPos(from);
  let el = domAtPos && domAtPos.node;
  while (el && el.nodeType === 3) el = el.parentNode;
  while (el && el !== host && el.tagName !== 'FIGURE') el = el.parentNode;
  if (!el || el === host) {
    panel.classList.remove('is-visible');
    return;
  }

  const hostRect = host.getBoundingClientRect();
  const figRect  = el.getBoundingClientRect();
  panel.style.top  = (figRect.top - hostRect.top - panel.offsetHeight - 8) + 'px';
  panel.style.left = (figRect.left - hostRect.left) + 'px';
  panel.classList.add('is-visible');

  // Reflect current data-size on the buttons.
  const current = editor.getAttributes('figure').dataSize || 'default';
  for (const btn of panel.querySelectorAll('.tt-fig-btn')) {
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
  };
  for (const btn of toolbar.querySelectorAll('button[data-cmd]')) {
    const cmd = btn.dataset.cmd;
    const fn = checks[cmd];
    if (!fn) continue;
    btn.classList.toggle('is-active', fn());
  }
}
