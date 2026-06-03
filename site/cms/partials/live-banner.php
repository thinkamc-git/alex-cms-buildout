<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/live-banner.php — green "Published" banner shown at the top
 * of a content editor when the row is live (status=published, not scheduled).
 *
 * Inputs (set before include):
 *   $published_at_raw  string  Raw MySQL datetime (caller-provided). Empty
 *                              string suppresses the "on <date>" suffix.
 *   $live_url          string  Path to the live page. Empty hides the link.
 *
 * Extracted Batch 2 #15 — same banner styling across all four editors. The
 * wording stays "Published" for every content type; the link href reflects
 * the type's public URL.
 */

$published_at_raw = (string)($published_at_raw ?? '');
$live_url         = (string)($live_url         ?? '');
$e = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<div class="live-banner">
  <span class="cms-live-dot" aria-hidden="true"></span>
  <span class="live-banner-text">
    Published<?php if ($published_at_raw !== ''): ?> on <strong><?= $e(date('M j, Y · g:i A', strtotime($published_at_raw))) ?></strong><?php endif; ?>
  </span>
  <?php if ($live_url !== ''): ?>
    <a class="live-banner-link" href="<?= $e($live_url) ?>" target="_blank" rel="noopener">View live ↗</a>
  <?php endif; ?>
</div>
