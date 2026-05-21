<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-event-card.php — combined When/Where panel + Format Tags pills.
 * Per BLOCKS.md §7 Live Session: "Event Card — Event Details + Format Tags,
 * combined white card with the same surface treatment as Author Bio."
 *
 * Reads (split into three fields in migration 0005):
 *   $article['event_date']      DATE — required at save-time. Date-only is OK.
 *   $article['event_time']      TIME — start time, optional.
 *   $article['event_end_time']  TIME — end time, optional. Only meaningful
 *                                       when event_time is also set.
 *   $article['location']        string
 *   $article['cost_pill']       string  (NULL hides)
 *   $article['attendance']      ENUM    in-person | remote (NULL hides)
 *   $article['custom_pill']     string  (NULL hides)
 *
 * Rendering of the time line:
 *   • date only           → "Sat, May 24"
 *   • date + start        → "Sat, May 24 · 2:00 PM"
 *   • date + start + end  → "Sat, May 24 · 2:00 – 4:00 PM"
 */
$a            = $ctx['article'] ?? [];
$eventDate    = (string)($a['event_date']     ?? '');
$eventTimeRaw = (string)($a['event_time']     ?? '');
$eventEndRaw  = (string)($a['event_end_time'] ?? '');
$location     = trim((string)($a['location']    ?? ''));
$costPill     = trim((string)($a['cost_pill']   ?? ''));
$attendance   = trim((string)($a['attendance']  ?? ''));
$customPill   = trim((string)($a['custom_pill'] ?? ''));

$eventTime = $eventTimeRaw !== '' ? substr($eventTimeRaw, 0, 5) : '';
$eventEnd  = $eventEndRaw  !== '' ? substr($eventEndRaw,  0, 5) : '';

$hasWhen  = $eventDate !== '';
$hasWhere = $location  !== '';
$hasPills = $costPill !== '' || $attendance !== '' || $customPill !== '';
if (!$hasWhen && !$hasWhere && !$hasPills) return;

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$whenStr = '';
if ($hasWhen) {
    $ts = strtotime($eventDate);
    $datePart = $ts !== false ? date('D, M j', $ts) : $eventDate;

    $timePart = '';
    if ($eventTime !== '') {
        $startTs = strtotime($eventTime);
        $startLabel = $startTs !== false ? date('g:i A', $startTs) : $eventTime;
        if ($eventEnd !== '') {
            $endTs    = strtotime($eventEnd);
            $endLabel = $endTs !== false ? date('g:i A', $endTs) : $eventEnd;
            // If start and end share AM/PM, drop the suffix from the start
            // for tighter reading ("2:00 – 4:00 PM" vs "2:00 PM – 4:00 PM").
            $startBare = $startTs !== false ? date('g:i',   $startTs) : $eventTime;
            $startMer  = $startTs !== false ? date('A',     $startTs) : '';
            $endMer    = $endTs   !== false ? date('A',     $endTs)   : '';
            $startStr  = ($startMer === $endMer && $startMer !== '')
                ? $startBare
                : $startLabel;
            $timePart = ' · ' . $startStr . ' – ' . $endLabel;
        } else {
            $timePart = ' · ' . $startLabel;
        }
    }
    $whenStr = $datePart . $timePart;
}

$attendanceLabel = '';
if ($attendance === 'in-person') $attendanceLabel = 'In-Person';
elseif ($attendance === 'remote') $attendanceLabel = 'Remote';
?>
<aside class="article-event-card" data-block="event-card">
  <?php if ($hasWhen || $hasWhere): ?>
    <dl class="article-event-details">
      <?php if ($whenStr !== ''): ?>
        <div class="article-event-row">
          <dt>When</dt>
          <dd><?= $e($whenStr) ?></dd>
        </div>
      <?php endif; ?>
      <?php if ($hasWhere): ?>
        <div class="article-event-row">
          <dt>Where</dt>
          <dd><?= $e($location) ?></dd>
        </div>
      <?php endif; ?>
    </dl>
  <?php endif; ?>

  <?php if ($hasPills): ?>
    <ul class="article-format-tags">
      <?php if ($costPill !== ''): ?>
        <li class="article-format-pill article-format-pill--cost"><?= $e($costPill) ?></li>
      <?php endif; ?>
      <?php if ($attendanceLabel !== ''): ?>
        <li class="article-format-pill article-format-pill--attendance"><?= $e($attendanceLabel) ?></li>
      <?php endif; ?>
      <?php if ($customPill !== ''): ?>
        <li class="article-format-pill article-format-pill--custom"><?= $e($customPill) ?></li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>
</aside>
