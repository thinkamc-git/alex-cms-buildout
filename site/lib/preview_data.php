<?php
declare(strict_types=1);

/**
 * lib/preview_data.php — synthetic $ctx for CMS template previews.
 *
 * Mirrors the $ctx that render_content() builds from a DB row, but with
 * every field populated. Lets the Post Templates Preview tab render the
 * actual templates/<slug>.php against a fully-saturated context so EVERY
 * block exercises its rendering path — no DB row required.
 *
 * Each preview $ctx must satisfy the same shape render_content() emits:
 *   ['article' => <row-shaped array>, 'author' => author_display(...),
 *    'category' => [...] | null, 'series' => [...] | null]
 *
 * Used only from /cms/post-template/preview. Auth-gated upstream.
 */

require_once __DIR__ . '/author.php';

/**
 * Build a saturated $ctx for the given template slug.
 *
 * Recognised slugs: article-standard, article-series, journal-entry,
 * live-session, experiment, experiment-html. Unknown slug → null.
 */
function preview_ctx(string $tpl_slug): ?array
{
    $author = author_display(get_author());

    // Common scaffolding — every preview row shares these defaults.
    // Real columns from the `content` table (see 0001_initial_schema.sql
    // + 0018_body_mode.sql for body_mode).
    $base = [
        'id'                => 0,
        'slug'              => 'preview-' . $tpl_slug,
        'type'              => 'article',
        'status'            => 'published',
        'published_status'  => 'live',
        'template'          => $tpl_slug,
        'body_mode'         => 'rtf',
        'title'             => 'Sample Article Title — every block populated for preview',
        'key_statement'     => '',
        'summary'           => 'A short summary that explains the piece at a glance — one or two sentences that set up the reader for what comes next.',
        'body'              => preview_body_html(),
        'source_file'       => '',
        'thumbnail'         => '',
        'hero_image'        => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=1600&q=80',
        'hero_caption'      => 'A hero image caption — describes the photograph or illustration without repeating the title.',
        'hero_size'         => 'default',
        'event_start'       => null,
        'location'          => null,
        'cost_pill'         => null,
        'attendance'        => null,
        'custom_pill'       => null,
        'show_author'       => 1,
        'show_author_bio'   => 1,
        'special_tag'       => null,
        'series_id'         => null,
        'series_order'      => null,
        'journal_number'    => null,
        'read_time'         => 7,
        'tags'              => 'sample, preview, every-block, demo',
        'published_at'      => '2025-03-12 09:00:00',
        'updated_at'        => '2025-04-04 14:30:00',
        'created_at'        => '2025-03-10 11:00:00',
        'concept_text'      => '',
        'outline_text'      => '',
    ];

    $category = [
        'value_slug' => 'ux-industry',
        'label'      => 'UX Industry',
        'colour'     => 'terracotta',
    ];

    $series = null;

    switch ($tpl_slug) {
        case 'article-standard':
            $base['type']        = 'article';
            $base['template']    = 'article-standard';
            $base['body_mode']   = 'rtf';
            $base['special_tag'] = 'principle';
            break;

        case 'article-html-body':
            // Phase 20.3: article chrome + body sourced from an html file
            // in /content/article/<slug>/. Preview shows synthetic prose
            // since no real file exists; the body block falls through to
            // its empty-mode rendering (no body div).
            $base['type']      = 'article';
            $base['template']  = 'article-standard';
            $base['body_mode'] = 'html-body';
            $base['body']      = '';
            $base['source_file'] = 'main.html';
            break;

        case 'article-series':
            $base['type']         = 'article';
            $base['template']     = 'article-series';
            $base['body_mode']    = 'rtf';
            $base['series_id']    = 1;
            $base['series_order'] = 2;
            $series = [
                'id'        => 1,
                'name'      => 'Designing With Constraints',
                'slug'      => 'designing-with-constraints',
                '_count'    => 4,
                '_position' => 2,
            ];
            break;

        case 'journal-entry':
            $base['type']           = 'journal';
            $base['template']       = 'journal-entry';
            $base['body_mode']      = 'rtf';
            $base['title']          = '';
            $base['key_statement']  = 'The shortest path to clarity is to write the sentence you are afraid to commit to.';
            $base['journal_number'] = 42;
            $base['summary']        = '';
            $base['read_time']      = null;
            $category = [
                'value_slug' => 'introspection',
                'label'      => 'Introspection',
                'colour'     => 'purple',
            ];
            break;

        case 'live-session':
            $base['type']         = 'live-session';
            $base['template']     = 'live-session';
            $base['body_mode']    = 'rtf';
            $base['title']        = 'Designing in the Age of Abundance — a 90-minute talk';
            $base['event_start']  = '2025-06-18 18:30:00';
            $base['location']     = 'Toronto, ON — Studio 47';
            $base['cost_pill']    = 'Free';
            $base['attendance']   = 'in-person';
            $base['custom_pill']  = 'Limited 30';
            $category = [
                'value_slug' => 'talk',
                'label'      => 'Talk',
                'colour'     => 'amber',
            ];
            break;

        case 'experiment':
            $base['type']      = 'experiment';
            $base['template']  = 'experiment';
            $base['body_mode'] = 'rtf';
            $base['title']     = 'Design-Native Spec Workflow';
            $base['summary']   = 'A working prototype exploring how spec authoring can be co-located with design output.';
            $category = [
                'value_slug' => 'prototype',
                'label'      => 'Prototype',
                'colour'     => 'violet',
            ];
            break;

        case 'experiment-html-body':
            $base['type']        = 'experiment';
            $base['template']    = 'experiment';
            $base['body_mode']   = 'html-body';
            $base['title']       = 'Design-Native Spec Workflow';
            $base['summary']     = 'A working prototype exploring how spec authoring can be co-located with design output.';
            $base['body']        = '';
            $base['source_file'] = 'main.html';
            $category = [
                'value_slug' => 'prototype',
                'label'      => 'Prototype',
                'colour'     => 'violet',
            ];
            break;

        case 'experiment-html':
            // Legacy slug retained for the Post Templates view; renders as
            // the html-swap variant (full passthrough — no chrome). Without
            // a real file on disk the preview endpoint shows a placeholder.
            $base['type']        = 'experiment';
            $base['template']    = 'experiment';
            $base['body_mode']   = 'html-swap';
            $base['source_file'] = 'main.html';
            $category = [
                'value_slug' => 'prototype',
                'label'      => 'Prototype',
                'colour'     => 'violet',
            ];
            break;

        case 'master':
            // Master preview = article-standard with everything, including
            // a series tag, so the comprehensive showcase covers each block.
            $base['type']         = 'article';
            $base['template']     = 'article-standard';
            $base['body_mode']    = 'rtf';
            $base['special_tag']  = 'framework';
            $base['series_id']    = 1;
            $base['series_order'] = 2;
            $series = [
                'id'        => 1,
                'name'      => 'Designing With Constraints',
                'slug'      => 'designing-with-constraints',
                '_count'    => 4,
                '_position' => 2,
            ];
            break;

        default:
            return null;
    }

    return [
        'article'  => $base,
        'author'   => $author,
        'category' => $category,
        'series'   => $series,
    ];
}

