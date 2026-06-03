<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/publish-box.php — sidebar Publish panel for editors.
 *
 * Renders one or both of:
 *   1. The is-live variant: live indicator + "View live ↗" link +
 *      editable publish date + Show-updated toggle row.
 *   2. The schedule variant: "Schedule for Publish" toggle + datetime input.
 *
 * Inputs (set before include):
 *   $is_live              bool    Renders the live variant when true.
 *   $show_publish_section bool    Renders the schedule variant when true.
 *   $is_scheduled         bool    Pre-checks the schedule toggle.
 *
 *   $live_url             string  Path to the live page (e.g. "/writing/foo").
 *   $published_at_id      string  HTML id for the published-at <input>.
 *   $published_at_value   string  Datetime-local value (pre-formatted).
 *   $updated_label        string  Copy after "Show 'Updated' date on the …"
 *                                 (e.g. "article", "journal entry").
 *   $show_updated         bool    Pre-checks the show-updated checkbox.
 *   $updated_input_value  string  Current updated_display value (date-only).
 *   $updated_default      string  Default updated-display (date-only).
 *   $updated_has_override bool    True when value differs from default.
 *
 *   $schedule_at_value    string  Datetime-local value (pre-formatted).
 *   $min_schedule_at      string  Min datetime-local for the input.
 *
 *   $e                    callable htmlspecialchars helper closure (caller-
 *                                  supplied — matches editor convention).
 *
 * The partial assumes the caller has already opened its <aside> /
 * .form-side container; it renders just the .cms-publish-box block(s).
 */

$is_live              = (bool)($is_live              ?? false);
$show_publish_section = (bool)($show_publish_section ?? false);
$is_scheduled         = (bool)($is_scheduled         ?? false);
$live_url             = (string)($live_url           ?? '');
$published_at_id      = (string)($published_at_id    ?? 'published-at');
$published_at_value   = (string)($published_at_value ?? '');
$updated_label        = (string)($updated_label      ?? 'page');
$show_updated         = (bool)($show_updated         ?? false);
$updated_input_value  = (string)($updated_input_value ?? '');
$updated_default      = (string)($updated_default     ?? '');
$updated_has_override = (bool)($updated_has_override  ?? false);
$schedule_at_value    = (string)($schedule_at_value   ?? '');
$min_schedule_at      = (string)($min_schedule_at     ?? '');

if (!isset($e) || !is_callable($e)) {
    $e = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
}
?>
<?php if ($is_live): ?>
  <div class="cms-publish-box is-live">
    <div class="cms-publish-header">
      <span class="cms-live-indicator">
        <span class="cms-live-dot" aria-hidden="true"></span>
        Live
      </span>
      <a href="<?= $e($live_url) ?>"
         target="_blank"
         rel="noopener"
         class="btn-ghost btn-tiny">View live ↗</a>
    </div>
    <div class="field-group" style="margin-bottom:var(--space-12)">
      <label class="field-sublabel" for="<?= $e($published_at_id) ?>">Published</label>
      <input type="datetime-local"
             name="published_at"
             id="<?= $e($published_at_id) ?>"
             class="field-input"
             value="<?= $e($published_at_value) ?>">
      <p class="field-hint">Editable. Changes the publish date displayed on the live page.</p>
    </div>
    <div class="field-group cms-updated-group" data-updated-group style="margin-bottom:0">
      <label class="cms-publish-check">
        <input type="checkbox" name="show_updated" value="1" <?= $show_updated ? 'checked' : '' ?> data-show-updated>
        <span>Show "Updated" date on the <?= $e($updated_label) ?></span>
      </label>
      <div class="cms-updated-input-row" data-updated-row>
        <input type="date"
               name="updated_display"
               class="field-input <?= !$updated_has_override ? 'is-default' : '' ?>"
               value="<?= $e($updated_input_value) ?>"
               data-default="<?= $e($updated_default) ?>"
               data-updated-input
               <?= !$show_updated ? 'disabled' : '' ?>>
        <button type="button"
                class="cms-updated-clear"
                data-clear-updated
                title="Reset to actual last update date"
                <?= !$updated_has_override ? 'hidden' : '' ?>>×</button>
      </div>
      <p class="field-hint">Default: actual last save date. Override to display a different date.</p>
    </div>
  </div>
<?php endif; ?>

<?php if ($show_publish_section): ?>
  <div class="cms-publish-box">
    <div class="field-group cms-publish-section" data-publish-section>
      <label class="field-label">Schedule for Publish</label>
      <div class="cms-publish-toggle">
        <label class="cms-publish-check">
          <input type="checkbox" name="schedule_enabled" value="1" <?= $is_scheduled ? 'checked' : '' ?> data-publish-toggle>
          <span>Schedule for later</span>
        </label>
      </div>
      <div class="cms-publish-schedule" data-publish-schedule-row<?= !$is_scheduled ? ' hidden' : '' ?>>
        <input type="datetime-local"
               name="schedule_at"
               class="field-input"
               value="<?= $e($schedule_at_value) ?>"
               min="<?= $e($min_schedule_at) ?>"
               data-schedule-input>
        <p class="field-hint">Must be at least one minute in the future. The system auto-publishes scheduled entries at this time.</p>
      </div>
    </div>
  </div>
<?php endif; ?>
