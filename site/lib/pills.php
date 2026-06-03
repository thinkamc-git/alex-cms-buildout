<?php
declare(strict_types=1);

/**
 * lib/pills.php — small helpers that render the canonical .pill family used
 * across the CMS list views. Extracted Batch 2 #40 to remove the duplicated
 * $stagePill closure that lived in articles/journals/live-sessions/experiments.
 *
 * All helpers return safe HTML strings; callers should NOT escape the result.
 */

if (!function_exists('cms_pill_stage')) {
    /**
     * Stage pill. $status is one of the pipeline status slugs (idea / concept /
     * outline / draft / published / scheduled). Renders as a .pill .pill-{slug}.
     *
     * @param string $status status slug (case-insensitive).
     * @param string $size   '' default, 'small' for compact contexts (Series).
     */
    function cms_pill_stage(string $status, string $size = ''): string
    {
        $status = strtolower(trim($status));
        if ($status === '') { $status = 'idea'; }
        $label  = ucfirst($status);
        $extra  = $size === 'small' ? ' style="font-size:10px;padding:1px 5px"' : '';
        return '<span class="pill pill-' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '"'
             . $extra . '>'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
             . '</span>';
    }
}
