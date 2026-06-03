<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/form-errors.php — single canonical .form-errors block.
 *
 * Inputs (set before include):
 *   $errors      array   Required. List of error strings (the partial
 *                        escapes each one). Renders nothing when empty.
 *   $heading     string  Optional. Strong heading copy above the list.
 *                        Defaults to "Please fix the following:".
 *                        Common overrides: "Couldn't save:",
 *                        "Couldn't update:", "Couldn't create:".
 *
 * Spacing is owned by .form-errors in style-cms.css; callers do NOT
 * pass inline margin styles.
 */

$errors  = (array)($errors ?? []);
$heading = (string)($heading ?? 'Please fix the following:');

if (count($errors) === 0) {
    return;
}
?>
<div class="form-errors" role="alert">
  <strong><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></strong>
  <ul>
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</div>
