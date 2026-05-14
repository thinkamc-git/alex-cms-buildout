<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/table.php — list-view table primitive.
 *
 * Inputs (set before include):
 *   $columns      array  Ordered list of columns. Each:
 *     [ 'label' => 'Title', 'width' => '36%' ]   // 'width' optional
 *
 *   $rows         array  Each row is an ordered list of cells matching
 *                        $columns. A cell may be:
 *                          - string: rendered as raw HTML (caller escapes)
 *                          - array:  [ 'html' => '…' ] or [ 'text' => '…', 'class' => '…' ]
 *
 *   $empty_text   string Shown when $rows is empty. Optional; defaults to
 *                        "No entries yet."
 *
 * The mockup pairs every <table class="cms-table"> with a wrapping
 * <div class="table-card"> for the card border + radius. This partial
 * always renders that wrapper, so callers compose by simply varying
 * $columns / $rows.
 */

$columns    = (array)($columns ?? []);
$rows       = (array)($rows ?? []);
$empty_text = (string)($empty_text ?? 'No entries yet.');
$colCount   = max(count($columns), 1);
?>
<div class="table-card">
  <table class="cms-table">
    <?php if (count($columns) > 0): ?>
      <thead>
        <tr>
          <?php foreach ($columns as $col):
              $label = (string)($col['label'] ?? '');
              $width = (string)($col['width'] ?? '');
              $style = $width !== '' ? ' style="width:' . htmlspecialchars($width, ENT_QUOTES, 'UTF-8') . '"' : '';
          ?>
            <th<?= $style ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
    <?php endif; ?>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr>
          <td colspan="<?= $colCount ?>" style="text-align:center;color:var(--muted);padding:var(--space-24)">
            <?= htmlspecialchars($empty_text, ENT_QUOTES, 'UTF-8') ?>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ((array)$row as $cell):
                if (is_array($cell)) {
                    $cellClass = isset($cell['class']) ? ' class="' . htmlspecialchars((string)$cell['class'], ENT_QUOTES, 'UTF-8') . '"' : '';
                    if (isset($cell['html'])) {
                        $cellContent = (string)$cell['html'];
                    } else {
                        $cellContent = htmlspecialchars((string)($cell['text'] ?? ''), ENT_QUOTES, 'UTF-8');
                    }
                } else {
                    $cellClass   = '';
                    $cellContent = (string)$cell;
                }
            ?>
              <td<?= $cellClass ?>><?= $cellContent ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
