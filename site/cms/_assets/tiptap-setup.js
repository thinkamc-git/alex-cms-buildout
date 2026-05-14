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

import { Editor, Mark, mergeAttributes }
  from 'https://esm.sh/@tiptap/core@2.6.6';
import StarterKit
  from 'https://esm.sh/@tiptap/starter-kit@2.6.6';
import Link
  from 'https://esm.sh/@tiptap/extension-link@2.6.6';
import Image
  from 'https://esm.sh/@tiptap/extension-image@2.6.6';

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
      Image.configure({ inline: false }),
      Muted,
    ],
    content: fallback.value,
    onUpdate({ editor }) {
      fallback.value = editor.getHTML();
      updateToolbarState(editor, toolbar);
    },
    onSelectionUpdate({ editor }) {
      updateToolbarState(editor, toolbar);
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
    try {
      const url = await uploadImage(file, { uploadUrl, csrfToken });
      editor.chain().focus().setImage({ src: url, alt: '' }).run();
    } catch (e) {
      window.alert('Image upload failed: ' + (e && e.message ? e.message : 'unknown error'));
    }
  });
  input.click();
}

async function uploadImage(file, { uploadUrl, csrfToken }) {
  const fd = new FormData();
  fd.append('image', file);
  const res = await fetch(uploadUrl, {
    method: 'POST',
    body: fd,
    headers: { 'X-CSRF-Token': csrfToken },
    credentials: 'same-origin',
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.ok || !json.url) {
    throw new Error(json.error || ('HTTP ' + res.status));
  }
  return json.url;
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
