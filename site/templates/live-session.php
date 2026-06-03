<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/live-session.php — Live Session rendering (Phase 9).
 *
 * Composes the live-session block order from BLOCKS.md §7:
 *   1. Topstrip (Category · PAST badge when event_start has passed)
 *   2. Title
 *   3. Summary
 *   4. Publish Date
 *   5. Event Card — Event Details (When/Where) + Format Tags
 *   6. Author byline
 *   7. Body
 *   8. Author Bio (footer)
 *   9. Tags
 *
 * Expects $ctx (set by render_content):
 *   $ctx['article']   row from `content` (type='live-session')
 *   $ctx['author']    output of author_display()
 *   $ctx['category']  ['value_slug','label','colour'] or null
 */
$cat       = $ctx['category'] ?? null;
$catColour = is_array($cat) ? (string)($cat['colour'] ?? '') : '';
$catLabel  = is_array($cat) ? (string)($cat['label']  ?? '') : '';

$_eDate    = (string)($ctx['article']['event_date']     ?? '');
$_eTimeRaw = (string)($ctx['article']['event_time']     ?? '');
$_eEndRaw  = (string)($ctx['article']['event_end_time'] ?? '');
$_eTime    = $_eTimeRaw !== '' ? substr($_eTimeRaw, 0, 5) : '';
$_eEnd     = $_eEndRaw  !== '' ? substr($_eEndRaw,  0, 5) : '';
$isPast    = false;
if ($_eDate !== '') {
    $_cmp = $_eEnd !== '' ? $_eEnd : ($_eTime !== '' ? $_eTime : '23:59');
    $_ts  = strtotime($_eDate . ' ' . $_cmp);
    if ($_ts !== false && $_ts < time()) $isPast = true;
}
?>
<?php if (empty($preview_no_chrome)): ?>
<nav class="article-breadcrumb" aria-label="Breadcrumb">
  <a class="article-breadcrumb-root" href="/live-sessions">Live Sessions</a>
  <?php if ($catLabel !== ''): ?>
    <span class="article-breadcrumb-sep" aria-hidden="true">→</span>
    <span class="article-breadcrumb-current"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</nav>
<?php endif; ?>

<article class="article article-live-session"<?= $catColour !== '' ? ' data-category="' . htmlspecialchars($catColour, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <div class="article-topstrip">
    <?php render_block('category', $ctx); ?>
    <?php if ($isPast): ?>
      <div class="article-topstrip-right">
        <span class="article-past-badge" data-block="past">PAST</span>
      </div>
    <?php endif; ?>
  </div>

  <?php render_block('title',   $ctx); ?>
  <?php render_block('summary', $ctx); ?>

  <div class="article-dates">
    <?php render_block('publish-date', $ctx); ?>
  </div>

  <?php render_block('event-card', $ctx); ?>

  <div class="article-byline-row article-byline-row--journal">
    <?php render_block('author', $ctx); ?>
  </div>

  <?php render_block('hero-image', $ctx); ?>
  <?php render_block('body', $ctx); ?>
  <?php render_block('author-bio', $ctx); ?>

  <footer class="article-tags">
    <?php render_block('tags', $ctx); ?>
  </footer>
</article>
