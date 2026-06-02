<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-updated-date.php — explicit per-content opt-in (Phase 14.6 followup).
 *
 * Renders only when `show_updated` is true on the content row. The displayed
 * date is `updated_display` when set, otherwise falls back to the actual
 * `updated_at` (the last DB write time). Migration 0013 added both columns.
 *
 * The old auto-conditional rule ("show when updated_at differs from
 * published_at by > 24h") was dropped — authors now opt in per-row from
 * the edit view's Publish info box.
 */
$row = $ctx['article'] ?? [];
$show = !empty($row['show_updated']);
if (!$show) return;

$override = (string)($row['updated_display'] ?? '');
$actual   = (string)($row['updated_at']      ?? '');
$src      = $override !== '' ? $override : $actual;
if ($src === '') return;

$ts = strtotime($src);
if ($ts === false) return;
?>
<span class="article-updated-date" data-block="updated-date">Updated <?= htmlspecialchars(date('M j, Y', $ts), ENT_QUOTES, 'UTF-8') ?></span>
