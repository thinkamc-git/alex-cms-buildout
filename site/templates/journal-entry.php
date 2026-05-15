<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/journal-entry.php — Journal entry rendering (Phase 8).
 *
 * Composes the journal block order from BLOCKS.md §journal:
 *   1. Topstrip (Category · Entry Number)
 *   2. Key Statement (replaces Title)
 *   3. Date row — Publish Date
 *   4. Byline row — Author byline only (no read-time)
 *   5. Body
 *   6. Author Bio (footer)
 *   7. Tags
 *
 * Expects $ctx (set by render_content) — same shape as article-standard:
 *   $ctx['article']   row from `content` (type='journal')
 *   $ctx['author']    output of author_display()
 *   $ctx['category']  ['value_slug','label','colour'] or null
 */
$cat       = $ctx['category'] ?? null;
$catColour = is_array($cat) ? (string)($cat['colour'] ?? '') : '';
$catLabel  = is_array($cat) ? (string)($cat['label']  ?? '') : '';
?>
<nav class="article-breadcrumb" aria-label="Breadcrumb">
  <a class="article-breadcrumb-root" href="/journal">Journal</a>
  <?php if ($catLabel !== ''): ?>
    <span class="article-breadcrumb-sep" aria-hidden="true">→</span>
    <span class="article-breadcrumb-current"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</nav>

<article class="article article-journal"<?= $catColour !== '' ? ' data-category="' . htmlspecialchars($catColour, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <div class="article-topstrip">
    <?php render_block('category', $ctx); ?>
    <div class="article-topstrip-right">
      <?php render_block('entry-number', $ctx); ?>
    </div>
  </div>

  <?php render_block('key-statement', $ctx); ?>

  <div class="article-dates">
    <?php render_block('publish-date', $ctx); ?>
  </div>

  <div class="article-byline-row article-byline-row--journal">
    <?php render_block('author', $ctx); ?>
  </div>

  <?php render_block('body', $ctx); ?>
  <?php render_block('author-bio', $ctx); ?>

  <footer class="article-tags">
    <?php render_block('tags', $ctx); ?>
  </footer>
</article>
