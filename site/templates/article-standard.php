<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/article-standard.php — long-form article rendering.
 *
 * Composes the 13 article blocks in the order from BLOCKS.md §7. Blocks
 * with no data render nothing (Path A); the topstrip and byline-row
 * structural containers always render — their .article-author /
 * .article-category children supply default placeholders so the visual
 * rhythm stays intact even on minimal content.
 *
 * Expects $ctx (set by render_content):
 *   $ctx['article']   row from `content`
 *   $ctx['author']    output of author_display()
 *   $ctx['category']  ['value_slug','label','colour'] or null
 *   $ctx['series']    ['name', '_count'] or null
 *
 * `render_block($slug, $ctx)` is a thin include — see lib/render.php.
 */
$cat = $ctx['category'] ?? null;
$catColour = is_array($cat) ? (string)($cat['colour'] ?? '') : '';
$catLabel  = is_array($cat) ? (string)($cat['label']  ?? '') : '';
?>
<nav class="article-breadcrumb" aria-label="Breadcrumb">
  <a class="article-breadcrumb-root" href="/writing">Writing</a>
  <?php if ($catLabel !== ''): ?>
    <span class="article-breadcrumb-sep" aria-hidden="true">→</span>
    <span class="article-breadcrumb-current"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</nav>

<article class="article"<?= $catColour !== '' ? ' data-category="' . htmlspecialchars($catColour, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <div class="article-topstrip">
    <?php render_block('category', $ctx); ?>
    <div class="article-topstrip-right">
      <?php render_block('series', $ctx); ?>
    </div>
  </div>

  <?php render_block('title', $ctx); ?>
  <?php render_block('summary', $ctx); ?>
  <?php render_block('special-tag', $ctx); ?>

  <div class="article-dates">
    <?php render_block('publish-date', $ctx); ?>
    <?php render_block('updated-date', $ctx); ?>
  </div>

  <div class="article-byline-row">
    <?php render_block('author', $ctx); ?>
    <?php render_block('read-time', $ctx); ?>
  </div>

  <?php render_block('hero-image', $ctx); ?>
  <?php render_block('body', $ctx); ?>
  <?php render_block('author-bio', $ctx); ?>

  <footer class="article-tags">
    <?php render_block('tags', $ctx); ?>
  </footer>
</article>
