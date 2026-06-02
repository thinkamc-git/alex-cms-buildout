<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-event-card.php — combined When/Where panel + Format Tags pills.
 * Per BLOCKS.md §7 Live Session: "Event Card — Event Details + Format Tags,
 * combined white card with the same surface treatment as Author Bio."
 *
 * Visual design ported from site/_templates/layouts.html — the When/Where
 * pair renders as a 2-column grid with each value in Instrument Serif
 * italic at 28px (editorial flair), and the format tags fill the bottom
 * row across both columns with a top rule separator.
 *
 * Reads (split into three fields in migration 0005):
 *   $article['event_date']      DATE — required at save-time. Date-only is OK.
 *   $article['event_time']      TIME — start time, optional.
 *   $article['event_end_time']  TIME — end time, optional. Only meaningful
 *                                       when event_time is also set.
 *   $article['location']        string  primary city/region line
 *   $article['venue']           string  smaller subline (e.g., room or venue name)
 *   $article['cost_pill']       string  (NULL hides)
 *   $article['attendance']      ENUM    in-person | remote (NULL hides)
 *   $article['custom_pill']     string  (NULL hides)
 *
 * Rendering of the time line (secondary, smaller, under the date):
 *   • date only           → no time line
 *   • date + start        → "2:00 PM"
 *   • date + start + end  → "2:00 – 4:00 PM"
 */
$a            = $ctx['article'] ?? [];
$eventDate    = (string)($a['event_date']     ?? '');
$eventTimeRaw = (string)($a['event_time']     ?? '');
$eventEndRaw  = (string)($a['event_end_time'] ?? '');
$location     = trim((string)($a['location']    ?? ''));
$venue        = trim((string)($a['venue']       ?? ''));
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

// WHEN — date is the primary editorial line; time is the smaller second line.
$datePart = '';
$timePart = '';
if ($hasWhen) {
    $ts = strtotime($eventDate);
    $datePart = $ts !== false ? date('M j, Y', $ts) : $eventDate;

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
            $timePart = $startStr . ' – ' . $endLabel;
        } else {
            $timePart = $startLabel;
        }
    }
}

// Format-tag modifier class — value-driven, matching layouts.html.
$tagMod = static function (string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
        'free'      => ' is-free',
        'in-person' => ' is-in-person',
        'remote'    => ' is-remote',
        default     => '',
    };
};

$attendanceLabel = '';
if ($attendance === 'in-person') $attendanceLabel = 'In-person';
elseif ($attendance === 'remote') $attendanceLabel = 'Remote';
?>
<aside class="event-card" data-block="event-card">
  <?php if ($hasWhen): ?>
    <div class="event-card-block">
      <div class="event-meta-block-label">When</div>
      <div class="event-date"><?= $e($datePart) ?></div>
      <?php if ($timePart !== ''): ?>
        <div class="event-time"><?= $e($timePart) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($hasWhere): ?>
    <div class="event-card-block">
      <div class="event-meta-block-label">Where</div>
      <div class="event-location"><?= $e($location) ?></div>
      <?php if ($venue !== ''): ?>
        <div class="event-cost-detail"><?= $e($venue) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($hasPills): ?>
    <div class="event-card-tags">
      <?php if ($costPill !== ''): ?>
        <span class="event-format-tag<?= $e($tagMod($costPill)) ?>"><?= $e($costPill) ?></span>
      <?php endif; ?>
      <?php if ($attendanceLabel !== ''): ?>
        <span class="event-format-tag<?= $e($tagMod($attendance)) ?>"><?= $e($attendanceLabel) ?></span>
      <?php endif; ?>
      <?php if ($customPill !== ''): ?>
        <span class="event-format-tag"><?= $e($customPill) ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</aside>
