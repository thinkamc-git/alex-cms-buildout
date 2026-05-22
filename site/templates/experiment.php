<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/experiment.php — article-format experiment rendering (Phase 10).
 *
 * Mirrors article-standard.php (Phase 6b) — same block order, same chrome.
 * The variant difference between an Article and an article-format Experiment
 * is the breadcrumb root (/experiments vs /writing) and the absence of the
 * Special Tag and Series blocks (experiments don't carry those).
 *
 * Expects $ctx (set by render_content):
 *   $ctx['article']   row from `content` (type='experiment', template='experiment')
 *   $ctx['author']    output of author_display()
 *   $ctx['category']  ['value_slug','label','colour'] or null
 */
$cat       = $ctx['category'] ?? null;
$catColour = is_array($cat) ? (string)($cat['colour'] ?? '') : '';
$catLabel  = is_array($cat) ? (string)($cat['label']  ?? '') : '';
?>
<nav class="article-breadcrumb" aria-label="Breadcrumb">
  <a class="article-breadcrumb-root" href="/experiments">Experiments</a>
  <?php if ($catLabel !== ''): ?>
    <span class="article-breadcrumb-sep" aria-hidden="true">→</span>
    <span class="article-breadcrumb-current"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</nav>

<article class="article article-experiment"<?= $catColour !== '' ? ' data-category="' . htmlspecialchars($catColour, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <div class="article-topstrip">
    <?php render_block('category', $ctx); ?>
  </div>

  <?php render_block('title',   $ctx); ?>
  <?php render_block('summary', $ctx); ?>

  <div class="article-dates">
    <?php render_block('publish-date', $ctx); ?>
    <?php render_block('updated-date', $ctx); ?>
  </div>

  <div class="article-byline-row">
    <?php render_block('author',    $ctx); ?>
    <?php render_block('read-time', $ctx); ?>
  </div>

  <?php render_block('hero-image', $ctx); ?>
  <?php render_block('body',       $ctx); ?>
  <?php render_block('author-bio', $ctx); ?>

  <footer class="article-tags">
    <?php render_block('tags', $ctx); ?>
  </footer>
</article>
