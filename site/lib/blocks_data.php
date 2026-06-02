<?php
/**
 * lib/blocks_data.php — Hardcoded block reference, sourced from docs/BLOCKS.md.
 *
 * Phase 14.5 ships the Content Template view in read-only mode. This file is
 * the data layer for that view: every block, every field, every sub-template,
 * and the content-type matrix that maps which blocks each sub-template uses.
 *
 * IMPORTANT: docs/BLOCKS.md is the canonical contract. If a block is added,
 * renamed, or moved between modes there, update this file to match. The
 * Content Template view reads only from here — it does not parse BLOCKS.md
 * at runtime (for stability and perf).
 *
 * Modes used in the matrix:
 *   always       — block always renders for this sub-template
 *   optional     — author toggles via data presence (or show_author / show_author_bio)
 *   auto         — auto-conditional; renders based on data state
 *   required     — data field required to save; block always shown when populated
 *   —            — block not applicable to this sub-template
 */

declare(strict_types=1);

/**
 * Every block in the system. Keyed by slug.
 * Sourced from BLOCKS.md §5.
 */
function blocks_reference(): array
{
    return [
        'category' => [
            'name'        => 'Category',
            'composition' => "\$article['category'] (primary, joined from content_categories)",
            'purpose'     => 'Coloured pill in the meta line. Drives --c-current for category-tinted elements.',
        ],
        'publish-date' => [
            'name'        => 'Publish Date',
            'composition' => "\$article['published_at']",
            'purpose'     => 'Formatted in the meta line.',
        ],
        'read-time' => [
            'name'        => 'Read Time',
            'composition' => "\$article['read_time']",
            'purpose'     => 'Auto-calculated at 200 wpm; manual in experiment-html. Hidden in journal.',
        ],
        'updated-date' => [
            'name'        => 'Updated Date',
            'composition' => "\$article['updated_at']",
            'purpose'     => 'Renders only when updated_at differs from published_at by more than 24h.',
        ],
        'title' => [
            'name'        => 'Title',
            'composition' => "\$article['title']",
            'purpose'     => 'Hidden in journal (replaced by Key Statement). Allows inline HTML.',
        ],
        'key-statement' => [
            'name'        => 'Key Statement',
            'composition' => "\$article['key_statement']",
            'purpose'     => 'Replaces Title in journal. Instrument Serif italic, left rule in category colour.',
        ],
        'summary' => [
            'name'        => 'Summary',
            'composition' => "\$article['summary']",
            'purpose'     => 'Single-line deck below the title. Reused for card excerpts and meta description.',
        ],
        'author' => [
            'name'        => 'Author',
            'composition' => "\$author['image'], \$author['name'], \$author['short_description']",
            'purpose'     => 'Byline next to title. Three of the four Author config fields. Configure in the Author tab.',
        ],
        'author-bio' => [
            'name'        => 'Author Bio',
            'composition' => "\$author['image'], \$author['extended_description']",
            'purpose'     => 'Footer "About the author" panel. Independently toggleable from the Author byline.',
        ],
        'series' => [
            'name'        => 'Series',
            'composition' => "\$article['series'], \$article['series_order']",
            'purpose'     => 'Pill + "Part N of M" + progress dots. Required in article-series. Articles only.',
        ],
        'special-tag' => [
            'name'        => 'Special Tag',
            'composition' => "\$article['special_tag'] (principle / framework)",
            'purpose'     => 'Articles only. Small pill with category-tinted border.',
        ],
        'hero-image' => [
            'name'        => 'Hero Image',
            'composition' => "\$article['hero_image'], \$article['hero_caption'], \$article['hero_size']",
            'purpose'     => 'Between header and body. Sizes: default / wide (~1080px) / full (100vw).',
        ],
        'body' => [
            'name'        => 'Body',
            'composition' => "\$article['body'] (Tiptap rich-text HTML)",
            'purpose'     => 'Rendered inside .article-prose. Hidden in experiment-html (replaced by Custom HTML).',
        ],
        'custom-html' => [
            'name'        => 'Custom HTML',
            'composition' => "\$article['source_file']",
            'purpose'     => 'Replaces Body in experiment-html. Production rendered raw via PHP readfile().',
        ],
        'series-nav' => [
            'name'        => 'Series Navigation',
            'composition' => 'derived: prev/next where series_id matches and series_order is ±1',
            'purpose'     => 'Renders only when article is in a series and the series template is active.',
        ],
        'event-details' => [
            'name'        => 'Event Details',
            'composition' => "\$article['event_start'] (DATETIME), \$article['location']",
            'purpose'     => 'When/Where panel. Live sessions only.',
        ],
        'format-tags' => [
            'name'        => 'Format Tags',
            'composition' => "\$article['cost_pill'], \$article['attendance'], \$article['custom_pill']",
            'purpose'     => 'Three independent pills. Each NULL hides its own pill. Live sessions only.',
        ],
        'entry-number' => [
            'name'        => 'Entry Number',
            'composition' => "\$article['journal_number']",
            'purpose'     => 'Auto-incremented per category on publish. Journals only. Renders as "Entry 14".',
        ],
        'tags' => [
            'name'        => 'Tags',
            'composition' => "\$article['tags'] (comma-separated)",
            'purpose'     => 'Renders only if any tags are present. Display only — not used for filtering.',
        ],
    ];
}