/**
 * Sample article body — used to populate the Body block so the reading
 * pane has real prose, headings, a blockquote, and a list to verify
 * block-body's typography pass.
 */
function preview_body_html(): string
{
    return <<<HTML
<p>This is a representative article body, used by the Post Templates preview to exercise the Body block's typography rules. It contains the structural elements an editor is likely to use: prose paragraphs, headings, a blockquote, an ordered list, and a short code span.</p>

<h2>A second-level heading</h2>
<p>Paragraphs after H2 should retain their natural rhythm — the first paragraph after a heading does <em>not</em> get an extra dent, and the spacing comes from <code>style-articles.css</code> directly.</p>

<h3>A third-level heading</h3>
<p>H3 sits closer to its preceding paragraph and is used inside longer sections. The two heading levels together give the writer enough hierarchy without needing H4 in editorial work.</p>

<blockquote>
  <p>The best constraint is the one you can't see — it shapes the work but never asks for credit.</p>
</blockquote>

<ol>
  <li>Ordered lists keep the article's vertical rhythm.</li>
  <li>List items use a numbered marker styled to match the byline rules.</li>
  <li>Three items is usually enough to set up the pattern.</li>
</ol>

<p>And one more closing paragraph so the body has weight at the bottom — the Author Bio and Tags blocks sit beneath this, so the preview gives them something to sit against.</p>
HTML;
}
