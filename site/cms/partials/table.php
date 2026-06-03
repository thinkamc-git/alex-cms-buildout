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
 *   $variant      string Optional visual sub-style: 'default' (omit),
 *                        'cat', 'sub', or 'reference'. Applied as an
 *                        extra class on .table-card and .cms-table for
 *                        scoped per-view tweaks (column proportions,
 *                        tints) — used by categories / subscribers /
 *                        post-template's reference tables.
 *
 *   $table_attrs  string Optional. RAW HTML attributes string appended
 *                        to the <table> tag — used by callers that need
 *                        to add an id (for HTML5 form= bindings to
 *                        external <form> elements) or data attributes.
 *
 * The mockup pairs every <table class="cms-table"> with a wrapping
 * <div class="table-card"> for the card border + radius. This partial
 * always renders that wrapper, so callers compose by simply varying
 * $columns / $rows.
 */

$columns     = (array)($columns ?? []);
$rows        = (array)($rows ?? []);
$empty_text  = (string)($empty_text ?? 'No entries yet.');
$variant     = (string)($variant ?? '');
$table_attrs = (string)($table_attrs ?? '');
$colCount    = max(count($columns), 1);

$variantSafe = $variant !== '' ? preg_replace('/[^a-z0-9_-]/i', '', $variant) : '';
$cardClass   = 'table-card' . ($variantSafe !== '' ? ' table-card--' . $variantSafe : '');
$tableClass  = 'cms-table'  . ($variantSafe !== '' ? ' cms-table--'  . $variantSafe : '');
?>
<div class="<?= $cardClass ?>">
  <table class="<?= $tableClass ?>"<?= $table_attrs !== '' ? ' ' . $table_attrs : '' ?>>
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
        <?php foreach ($rows as $row):
            // A row may be either a flat list of cells (back-compat) or
            // an object { cells: [...], href: "/path" } to make the whole
            // <tr> clickable. The href is propagated via data-row-href;
            // a small JS hook downstream listens for clicks anywhere on
            // the row except inside .cell-actions (Edit/Delete buttons).
            if (isset($row['cells']) && is_array($row['cells'])) {
                $cells   = $row['cells'];
                $rowHref = (string)($row['href'] ?? '');
            } else {
                $cells   = (array)$row;
                $rowHref = '';
            }
            $trAttr = $rowHref !== ''
                ? ' class="row-clickable" data-row-href="' . htmlspecialchars($rowHref, ENT_QUOTES, 'UTF-8') . '"'
                : '';
        ?>
          <tr<?= $trAttr ?>>
            <?php foreach ($cells as $cell):
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