/**
 * Every data field underlying the blocks. Keyed by field name.
 * Sourced from BLOCKS.md §5 and §7a.
 */
function fields_reference(): array
{
    return [
        // Author config (single-row author table)
        'author.image' => [
            'php'         => "\$author['image']",
            'description' => 'Author profile photo. Used by Author byline and Author Bio.',
        ],
        'author.name' => [
            'php'         => "\$author['name']",
            'description' => 'Author display name. Used by Author byline.',
        ],
        'author.short_description' => [
            'php'         => "\$author['short_description']",
            'description' => 'Author short bio. Renders as "Name – Short description" in the byline.',
        ],
        'author.extended_description' => [
            'php'         => "\$author['extended_description']",
            'description' => 'Author long bio. Renders in the Author Bio block (footer).',
        ],

        // Content fields (on the content row)
        'title' => [
            'php'         => "\$article['title']",
            'description' => 'Article / live-session / experiment title. Allows inline HTML.',
        ],
        'key_statement' => [
            'php'         => "\$article['key_statement']",
            'description' => 'Single declarative sentence. Replaces Title in journals.',
        ],
        'summary' => [
            'php'         => "\$article['summary']",
            'description' => 'One-line deck. Reused for card excerpts and meta description.',
        ],
        'category' => [
            'php'         => "\$article['category']",
            'description' => 'Primary category (joined from content_categories).',
        ],
        'published_at' => [
            'php'         => "\$article['published_at']",
            'description' => 'Publish timestamp. Drives Publish Date block.',
        ],
        'updated_at' => [
            'php'         => "\$article['updated_at']",
            'description' => 'Last-edited timestamp. Drives Updated Date block (auto-conditional).',
        ],
        'read_time' => [
            'php'         => "\$article['read_time']",
            'description' => 'Auto-calculated at 200 wpm. Manual override on experiment-html.',
        ],
        'special_tag' => [
            'php'         => "\$article['special_tag']",
            'description' => 'NULL / "principle" / "framework". Articles only.',
        ],
        'series_id' => [
            'php'         => "\$article['series']",
            'description' => 'Series this article belongs to (joined from series). Required for article-series.',
        ],
        'series_order' => [
            'php'         => "\$article['series_order']",
            'description' => 'Position within the series. Drives Part N of M and prev/next.',
        ],
        'hero_image' => [
            'php'         => "\$article['hero_image']",
            'description' => 'Hero image URL.',
        ],
        'hero_caption' => [
            'php'         => "\$article['hero_caption']",
            'description' => 'Hero image caption.',
        ],
        'hero_size' => [
            'php'         => "\$article['hero_size']",
            'description' => 'ENUM: default / wide / full.',
        ],
        'body' => [
            'php'         => "\$article['body']",
            'description' => 'Tiptap rich-text HTML. Used by Body block.',
        ],
        'source_file' => [
            'php'         => "\$article['source_file']",
            'description' => 'Filename for experiment-html. Path derived from type and slug.',
        ],
        'event_start' => [
            'php'         => "\$article['event_start']",
            'description' => 'DATETIME. Live sessions only.',
        ],
        'event_date' => [
            'php'         => "\$article['event_date']",
            'description' => 'Date portion of the event. Phase 9 split.',
        ],
        'event_time' => [
            'php'         => "\$article['event_time']",
            'description' => 'Time portion of the event. Phase 9 split.',
        ],
        'event_end_time' => [
            'php'         => "\$article['event_end_time']",
            'description' => 'Optional end time. Phase 9 split.',
        ],
        'location' => [
            'php'         => "\$article['location']",
            'description' => 'Venue / address. Live sessions only.',
        ],
        'cost_pill' => [
            'php'         => "\$article['cost_pill']",
            'description' => 'Free / Fee / custom. NULL hides the pill.',
        ],
        'attendance' => [
            'php'         => "\$article['attendance']",
            'description' => 'in-person / remote. NULL hides the pill.',
        ],
        'custom_pill' => [
            'php'         => "\$article['custom_pill']",
            'description' => 'Short custom string. NULL hides the pill.',
        ],
        'journal_number' => [
            'php'         => "\$article['journal_number']",
            'description' => 'Auto-assigned at publish per category. Journals only.',
        ],
        'tags' => [
            'php'         => "\$article['tags']",
            'description' => 'Comma-separated. Display only.',
        ],
        'show_author' => [
            'php'         => "\$article['show_author']",
            'description' => 'BOOLEAN. Per-content toggle for the Author byline.',
        ],
        'show_author_bio' => [
            'php'         => "\$article['show_author_bio']",
            'description' => 'BOOLEAN. Per-content toggle for the Author Bio footer.',
        ],
    ];
}

