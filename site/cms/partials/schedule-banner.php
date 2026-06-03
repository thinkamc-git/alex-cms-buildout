<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/schedule-banner.php — amber "Scheduled for publish" banner
 * shown at the top of a content editor when published_status='scheduled'.
 *
 * Inputs (set before include):
 *   $published_at_raw  string  Raw MySQL datetime (caller-provided).
 *
 * Extracted Batch 2 #16. The countdown <span> is hydrated client-side by
 * the same code that powers all four editors today.
 */

$published_at_raw = (string)($published_at_raw ?? '');
$e = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<div class="schedule-banner" data-target="<?= $e($published_at_raw) ?>">
  <span class="schedule-banner-icon">⏱</span>
  <span class="schedule-banner-text">
    Scheduled for publish on <strong><?= $e(date('M j, Y · g:i A', strtotime($published_at_raw))) ?></strong>
    · <span class="schedule-countdown" data-countdown>computing…</span>
  </span>
</div>