/**
 * The 6 sub-templates. Keyed by slug.
 * Sourced from BLOCKS.md §6 and the cms-ui.html mockup.
 */
function sub_templates_reference(): array
{
    // Phase 20.3: sub-templates are now (chrome × body_mode) combinations,
    // not standalone template enum values. The keys here are display slugs
    // used by /cms/post-template — the data model behind them is
    // (template, body_mode) per the migration in 0018_body_mode.sql.
    return [
        'article-standard' => [
            'name'     => 'article: standard',
            'desc'     => 'Default long-form article. Body is a Tiptap rich text field.',
            'php_file' => 'article-standard.php',
        ],
        'article-html-body' => [
            'name'     => 'article: html body',
            'desc'     => 'Article chrome (breadcrumb, byline, hero, etc.) with the body slot sourced from an HTML file in /content/article/<slug>/. Same chrome as standard — only the body block differs.',
            'php_file' => 'article-standard.php',
        ],
        'article-series' => [
            'name'     => 'article: series',
            'desc'     => 'Articles that belong to a series. Series field required; prev/next nav auto-renders.',
            'php_file' => 'article-standard.php',
        ],
        'journal-entry' => [
            'name'     => 'journal entry',
            'desc'     => 'Short reflective entries. Key Statement replaces Title; Entry Number auto-assigned.',
            'php_file' => 'journal-entry.php',
        ],
        'live-session' => [
            'name'     => 'live session',
            'desc'     => 'Talks, workshops, masterclasses. Includes Event Details and Format Tags.',
            'php_file' => 'live-session.php',
        ],
        'experiment' => [
            'name'     => 'experiment: rich text',
            'desc'     => 'Article-format experiment with the rich text body editor.',
            'php_file' => 'experiment.php',
        ],
        'experiment-html-body' => [
            'name'     => 'experiment: html body',
            'desc'     => 'Experiment chrome with the body slot sourced from an HTML file in /content/experiment/<slug>/. The chrome stays the same as the rich-text experiment; only the body block reads from disk.',
            'php_file' => 'experiment.php',
        ],
        'experiment-html' => [
            'name'     => 'experiment: html swap',
            'desc'     => 'Full-page HTML passthrough. readfile() serves the file directly with no template wrapper. Use for prototypes that need their own <head>, scripts, custom fonts, etc.',
            'php_file' => 'experiment-html.php',
        ],
    ];
}

/**
 * Which blocks each sub-template uses, and in what mode.
 * Sourced from BLOCKS.md §6.
 *
 * Modes:
 *   always   — always renders when applicable
 *   optional — author toggles via data presence (or per-content boolean for Author/Author Bio)
 *   auto     — auto-conditional; renders based on data state
 *   required — data field required to save; always shown when populated
 *   —        — not applicable to this sub-template
 */
function content_type_matrix(): array
{
    return [
        'article-standard' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => 'optional',
            'special-tag'    => 'optional',
            'hero-image'     => 'optional',
            'body'           => 'always',
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        'article-series' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => 'required',
            'special-tag'    => 'optional',
            'hero-image'     => 'optional',
            'body'           => 'always',
            'custom-html'    => '—',
            'series-nav'     => 'auto',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        'journal-entry' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => '—',
            'updated-date'   => 'auto',
            'title'          => '—',
            'key-statement'  => 'always',
            'summary'        => '—',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => '—',
            'special-tag'    => '—',
            'hero-image'     => '—',
            'body'           => 'always',
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => 'auto',
            'tags'           => 'auto',
        ],
        'live-session' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => '—',
            'special-tag'    => '—',
            'hero-image'     => 'optional',
            'body'           => 'always',
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => 'always',
            'format-tags'    => 'always',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        'experiment' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => '—',
            'special-tag'    => '—',
            'hero-image'     => 'optional',
            'body'           => 'always',
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        'experiment-html' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',  // manual on experiment-html (no body to count)
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => '—',
            'special-tag'    => '—',
            'hero-image'     => '—',
            'body'           => '—',
            'custom-html'    => 'always',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        // Phase 20.3: article-html-body shares article-standard's chrome —
        // every block stays except Body switches from rich-text (always) to
        // file-sourced (still always-rendered, but read from /content/article).
        'article-html-body' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',  // manual on html-body (no body to count)
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => 'optional',
            'special-tag'    => 'optional',
            'hero-image'     => 'optional',
            'body'           => 'always',  // sourced from file, not RTF
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
        // Phase 20.3: experiment-html-body shares experiment's chrome.
        'experiment-html-body' => [
            'category'       => 'always',
            'publish-date'   => 'always',
            'read-time'      => 'optional',
            'updated-date'   => 'auto',
            'title'          => 'always',
            'key-statement'  => '—',
            'summary'        => 'optional',
            'author'         => 'optional',
            'author-bio'     => 'optional',
            'series'         => '—',
            'special-tag'    => '—',
            'hero-image'     => 'optional',
            'body'           => 'always',  // sourced from file
            'custom-html'    => '—',
            'series-nav'     => '—',
            'event-details'  => '—',
            'format-tags'    => '—',
            'entry-number'   => '—',
            'tags'           => 'auto',
        ],
    ];
}

/**
 * Per-mode notes for the visibility table.
 */
function block_mode_notes(): array
{
    return [
        'always'   => 'Renders whenever the content type includes it.',
        'optional' => 'Author toggles via data presence (or per-content boolean for Author / Author Bio).',
        'auto'     => 'Auto-conditional. Renders based on data state.',
        'required' => 'Data field required to save the content. Always shown when populated.',
    ];
}
