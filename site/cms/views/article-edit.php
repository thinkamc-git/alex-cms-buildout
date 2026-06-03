<?php
/**
 * cms/views/article-edit.php — edit an existing Article at any pipeline stage.
 *
 * Routed from site/index.php:
 *   GET  /cms/articles/edit?id=N — render the form (stage-aware variant)
 *   POST /cms/articles/edit?id=N — save fields and/or transition stage
 *
 * Stage variants:
 *   - Idea  → minimal form (title + notes [stored as concept_text]).
 *   - Concept/Outline/Draft/Published → full editor inherited from Phase 6a.
 *
 * Actions (POST body `action`):
 *   - save        — write fields, keep stage.
 *   - advance     — write fields, transition to next stage.
 *   - step-back   — write fields, transition to previous stage.
 *   - publish     — alias of advance, used when stage = draft.
 *   - unpublish   — write fields, transition Published → Draft (UI requires
 *                   a JS confirm, server allows it unconditionally).
 *
 * Forward skipping is rejected by lib/content.php::transition_stage; the UI
 * already hides off-neighbor buttons, but the server is the source of truth.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/uploads.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/articles');
    exit;
}

$article = get_article($id);
if ($article === null) {
    // get_article() filters type='article'. Idea-stage rows may be untyped
    // (captured but not yet typed by a column-drag in Ideation), and other
    // types pass through this editor only while at Idea — beyond that
    // they route to their type-specific editor.
    $stmt = db()->prepare("SELECT * FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $article = $stmt->fetch() ?: null;
}
if ($article === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Article not found.\n";
    exit;
}

// Route non-article types past Idea to their dedicated editor. Idea stage
// stays here so the shared minimal editor handles all types uniformly.
$_routeStage = (string)($article['status'] ?? '');
$_routeType  = (string)($article['type']   ?? '');
if ($_routeStage !== 'idea' && $_routeType === 'journal') {
    header('Location: /cms/journals/edit?id=' . $id);
    exit;
}
if ($_routeStage !== 'idea' && $_routeType === 'live-session') {
    header('Location: /cms/live-sessions/edit?id=' . $id);
    exit;
}

$errors = [];
$flash  = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Series list for the sidebar picker (Phase 11). Loaded once here so
// both the POST validator and the render pass see the same list.
$allSeries = list_series();

// Article categories for the Primary category picker. Drives card colour
// on /writing/ — see views.css .card[data-category="…"] map.
$allArticleCategories = list_categories('article');
$currentPrimaryCategory = get_primary_category($id);

/**
 * `from_stage` suffix for the post-transition redirect. Only forward
 * transitions surface the Undo button; backward/move-to-draft don't.
 */
$undoSuffix = static function (string $action, string $current): string {
    return in_array($action, ['advance', 'publish'], true)
        ? '&from_stage=' . urlencode($current)
        : '';
};

/**
 * Map an action to a destination stage (or null = no transition). Returns
 * a tuple [stage|null, defaultFlashMessage]. Stages depend on the type
 * (journals skip Concept/Outline) so the destination of "advance" varies.
 */
$actionToStage = static function (string $action, string $current, ?string $type) {
    $stages = stages_for_type($type);
    $idx = array_search($current, $stages, true);
    switch ($action) {
        case 'advance':
            if ($idx === false || $idx >= count($stages) - 1) return [null, ''];
            $next = $stages[$idx + 1];
            return [$next, 'Advanced to ' . ucfirst($next) . '.'];
        case 'step-back':
            if ($idx === false || $idx <= 0) return [null, ''];
            $prev = $stages[$idx - 1];
            return [$prev, 'Stepped back to ' . ucfirst($prev) . '.'];
        case 'publish':
            return ['published', 'Published — live now.'];
        case 'unpublish':
            return ['draft', 'Moved back to draft — no longer publicly visible.'];
    }
    return [null, ''];
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action       = (string)($_POST['action'] ?? 'save');
        $currentStage = (string)($article['status'] ?? 'idea');

        // Phase 20.3: folder ops for body_mode='html-body'. Same shape as
        // experiment-edit's setup_folder/refresh_folder actions; uses the
        // article folder type so /content/article/<slug>/ gets created.
        if ($action === 'setup_folder' || $action === 'refresh_folder') {
            require_once __DIR__ . '/../../lib/folders.php';
            $aSlug = (string)($article['slug'] ?? '');
            if ($aSlug === '') {
                header('Location: /cms/articles/edit?id=' . $id
                    . '&flash=' . rawurlencode('Slug required before setting up a folder.'));
                exit;
            }
            // Persist the body_mode that came in with the POST so the post-
            // redirect render lands on the same panel the user clicked from.
            // Without this, an unsaved toggle to HTML File is lost and the
            // form snaps back to RTF after Setup/Refresh.
            $postedMode = (string)($_POST['body_mode'] ?? '');
            if (in_array($postedMode, ['rtf', 'html-body'], true)) {
                save_article(['id' => $id, 'body_mode' => $postedMode]);
            }
            if ($action === 'setup_folder') {
                $res = folder_setup('article', $aSlug);
                if ($res['ok']) {
                    $absPath = (string)($res['path'] ?? '');
                    $msg = ($res['created'] ?? false)
                        ? 'Folder created at: ' . $absPath
                        : 'Folder already exists at: ' . $absPath;
                } else {
                    $msg = (string)($res['error'] ?? 'Could not set up folder.');
                }
            } else {
                $msg = 'Refreshed.';
            }
            header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($msg));
            exit;
        }

        // Undo just-advanced — revert one stage without saving form. The
        // "Undo" button only renders while ?from_stage is in the URL, which
        // any save call drops, enforcing "once saved, no going back."
        if ($action === 'undo') {
            $idx = stage_index($currentStage);
            if ($idx > 0) {
                $prev = ARTICLE_STAGES[$idx - 1];
                $res  = transition_stage($id, $prev);
                if ($res['ok']) {
                    header('Location: /cms/articles/edit?id=' . $id
                        . '&flash=' . rawurlencode('Reverted to ' . ucfirst($prev) . '.'));
                    exit;
                }
            }
            header('Location: /cms/articles/edit?id=' . $id);
            exit;
        }

        if ($currentStage === 'idea') {
            // ── Idea-stage form: title + notes + type ───────────────────
            $titleIn = trim((string)($_POST['title'] ?? ''));
            $notesIn = trim((string)($_POST['notes'] ?? ''));
            $typeIn  = trim((string)($_POST['type']  ?? ''));

            if ($titleIn === '') {
                $errors[] = 'Title is required.';
            }

            $typeDb = ($typeIn === '' || $typeIn === 'none') ? null : $typeIn;
            if ($typeDb !== null && !in_array($typeDb, CONTENT_TYPES, true)) {
                $errors[] = 'Invalid type.';
                $typeDb = null;
            }

            // Re-derive slug from title if title changed; ideas are pre-publish
            // so the slug churning here is harmless and keeps URLs in sync.
            $slug = (string)($article['slug'] ?? '');
            $currentTitle = (string)($article['title'] ?? '');
            if ($titleIn !== '' && $titleIn !== $currentTitle) {
                $candidate = slugify($titleIn);
                if ($candidate !== '') $slug = unique_slug($candidate, $id);
            }

            if (count($errors) === 0) {
                $saveData = [
                    'id'    => $id,
                    'title' => $titleIn,
                    'slug'  => $slug,
                    'notes' => $notesIn !== '' ? $notesIn : null,
                    'type'  => $typeDb,
                ];
                $flashMsg = 'Saved.';

                // Idea-stage advance uses the type the user just picked
                // (which may have just changed from null).
                [$targetStage, $stageMsg] = $actionToStage($action, $currentStage, $typeDb);
                if ($targetStage !== null) {
                    save_article($saveData);
                    $res = transition_stage($id, $targetStage);
                    if (!$res['ok']) {
                        header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    // Per-type routing: rows past Idea live in their own
                    // editor (journal / live-session). Articles + untyped
                    // rows stay here.
                    if ($targetStage !== 'idea' && $typeDb === 'journal') {
                        $editPath = '/cms/journals/edit';
                    } elseif ($targetStage !== 'idea' && $typeDb === 'live-session') {
                        $editPath = '/cms/live-sessions/edit';
                    } else {
                        $editPath = '/cms/articles/edit';
                    }
                    header('Location: ' . $editPath . '?id=' . $id
                        . '&flash=' . rawurlencode($stageMsg)
                        . $undoSuffix($action, $currentStage));
                    exit;
                }

                save_article($saveData);
                header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
                exit;
            }

            $article = array_merge($article, [
                'title' => $titleIn,
                'slug'  => $slug,
                'notes' => $notesIn,
                'type'  => $typeDb,
            ]);
        } else {
            // ── Concept / Outline / Draft / Published — stage-aware ─────
            // Which fields are editable at this stage. Other fields are
            // either hidden or shown read-only; we only patch the editable
            // ones so saving at Concept doesn't blank out Body, etc.
            //
            // Notes are intentionally absent: they're frozen the moment the
            // row leaves Idea — see the Idea branch above for the only write.
            $editConcept  = $currentStage === 'concept';
            $editOutline  = $currentStage === 'outline' || $currentStage === 'draft';
            $editSummary  = $currentStage === 'draft' || $currentStage === 'published';
            $editBody     = $currentStage === 'draft' || $currentStage === 'published';
            $editHero     = $currentStage === 'draft' || $currentStage === 'published';
            $editReadTime = $currentStage === 'draft' || $currentStage === 'published';

            $post = [
                'title'         => trim((string)($_POST['title']         ?? '')),
                'slug'          => trim((string)($_POST['slug']          ?? '')),
                'concept_text'  => trim((string)($_POST['concept_text']  ?? '')),
                'outline_text'  => trim((string)($_POST['outline_text']  ?? '')),
                'summary'       => trim((string)($_POST['summary']       ?? '')),
                'body_raw'      =>      (string)($_POST['body']          ?? ''),
                'body_mode'     => trim((string)($_POST['body_mode']     ?? 'rtf')),
                'source_file'   => trim((string)($_POST['source_file']   ?? '')),
                'tags'          => trim((string)($_POST['tags']          ?? '')),
                'read_time'     => trim((string)($_POST['read_time']     ?? '')),
                'special_tag'   => trim((string)($_POST['special_tag']   ?? '')),
                'hero_caption'  => trim((string)($_POST['hero_caption']  ?? '')),
                'hero_size'     => trim((string)($_POST['hero_size']     ?? 'default')),
                // Hidden input flipped by the trash button — value '1' means remove.
                'remove_hero'   => (string)($_POST['remove_hero'] ?? '0') === '1',
                'series_id'     => trim((string)($_POST['series_id']     ?? '')),
                'primary_category' => trim((string)($_POST['primary_category'] ?? '')),
            ];

            // Validate primary category against the allowed article slugs.
            $allowedCatSlugs = array_map(static fn($c) => (string)$c['value_slug'], $allArticleCategories);
            if ($post['primary_category'] !== '' && !in_array($post['primary_category'], $allowedCatSlugs, true)) {
                $errors[] = 'Primary category is not a known article category.';
                $post['primary_category'] = '';
            }

            // Series — '' = none, otherwise an integer id. Validate against
            // the loaded list so a tampered POST can't write a stale id.
            // Part number is no longer editable here: series_order is
            // auto-assigned by compact_series_order() after save (see below).
            $seriesIdDb = null;
            if ($post['series_id'] !== '' && ctype_digit($post['series_id'])) {
                $sid = (int)$post['series_id'];
                foreach ($allSeries as $_s) {
                    if ((int)$_s['id'] === $sid) { $seriesIdDb = $sid; break; }
                }
                if ($seriesIdDb === null) {
                    $errors[] = 'Series no longer exists — pick another or set to None.';
                }
            }
            $prevSeriesId = (int)($article['series_id'] ?? 0);
            $seriesChanged = $seriesIdDb !== ($prevSeriesId > 0 ? $prevSeriesId : null);

            if ($post['title'] === '') {
                $errors[] = 'Title is required.';
            }

            $slug = $post['slug'] !== '' ? slugify($post['slug']) : slugify($post['title']);
            if ($slug === '') {
                $errors[] = 'Slug is required.';
            } else {
                $slug = unique_slug($slug, $id);
            }

            $specialTag = $post['special_tag'];
            if (!in_array($specialTag, ['', 'principle', 'framework'], true)) {
                $errors[] = 'Special tag must be empty, principle, or framework.';
                $specialTag = '';
            }
            $specialTagDb = $specialTag === '' ? null : $specialTag;

            $heroSize = in_array($post['hero_size'], ['default', 'wide', 'full'], true)
                ? $post['hero_size']
                : 'default';

            $readTime = null;
            if ($editReadTime && $post['read_time'] !== '') {
                if (!ctype_digit($post['read_time'])) {
                    $errors[] = 'Read time must be a whole number of minutes.';
                } else {
                    $readTime = (int)$post['read_time'];
                }
            }

            $bodyClean = $editBody ? sanitize_html($post['body_raw']) : (string)($article['body'] ?? '');

            $heroPath = (string)($article['hero_image'] ?? '');
            if ($editHero) {
                if ($post['remove_hero']) {
                    $heroPath = '';
                }
                if (isset($_FILES['hero']) && is_array($_FILES['hero']) && (int)$_FILES['hero']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $up = accept_upload($_FILES['hero'], 'content/article/' . $slug);
                    if (!$up['ok']) {
                        $errors[] = 'Hero image: ' . $up['error'];
                    } else {
                        $heroPath = $up['url'];
                    }
                }
            }

            if (count($errors) === 0) {
                // Build the patch with only the fields editable at this stage.
                // save_article() ignores absent keys, so untouched fields stay
                // in the row exactly as they were. (notes stays frozen — only
                // the Idea-stage handler ever writes it.)
                $saveData = [
                    'id'           => $id,
                    'template'     => 'article-standard',
                    'title'        => $post['title'],
                    'slug'         => $slug,
                    'special_tag'  => $specialTagDb,
                    'tags'         => $post['tags'] !== '' ? $post['tags'] : null,
                    'series_id'    => $seriesIdDb,
                ];
                // When the series changes, clear series_order so the new
                // series's compact pass appends this row at the end. When
                // the series clears entirely, also null the order.
                if ($seriesChanged) {
                    $saveData['series_order'] = null;
                }
                if ($editConcept) {
                    $saveData['concept_text'] = $post['concept_text'] !== '' ? $post['concept_text'] : null;
                }
                if ($editOutline) {
                    $saveData['outline_text'] = $post['outline_text'] !== '' ? $post['outline_text'] : null;
                }
                if ($editSummary) {
                    $saveData['summary'] = $post['summary'] !== '' ? $post['summary'] : null;
                }
                if ($editBody) {
                    // Phase 20.3: body always saved (preserve TipTap content
                    // across mode toggles). body_mode + source_file capture
                    // which source the public render pulls from. Only touch
                    // source_file when actively in html-body mode — that way
                    // a stale selector in a hidden panel can't overwrite a
                    // saved file reference.
                    $saveData['body'] = $bodyClean;
                    $mode = $post['body_mode'] === 'html-body' ? 'html-body' : 'rtf';
                    $saveData['body_mode'] = $mode;
                    if ($mode === 'html-body') {
                        $saveData['source_file'] = $post['source_file'] !== '' ? $post['source_file'] : null;
                    }
                }
                if ($editHero) {
                    $saveData['hero_image']   = $heroPath !== '' ? $heroPath : null;
                    $saveData['hero_caption'] = $post['hero_caption'] !== '' ? $post['hero_caption'] : null;
                    $saveData['hero_size']    = $heroSize;
                }
                if ($editReadTime) {
                    $saveData['read_time'] = $readTime;
                }

                // Phase 14.6 — published_at editable on live rows. Only honor
                // when the row is currently live (status='published' AND
                // published_status != 'scheduled') AND a published_at value
                // was actually posted. Convert from datetime-local Y-m-d\TH:i
                // to MySQL Y-m-d H:i:s.
                $rowIsCurrentlyLive = ((string)($article['status'] ?? '') === 'published')
                    && ((string)($article['published_status'] ?? '') !== 'scheduled');
                if ($rowIsCurrentlyLive && isset($_POST['published_at']) && trim((string)$_POST['published_at']) !== '') {
                    $rawPa = trim((string)$_POST['published_at']);
                    $tsPa  = strtotime($rawPa);
                    if ($tsPa !== false) {
                        $saveData['published_at'] = date('Y-m-d H:i:s', $tsPa);
                    }
                }

                // Phase 14.6 (followup 2) — show_updated checkbox +
                // updated_display override (date-only). Only honored on
                // live rows. If the submitted date matches the actual
                // updated_at date, store NULL (no override) so future
                // saves track updated_at naturally.
                if ($rowIsCurrentlyLive) {
                    $saveData['show_updated'] = isset($_POST['show_updated']) ? 1 : 0;
                    $udRaw = trim((string)($_POST['updated_display'] ?? ''));
                    if ($udRaw === '' || $udRaw === $updatedAtDateOnly) {
                        $saveData['updated_display'] = null;
                    } else {
                        $tsUd = strtotime($udRaw);
                        $saveData['updated_display'] = $tsUd === false
                            ? null
                            : date('Y-m-d 00:00:00', $tsUd);
                    }
                }

                $flashMsg = 'Saved.';
                $rowTypeForAction = (string)($article['type'] ?? '') !== '' ? (string)$article['type'] : null;

                // Phase 14.6 — publish-now branch. Promotes a scheduled row
                // to live immediately (published_status='live', published_at=NOW()).
                if ($action === 'publish-now') {
                    $stmt = db()->prepare("UPDATE content SET published_status='live', published_at=NOW() WHERE id = :id AND status='published'");
                    $stmt->execute([':id' => $id]);
                    header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode('Published — live now.'));
                    exit;
                }

                // Phase 14.6 — schedule branch. Saves form fields then calls
                // schedule_content(), which sets status='published' +
                // published_status='scheduled' + published_at=<future datetime>.
                // The Phase 13 cron sweeps these to 'live' when the time arrives.
                if ($action === 'schedule') {
                    $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));
                    if ($scheduleAt === '') {
                        $errors[] = 'A schedule date/time is required.';
                    } else {
                        save_article($saveData);
                        assign_primary_category($id, 'article', $post['primary_category']);
                        if ($seriesChanged) {
                            if ($prevSeriesId > 0)  compact_series_order($prevSeriesId);
                            if ($seriesIdDb !== null) compact_series_order($seriesIdDb);
                        }
                        $res = schedule_content($id, $scheduleAt);
                        if (!$res['ok']) {
                            header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                            exit;
                        }
                        $stamp = (string)($res['published_at'] ?? $scheduleAt);
                        $msg = 'Scheduled for ' . date('M j, Y · g:i A', strtotime($stamp));
                        header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($msg));
                        exit;
                    }
                }

                [$targetStage, $stageMsg] = $actionToStage($action, $currentStage, $rowTypeForAction);

                if ($targetStage !== null) {
                    save_article($saveData);
                    assign_primary_category($id, 'article', $post['primary_category']);
                    if ($seriesChanged) {
                        if ($prevSeriesId > 0)  compact_series_order($prevSeriesId);
                        if ($seriesIdDb !== null) compact_series_order($seriesIdDb);
                    }
                    $res = transition_stage($id, $targetStage);
                    if (!$res['ok']) {
                        header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    if ($targetStage === 'published') {
                        $stageMsg = 'Published — live at /writing/' . $slug;
                    }
                    header('Location: /cms/articles/edit?id=' . $id
                        . '&flash=' . rawurlencode($stageMsg)
                        . $undoSuffix($action, $currentStage));
                    exit;
                }

                save_article($saveData);
                assign_primary_category($id, 'article', $post['primary_category']);
                if ($seriesChanged) {
                    if ($prevSeriesId > 0)  compact_series_order($prevSeriesId);
                    if ($seriesIdDb !== null) compact_series_order($seriesIdDb);
                }
                header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
                exit;
            }

            $article = array_merge($article, [
                'title'        => $post['title'],
                'slug'         => $slug !== '' ? $slug : $post['slug'],
                'concept_text' => $editConcept ? $post['concept_text'] : ($article['concept_text'] ?? null),
                'outline_text' => $editOutline ? $post['outline_text'] : ($article['outline_text'] ?? null),
                'summary'      => $editSummary ? $post['summary']      : ($article['summary']      ?? null),
                'body'         => $bodyClean,
                'hero_image'   => $heroPath,
                'hero_caption' => $editHero ? $post['hero_caption'] : ($article['hero_caption'] ?? null),
                'hero_size'    => $heroSize,
                'special_tag'  => $specialTagDb,
                'tags'         => $post['tags'],
                'read_time'    => $editReadTime ? $readTime : ($article['read_time'] ?? null),
            ]);
        }
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status        = (string)($article['status'] ?? 'idea');
$rowType       = $article['type'] ?? null;
if (is_string($rowType) && $rowType === '') $rowType = null;
$myStages      = stages_for_type($rowType);
$statusIdx     = stage_index($status);                       // index in ARTICLE_STAGES (for ?: classes etc.)
$myStatusIdx   = array_search($status, $myStages, true);     // index in the type-specific list
if ($myStatusIdx === false) $myStatusIdx = -1;
$isIdea        = $status === 'idea';
$slugPublished = $status === 'published';
$bodyInitial   = (string)($article['body'] ?? '');

// Phase 14.6 — publish-state derivation. A row is "scheduled" when its
// pipeline stage has reached 'published' but published_status is
// 'scheduled' (the cron hasn't promoted it to 'live' yet). The Publish
// section in the right aside renders for Draft AND Scheduled — drafts
// pick between immediate/schedule, scheduled rows allow reschedule + unschedule.
$publishedStatus    = (string)($article['published_status'] ?? '');
$isScheduled        = ($status === 'published' && $publishedStatus === 'scheduled');
$isLive             = ($status === 'published' && $publishedStatus !== 'scheduled');
$showPublishSection = ($status === 'draft' || $isScheduled);
$publishedAtRaw     = (string)($article['published_at'] ?? '');
$scheduleAtForInput = $isScheduled && $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$minScheduleAt      = date('Y-m-d\TH:i', time() + 60);

// Phase 14.6 (followup) — Publish info box for live rows. Pre-fills the
// published_at edit input from the current DB value. Updated date is now
// explicit per-content (Phase 14.6 followup 2): show_updated boolean +
// optional updated_display override.
$publishedAtForInput = $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$updatedAtRaw       = (string)($article['updated_at'] ?? '');
$updatedAtFormatted = $updatedAtRaw !== ''
    ? date('M j, Y · g:i A', strtotime($updatedAtRaw))
    : '—';
$updatedAtDateOnly  = $updatedAtRaw !== '' ? substr($updatedAtRaw, 0, 10) : '';
$showUpdated        = !empty($article['show_updated']);
$updatedDisplayRaw  = (string)($article['updated_display'] ?? '');
$updatedDisplayDateOnly = $updatedDisplayRaw !== '' ? substr($updatedDisplayRaw, 0, 10) : '';
$updatedHasOverride = $updatedDisplayDateOnly !== '' && $updatedDisplayDateOnly !== $updatedAtDateOnly;
$updatedInputValue  = $updatedHasOverride ? $updatedDisplayDateOnly : $updatedAtDateOnly;

$prevStage = $myStatusIdx > 0 ? $myStages[$myStatusIdx - 1] : null;
$nextStage = $myStatusIdx >= 0 && $myStatusIdx < count($myStages) - 1
    ? $myStages[$myStatusIdx + 1] : null;

// Stage-aware Save label. Published reads "Save changes" since it's a
// post-publish edit; every other stage names the stage you're saving in.
$saveLabel = $status === 'published'
    ? 'Save changes'
    : 'Save ' . ucfirst($status);

// Per-stage field visibility. These drive both the form render and the
// POST handler's $saveData patch — keep them in sync.
//
// Carry-forwards (read-only at the next stage, gone after):
//   - Idea Notes: written at Idea, shown read-only at Concept, then archived.
//   - Concept:    written at Concept, shown read-only at Outline, then archived.
//
// Outline is editable at both Outline and Draft; gone at Published.
// Hero and Read time only live at Draft and Published.
$showIdeaNotesReadOnly = $status === 'concept';
$showConceptInput      = $status === 'concept';
$showConceptReadOnly   = $status === 'outline';
$showOutlineInput      = $status === 'outline' || $status === 'draft';
$showSummary           = $status === 'draft' || $status === 'published';
$showBody              = $status === 'draft' || $status === 'published';
$showHero              = $status === 'draft' || $status === 'published';
$showReadTime          = $status === 'draft' || $status === 'published';

// Phase 20.2: Preview sub-tab is available once the post has body content
// to render (draft + published). At earlier stages the row lacks the
// fields the public template renders, so the preview would be empty.
$showPreviewTab = $showBody;
$activeTab      = (string)($_GET['tab'] ?? 'edit');
if (!in_array($activeTab, ['edit', 'preview'], true)) $activeTab = 'edit';
if ($activeTab === 'preview' && !$showPreviewTab) $activeTab = 'edit';

// Phase 20.3: body_mode + folder state for the RTF/HTML toggle.
$bodyMode       = (string)($article['body_mode'] ?? 'rtf');
if (!in_array($bodyMode, ['rtf', 'html-body'], true)) $bodyMode = 'rtf';
$sourceFileVal  = (string)($article['source_file'] ?? '');
$slugForFolder  = (string)($article['slug'] ?? '');
$articleFolderExists = false;
$articleFolderFiles  = [];
$articleFolderPath   = '';
if ($slugForFolder !== '') {
    require_once __DIR__ . '/../../lib/folders.php';
    $articleFolderPath   = '/content/article/' . $slugForFolder . '/';
    $articleFolderExists = folder_exists('article', $slugForFolder);
    if ($articleFolderExists) {
        $articleFolderFiles = folder_scan('article', $slugForFolder);
    }
}

// Undo-after-advance: shown only when the URL carries ?from_stage from
// a forward transition. The first save_article drops the query param,
// enforcing "once saved, no going back."
$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && stage_index($fromStage) >= 0 && $myStatusIdx > 0;
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Edit: <?= $e((string)($article['title'] ?? 'Untitled')) ?> — alexmchong.ca CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/_ds/css/tokens.css">
<link rel="stylesheet" href="/_ds/css/base.css">
<link rel="stylesheet" href="/_ds/css/typography.css">
<link rel="stylesheet" href="/_ds/css/shell.css">
<link rel="stylesheet" href="/_ds/css/components.css">
<link rel="stylesheet" href="/_ds/css/tables.css">
<link rel="stylesheet" href="/_ds/css/status.css">
<link rel="stylesheet" href="/_ds/css/views.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<?php if ($showBody): ?>
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
<!-- The editor's contenteditable carries class="article-prose" so the SAME
     stylesheet that styles the public post page also styles the editor.
     Loaded after tiptap.css so .article-prose rules win on cascade. -->
<link rel="stylesheet" href="/_templates/style-articles.css">
<?php endif; ?>
</head>
<body>

<?php
// Phase 21.x: resolve provenance BEFORE topbar renders so the breadcrumb
// reflects where the user actually came from (?from=ideation → "Ideation
// → Edit") rather than always saying "Articles". Same $fromKey drives the
// sidebar nav highlight + the back link further down.
$validFromKeys = ['ideation', 'draft-writing', 'articles', 'journals', 'live-sessions', 'experiments'];
$fromKey = (string)($_GET['from'] ?? '');
if (!in_array($fromKey, $validFromKeys, true)) {
    if ($status === 'idea') {
        $fromKey = 'ideation';
    } elseif ($status === 'published') {
        $fromKey = 'articles';
    } else {
        $fromKey = 'draft-writing';
    }
}
$navLabelMap = [
    'ideation'      => ['Ideation',      '/cms/ideation'],
    'draft-writing' => ['Draft Writing', '/cms/'],
    'articles'      => ['Articles',      '/cms/articles'],
    'journals'      => ['Journals',      '/cms/journals'],
    'live-sessions' => ['Live Sessions', '/cms/live-sessions'],
    'experiments'   => ['Experiments',   '/cms/experiments'],
];
[$_navLabel, $_navHref] = $navLabelMap[$fromKey] ?? ['Articles', '/cms/articles'];
$breadcrumb      = $_navLabel . ' → Edit';
$breadcrumb_href = $_navHref;
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  // $fromKey was resolved above (before the topbar) so the breadcrumb
  // + back link + sidebar highlight all share the same provenance.
  $active_nav_id = $fromKey;
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-article-edit">
      <?php
      $titleHdr = (string)($article['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $stageLabel = $isScheduled ? 'Scheduled for Publish' : ucfirst($status);
      $subtitle = 'Article · ' . $stageLabel . ' · saved ' . (string)($article['updated_at'] ?? '');

      // Flash + optional Undo render via the canonical flash-success banner
      // above the content area (proposal #4 + #29). Undo is piped through
      // $flash_extra as raw HTML alongside the escaped flash text.
      $flash_extra = '';
      if ($flash !== '' && $canUndo) {
          $flash_extra = ' <form method="post" action="/cms/articles/edit?id=' . (int)$id . '" class="flash-undo">'
                       . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                       . '<button type="submit" name="action" value="undo" formnovalidate class="btn-link"'
                       . ' title="Reverts the last advance. Unsaved changes at this stage are lost.">↶ Undo</button>'
                       . '</form>';
      }

      // Phase 20.3: Back link mirrors the resolved $fromKey above so
      // returning lands where you came from — Draft Writing for any row
      // clicked from /cms/, Articles for any row clicked from /cms/articles,
      // etc. Same map used in journal / live-session / experiment edit.
      $backMap = [
          'ideation'      => ['/cms/ideation',      '← Back to Ideation'],
          'draft-writing' => ['/cms/',              '← Back to Draft Writing'],
          'articles'      => ['/cms/articles',      '← Back to Articles'],
          'journals'      => ['/cms/journals',      '← Back to Journals'],
          'live-sessions' => ['/cms/live-sessions', '← Back to Live Sessions'],
          'experiments'   => ['/cms/experiments',   '← Back to Experiments'],
      ];
      [$backHref, $backLabel] = $backMap[$fromKey] ?? ['/cms/articles', '← Back to list'];
      $actions  = '<a href="' . $e($backHref) . '" class="btn-sec">' . $e($backLabel) . '</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($myStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)         $cls = ' done';
          elseif ($i === $myStatusIdx)   $cls = ' current';
          // Re-label the Published step as "Scheduled" while the row is
          // queued for a future publish — matches the list view's stage
          // pill and the schedule banner up top.
          $stepLabel = ($s === 'published' && $isScheduled) ? 'Scheduled' : ucfirst($s);
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= $e($stepLabel) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($showPreviewTab): ?>
        <div class="post-edit-tabs" role="tablist" aria-label="Article edit and preview">
          <a class="post-edit-tab<?= $activeTab === 'edit' ? ' active' : '' ?>"
             role="tab" data-tab-target="edit"
             aria-selected="<?= $activeTab === 'edit' ? 'true' : 'false' ?>"
             href="/cms/articles/edit?id=<?= (int)$id ?>&tab=edit">Edit</a>
          <a class="post-edit-tab<?= $activeTab === 'preview' ? ' active' : '' ?>"
             role="tab" data-tab-target="preview"
             aria-selected="<?= $activeTab === 'preview' ? 'true' : 'false' ?>"
             href="/cms/articles/edit?id=<?= (int)$id ?>&tab=preview">Preview</a>
        </div>

        <div class="post-preview-frame<?= $activeTab === 'preview' ? '' : ' is-hidden-tab' ?>" data-tab-panel="preview">
          <iframe
            name="post-preview-frame-<?= (int)$id ?>"
            src="/cms/post/preview?id=<?= (int)$id ?>"
            title="Preview — <?= $e($titleHdr) ?>"
            class="post-preview-iframe"
            loading="lazy"
            data-preview-iframe
            data-preview-endpoint="/cms/post/preview-form?id=<?= (int)$id ?>"></iframe>
        </div>
      <?php endif; ?>

      <div class="content-area<?= ($showPreviewTab && $activeTab === 'preview') ? ' is-hidden-tab' : '' ?>" data-tab-panel="edit">
        <?php
        require __DIR__ . '/../partials/flash.php';
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

      <?php if ($isIdea): ?>
        <form method="post"
              action="/cms/articles/edit?id=<?= (int)$id ?>"
              class="cms-form">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="info-box"><strong>Idea stage</strong> — capture the title and any early notes. Slug and full editor unlock once you advance to Concept.</div>

          <div class="field-group">
            <label class="field-label" for="article-title">Title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="article-title"
              name="title"
              value="<?= $e((string)($article['title'] ?? '')) ?>"
              maxlength="500"
              required>
          </div>

          <div class="field-group">
            <label class="field-label" for="article-notes">Idea Notes <span class="field-hint-inline">optional</span></label>
            <textarea
              class="field-input"
              id="article-notes"
              name="notes"
              rows="6"
              maxlength="5000"
              placeholder="Jot down what this idea is about, possible angles, references, anything you'd lose otherwise…"><?= $e((string)($article['notes'] ?? '')) ?></textarea>
            <p class="field-hint">Private scratchpad — viewable as reference at Concept, then archived. Never appears on the public site.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="article-type">Type</label>
            <?php
            $typeCur = (string)($article['type'] ?? '');
            $typeOptions = [
                ''             => '— No type —',
                'article'      => 'Article',
                'journal'      => 'Journal',
                'live-session' => 'Live Session',
                'experiment'   => 'Experiment',
            ];
            ?>
            <select class="field-select" id="article-type" name="type" style="max-width:240px">
              <?php foreach ($typeOptions as $val => $lbl): ?>
                <option value="<?= $e((string)$val) ?>" <?= $typeCur === $val ? 'selected' : '' ?>><?= $e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field-hint">Required before you can advance.</p>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="<?= $e($backHref) ?>" class="btn-sec">Cancel</a>
            <button type="submit" form="article-delete-form" class="btn-sec btn-danger">Delete</button>
            <button type="submit" name="action" value="advance" class="btn-pri btn-actions-end" data-advance-button>
              Advance to <span data-advance-target><?= $e(ucfirst($nextStage ?? 'Concept')) ?></span> →
            </button>
          </div>
        </form>

        <script>
          // Type-aware advance label. Journals skip Concept/Outline, so the
          // Advance button's destination depends on whichever type is picked
          // in the dropdown at submit time. Update the label live as the
          // user changes the Type select so the button never lies.
          (function () {
            const typeSel = document.getElementById('article-type');
            const tgt     = document.querySelector('[data-advance-target]');
            if (!typeSel || !tgt) return;
            const journalLike = new Set(['journal', 'live-session', 'experiment']);
            function update() {
              tgt.textContent = journalLike.has(typeSel.value) ? 'Draft' : 'Concept';
            }
            typeSel.addEventListener('change', update);
            update();
          })();
        </script>

      <?php else: ?>
        <form method="post"
              action="/cms/articles/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide"
              data-preview-source-form
              enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <?php if ($isScheduled): ?>
            <?php
            $published_at_raw = $publishedAtRaw;
            require __DIR__ . '/../partials/schedule-banner.php';
            ?>
          <?php elseif ($isLive): ?>
            <?php
            $articleSlug      = (string)($article['slug'] ?? '');
            $published_at_raw = $publishedAtRaw;
            $live_url         = $articleSlug !== '' ? ('/writing/' . $articleSlug) : '';
            require __DIR__ . '/../partials/live-banner.php';
            ?>
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-main">
              <div class="field-group">
                <label class="field-label" for="article-title">Title <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input large"
                  id="article-title"
                  name="title"
                  value="<?= $e((string)($article['title'] ?? '')) ?>"
                  maxlength="500"
                  required>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-slug">Slug <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="article-slug"
                  name="slug"
                  value="<?= $e((string)($article['slug'] ?? '')) ?>"
                  maxlength="200"
                  pattern="[a-z0-9\-]+"
                  required>
                <p class="field-hint">
                  <?php if ($slugPublished): ?>
                    <strong>Warning:</strong> this article is published. Changing the slug
                    will create a 301 redirect from the old URL.
                  <?php else: ?>
                    Lowercase letters, numbers, hyphens. Becomes part of <code>/writing/&lt;slug&gt;</code>.
                  <?php endif; ?>
                </p>
              </div>

              <?php if ($showSummary): ?>
                <div class="field-group">
                  <label class="field-label" for="article-summary">Summary</label>
                  <textarea
                    id="article-summary"
                    class="field-input"
                    name="summary"
                    rows="3"
                    maxlength="500"
                    placeholder="One- to two-sentence summary for cards and meta description."><?= $e((string)($article['summary'] ?? '')) ?></textarea>
                </div>
              <?php endif; ?>

              <?php if ($showIdeaNotesReadOnly): ?>
                <div class="field-group">
                  <label class="field-label">Idea Notes</label>
                  <div class="readonly-block"><?= nl2br($e((string)($article['notes'] ?? '')), false) ?></div>
                </div>
              <?php endif; ?>

              <?php if ($showConceptReadOnly): ?>
                <div class="field-group">
                  <label class="field-label">Concept</label>
                  <div class="readonly-block"><?= nl2br($e((string)($article['concept_text'] ?? '')), false) ?></div>
                </div>
              <?php endif; ?>

              <?php if ($showConceptInput): ?>
                <div class="field-group">
                  <label class="field-label" for="article-concept">Concept</label>
                  <textarea
                    id="article-concept"
                    class="field-input"
                    name="concept_text"
                    rows="8"
                    maxlength="10000"
                    placeholder="What is this piece about? What's the angle? Write enough to know whether it's worth developing further."><?= $e((string)($article['concept_text'] ?? '')) ?></textarea>
                </div>
              <?php endif; ?>

              <?php if ($showOutlineInput): ?>
                <div class="field-group">
                  <label class="field-label" for="article-outline">Outline</label>
                  <textarea
                    id="article-outline"
                    class="field-input"
                    name="outline_text"
                    rows="10"
                    maxlength="20000"
                    placeholder="Structure the piece — section headers, key points, supporting examples."><?= $e((string)($article['outline_text'] ?? '')) ?></textarea>
                </div>
              <?php endif; ?>

              <?php if ($showBody): ?>
                <div class="field-group" data-body-source-block>
                  <div class="body-source-header">
                    <label class="field-label" style="margin:0">Body</label>
                    <div class="body-source-toggle" role="radiogroup" aria-label="Body source">
                      <label class="body-source-option<?= $bodyMode === 'rtf' ? ' is-active' : '' ?>">
                        <input type="radio" name="body_mode" value="rtf"<?= $bodyMode === 'rtf' ? ' checked' : '' ?>>
                        <span>Rich text</span>
                      </label>
                      <label class="body-source-option<?= $bodyMode === 'html-body' ? ' is-active' : '' ?>">
                        <input type="radio" name="body_mode" value="html-body"<?= $bodyMode === 'html-body' ? ' checked' : '' ?>>
                        <span>HTML file</span>
                      </label>
                    </div>
                  </div>

                  <!-- RTF panel — visible when body_mode='rtf' -->
                  <div class="body-source-panel" data-body-panel="rtf"<?= $bodyMode !== 'rtf' ? ' hidden' : '' ?>>
                    <div class="tiptap-wrap body-box">
                      <div class="tiptap-toolbar" id="tiptap-toolbar">
                        <button type="button" data-cmd="bold"        title="Bold"            class="tt-btn"><strong>B</strong></button>
                        <button type="button" data-cmd="italic"      title="Italic"          class="tt-btn"><em>I</em></button>
                        <button type="button" data-cmd="h2"          title="Heading 2"       class="tt-btn">H2</button>
                        <button type="button" data-cmd="h3"          title="Heading 3"       class="tt-btn">H3</button>
                        <button type="button" data-cmd="ul"          title="Bullet list"     class="tt-btn">• List</button>
                        <button type="button" data-cmd="ol"          title="Numbered list"   class="tt-btn">1. List</button>
                        <button type="button" data-cmd="link"        title="Link"            class="tt-btn">Link</button>
                        <button type="button" data-cmd="blockquote"  title="Blockquote"      class="tt-btn">“ Quote</button>
                        <button type="button" data-cmd="code"        title="Inline code"     class="tt-btn">Code</button>
                        <button type="button" data-cmd="muted"       title="Muted word (m)"  class="tt-btn">m</button>
                        <button type="button" data-cmd="image"       title="Insert image"    class="tt-btn">Image</button>
                      </div>
                      <div id="tiptap-editor" class="tiptap-editor"></div>
                      <textarea
                        id="article-body"
                        name="body"
                        rows="20"
                        class="tiptap-fallback"
                        aria-label="Article body (HTML)"><?= $e($bodyInitial) ?></textarea>
                    </div>
                    <p class="field-hint">
                      Any HTML outside the toolbar's allowlist is stripped on save.
                    </p>
                  </div>

                  <!-- HTML-file panel — visible when body_mode='html-body' -->
                  <div class="body-source-panel" data-body-panel="html-body"<?= $bodyMode !== 'html-body' ? ' hidden' : '' ?>>
                    <div class="folder-block">
                      <div class="folder-block-hd">
                        <div class="folder-path"><?= $e($articleFolderPath) ?></div>
                        <span class="folder-status">
                          <?php if (!$articleFolderExists): ?>
                            <span class="muted">Folder not set up yet</span>
                          <?php elseif (count($articleFolderFiles) === 0): ?>
                            <span class="muted">Folder is empty</span>
                          <?php else: ?>
                            <?= (int)count($articleFolderFiles) ?> file<?= count($articleFolderFiles) === 1 ? '' : 's' ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="folder-block-bd">
                        <?php if (!$articleFolderExists): ?>
                          <p class="field-hint">No folder exists yet for this slug. Click "Set up folder" to create
                            <code><?= $e($articleFolderPath) ?></code> on the server.
                            Drop your <code>.html</code> file into it via SSH or CloudMounter, then click Refresh.</p>
                          <button type="submit" name="action" value="setup_folder" class="btn-sec" formnovalidate>
                            Set up folder
                          </button>
                        <?php else: ?>
                          <div style="display:flex;gap:var(--space-8);align-items:center">
                            <?php if (count($articleFolderFiles) === 0): ?>
                              <select class="field-select" name="source_file" disabled style="flex:1">
                                <option>— no .html files in folder —</option>
                              </select>
                            <?php else: ?>
                              <select class="field-select" name="source_file" id="article-source-file" style="flex:1">
                                <option value="">— Pick a file —</option>
                                <?php foreach ($articleFolderFiles as $f): ?>
                                  <option value="<?= $e($f) ?>"<?= $sourceFileVal === $f ? ' selected' : '' ?>><?= $e($f) ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <button type="submit" name="action" value="refresh_folder" class="btn-sec" formnovalidate>↺ Refresh</button>
                          </div>
                          <p class="field-hint">
                            The article chrome (breadcrumb, title, byline, hero, tags) stays as edited above. Only the body
                            slot is replaced by the file. The file's HTML inherits the public
                            <code>.article-prose</code> typography rules.
                          </p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <aside class="form-side">
              <?php if ($showHero): ?>
                <?php
                  $hero            = (string)($article['hero_image'] ?? '');
                  $heroSizeCurrent = (string)($article['hero_size'] ?? 'default');
                  if (!in_array($heroSizeCurrent, ['default','wide','full'], true)) $heroSizeCurrent = 'default';
                  $heroSizeLabels  = ['default' => 'Column', 'wide' => 'Wide', 'full' => 'Full'];
                ?>
                <div class="cms-hero-box">
                  <div class="cms-hero-header">
                    <label class="field-label">Hero image</label>
                    <span class="field-hint-inline">optional</span>
                  </div>

                  <!-- Preview (or empty placeholder). Fills the box's full width. -->
                  <div class="cms-hero-preview<?= $hero !== '' ? ' is-loaded' : ' is-empty' ?>" data-hero-size="<?= $e($heroSizeCurrent) ?>">
                    <?php if ($hero !== ''): ?>
                      <img src="<?= $e($hero) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="cms-hero-empty">No image yet</div>
                    <?php endif; ?>
                    <!-- Trash overlay: top-right of the preview, only visible
                         when an image is loaded. Sets the hidden remove_hero
                         input on click and clears the preview. -->
                    <button type="button"
                            class="cms-hero-trash"
                            aria-label="Remove hero image"
                            title="Remove hero image">
                      <svg viewBox="0 0 16 16" aria-hidden="true" width="14" height="14"><path fill="currentColor" d="M6 2h4l1 1h3v2H2V3h3l1-1zm-3 4h10l-1 9H4L3 6zm3 2v6h1V8H6zm3 0v6h1V8H9z"/></svg>
                    </button>
                  </div>

                  <!-- Hidden remove flag — the trash overlay flips this to 1 -->
                  <input type="hidden" name="remove_hero" value="0" class="cms-hero-remove-flag">

                  <!-- File picker: native input hidden, label acts as the button. -->
                  <input type="file"
                         class="cms-hero-file sr-only"
                         id="article-hero-file"
                         name="hero"
                         accept="image/jpeg,image/png,image/webp,image/gif">
                  <div class="cms-hero-pick-row">
                    <label for="article-hero-file" class="btn-sec cms-hero-pick-btn">
                      <?= $hero !== '' ? 'Replace image' : 'Choose image' ?>
                    </label>
                    <span class="cms-hero-pick-name" aria-live="polite"></span>
                  </div>

                  <!-- Size toggle pills — mirrors the figure size toggle inside the RTF -->
                  <div class="cms-hero-controls">
                    <span class="cms-hero-controls-label">Size</span>
                    <div class="cms-hero-size-group" role="group" aria-label="Hero size">
                      <?php foreach (['default','wide','full'] as $sz): ?>
                        <button type="button"
                                class="cms-hero-size-btn<?= $heroSizeCurrent === $sz ? ' is-active' : '' ?>"
                                data-hero-size-btn="<?= $sz ?>"
                                aria-pressed="<?= $heroSizeCurrent === $sz ? 'true' : 'false' ?>"><?= $e($heroSizeLabels[$sz]) ?></button>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="hero_size" id="article-hero-size" value="<?= $e($heroSizeCurrent) ?>">
                  </div>

                  <!-- Caption -->
                  <input
                    type="text"
                    class="field-input cms-hero-caption"
                    id="article-hero-caption"
                    name="hero_caption"
                    placeholder="Caption (optional)"
                    value="<?= $e((string)($article['hero_caption'] ?? '')) ?>"
                    maxlength="500">

                  <p class="field-hint cms-hero-hint">JPEG, PNG, WebP, GIF · max 5 MB.</p>
                </div>
              <?php endif; ?>

              <div class="field-group">
                <label class="field-label" for="article-primary-category">Primary category</label>
                <select class="field-select" id="article-primary-category" name="primary_category">
                  <option value="">— None</option>
                  <?php foreach ($allArticleCategories as $cat): ?>
                    <option value="<?= $e((string)$cat['value_slug']) ?>" <?= $currentPrimaryCategory === (string)$cat['value_slug'] ? 'selected' : '' ?>><?= $e((string)$cat['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="field-hint">Drives card colour on /writing/ and the breadcrumb. Manage in Collections › Categories.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-special-tag">Special tag <span class="field-hint-inline">optional</span></label>
                <select class="field-select" id="article-special-tag" name="special_tag">
                  <?php
                  $stCurrent = (string)($article['special_tag'] ?? '');
                  $stOptions = ['' => '— None', 'principle' => 'Principle', 'framework' => 'Framework'];
                  foreach ($stOptions as $val => $lbl):
                  ?>
                    <option value="<?= $e((string)$val) ?>" <?= $stCurrent === $val ? 'selected' : '' ?>><?= $e($lbl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-series">Series <span class="field-hint-inline">optional</span></label>
                <?php $currentSeriesId = (int)($article['series_id'] ?? 0); ?>
                <select class="field-select" id="article-series" name="series_id">
                  <option value="">— None</option>
                  <?php foreach ($allSeries as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $currentSeriesId === (int)$s['id'] ? 'selected' : '' ?>>
                      <?= $e((string)$s['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="field-hint">
                  Manage the list in Collections › Series.
                </p>
              </div>

              <div class="field-group" id="series-order-group"<?= $currentSeriesId === 0 ? ' style="display:none"' : '' ?>>
                <label class="field-label">Part number <span class="field-hint-inline">in series</span></label>
                <div style="display:flex;align-items:center;gap:8px;color:var(--secondary);font-family:var(--font-mono);font-size:var(--text-meta)">
                  <?php $partNo = (int)($article['series_order'] ?? 0); ?>
                  <?php if ($partNo > 0): ?>
                    <span class="val-pill"><?= str_pad((string)$partNo, 2, '0', STR_PAD_LEFT) ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted);font-style:italic">unset (save once to assign)</span>
                  <?php endif; ?>
                </div>
                <p class="field-hint">Auto-assigned. Re-order parts in Collections › Series (drag handles).</p>
              </div>

              <script>
                (function () {
                  var sel = document.getElementById('article-series');
                  var grp = document.getElementById('series-order-group');
                  if (sel && grp) {
                    sel.addEventListener('change', function () {
                      grp.style.display = sel.value === '' ? 'none' : '';
                    });
                  }
                })();
              </script>

              <div class="field-group">
                <label class="field-label" for="article-tags">Tags <span class="field-hint-inline">optional</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="article-tags"
                  name="tags"
                  value="<?= $e((string)($article['tags'] ?? '')) ?>"
                  maxlength="500"
                  placeholder="comma, separated, list">
                <p class="field-hint">Display only — not used for filtering.</p>
              </div>

              <?php if ($showReadTime): ?>
                <div class="field-group">
                  <label class="field-label" for="article-read-time">
                    Read time <span class="field-hint-inline">minutes<?= $readTimeDisabled ? ' · set at Draft stage' : '' ?></span>
                  </label>
                  <input
                    type="number"
                    class="field-input"
                    id="article-read-time"
                    name="read_time"
                    value="<?= $e((string)($article['read_time'] ?? '')) ?>"
                    min="0"
                    max="120"
                    <?= $readTimeDisabled ? 'disabled' : '' ?>>
                  <p class="field-hint">
                    <span data-rt-result></span>
                    <button
                      type="button"
                      id="read-time-estimate"
                      style="background:transparent;border:0;padding:0;color:var(--stage-published);font:inherit;font-weight:600;text-decoration:underline;cursor:pointer;">↻ Get estimate</button>
                  </p>
                </div>
              <?php endif; ?>

              <?php
              // Publish-box: shared partial used by all 4 editors. Per-type
              // context is set here; the partial reads $is_live / $is_scheduled
              // / $show_publish_section to render the right variant(s).
              $is_live              = $isLive;
              $show_publish_section = $showPublishSection;
              $is_scheduled         = $isScheduled;
              $live_url             = '/writing/' . (string)($article['slug'] ?? '');
              $published_at_id      = 'article-published-at';
              $published_at_value   = $publishedAtForInput;
              $updated_label        = 'article';
              $show_updated         = $showUpdated;
              $updated_input_value  = $updatedInputValue;
              $updated_default      = $updatedAtDateOnly;
              $updated_has_override = $updatedHasOverride;
              $schedule_at_value    = $scheduleAtForInput;
              $min_schedule_at      = $minScheduleAt;
              require __DIR__ . '/../partials/publish-box.php';
              ?>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-sec" data-save-btn><?= $e($saveLabel) ?></button>
            <a href="<?= $e($backHref) ?>" class="btn-sec">Cancel</a>

            <button type="submit" form="article-delete-form" class="btn-sec btn-danger">Delete</button>

            <?php if ($nextStage !== null && $status !== 'draft' && $status !== 'published'): ?>
              <button type="submit" name="action" value="advance" class="btn-pri btn-actions-end">Advance to <?= $e(ucfirst($nextStage)) ?> →</button>
            <?php endif; ?>

            <?php if ($status === 'draft'): ?>
              <button type="submit" name="action" value="schedule" class="btn-sec btn-actions-end" data-set-schedule>Schedule Publish</button>
              <button type="submit" name="action" value="publish" class="btn-pri" data-publish-btn>Publish →</button>
            <?php endif; ?>

            <?php if ($isScheduled): ?>
              <button type="submit" name="action" value="publish-now" class="btn-pri btn-actions-end"
                      data-confirm="Publish this now? It will go live immediately at the current time.">Publish Now</button>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec"
                data-confirm-unpublish="1">Move Back To Draft</button>
            <?php endif; ?>

            <?php if ($isLive): ?>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec btn-actions-end"
                data-confirm-unpublish="1">Move Back To Draft</button>
            <?php endif; ?>
          </div>
        </form>
      <?php endif; ?>

        <form id="article-delete-form"
              method="post"
              action="/cms/articles/delete?id=<?= (int)$id ?>"
              class="inline-delete"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($article['slug'] ?? '')) ?>"
              hidden>
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <?php if ($slugPublished): ?>
            <input type="hidden" name="typed_slug" value="">
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>
</div>

<?php if ($showBody): ?>
<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('article-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/articles/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>
<?php if ($showHero): ?>
<script>
  // Phase 21.x — Hero box: pill toggle writes selected size into the hidden
  // input that the form posts, and live-previews a freshly-picked file before
  // upload so the author sees something in the preview pane immediately.
  (function () {
    const box = document.querySelector('.cms-hero-box');
    if (!box) return;

    const hidden      = box.querySelector('#article-hero-size');
    const buttons     = box.querySelectorAll('[data-hero-size-btn]');
    const preview     = box.querySelector('.cms-hero-preview');
    const fileInput   = box.querySelector('.cms-hero-file');
    const nameEl      = box.querySelector('.cms-hero-pick-name');
    const pickBtn     = box.querySelector('.cms-hero-pick-btn');
    const removeFlag  = box.querySelector('.cms-hero-remove-flag');
    const trash       = box.querySelector('.cms-hero-trash');

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const size = btn.getAttribute('data-hero-size-btn');
        if (!size) return;
        if (hidden) hidden.value = size;
        if (preview) preview.setAttribute('data-hero-size', size);
        buttons.forEach(b => {
          const match = b === btn;
          b.classList.toggle('is-active', match);
          b.setAttribute('aria-pressed', match ? 'true' : 'false');
        });
        // Mirror an input event so preview-tab-guard's dirty-tracker fires.
        if (hidden) hidden.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    if (fileInput && preview) {
      fileInput.addEventListener('change', () => {
        const f = fileInput.files && fileInput.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        const empty = preview.querySelector('.cms-hero-empty');
        if (empty) empty.remove();
        let img = preview.querySelector('img');
        if (!img) {
          img = document.createElement('img');
          img.alt = '';
          preview.appendChild(img);
        }
        img.src = url;
        preview.classList.remove('is-empty');
        preview.classList.add('is-loaded');
        // Replacing an image cancels any pending "remove" request.
        if (removeFlag) removeFlag.value = '0';
        if (nameEl)  nameEl.textContent = f.name;
        if (pickBtn) pickBtn.textContent = 'Replace image';
      });
    }

    // Trash overlay → flips the hidden remove_hero flag + clears the preview.
    // Confirm before destroying — the actual delete happens on Save, but the
    // preview clears immediately so the confirm prevents accidental clicks.
    if (trash && preview) {
      trash.addEventListener('click', () => {
        if (!window.confirm('Remove this hero image? You\'ll need to Save to confirm.')) return;
        if (removeFlag) {
          removeFlag.value = '1';
          removeFlag.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const img = preview.querySelector('img');
        if (img) img.remove();
        preview.classList.remove('is-loaded');
        preview.classList.add('is-empty');
        if (!preview.querySelector('.cms-hero-empty')) {
          const empty = document.createElement('div');
          empty.className = 'cms-hero-empty';
          empty.textContent = 'No image yet';
          preview.appendChild(empty);
        }
        // Also clear any pending file pick — saving now means "remove the
        // existing hero", not "swap to a new one".
        if (fileInput) fileInput.value = '';
        if (pickBtn)   pickBtn.textContent = 'Choose image';
        if (nameEl)    nameEl.textContent  = '';
      });
    }
  })();
</script>
<?php endif; ?>

<script>
  // Phase 20.3 + Batch 2 #23: body-source toggle now lives in
  // cms/_assets/body-mode-toggle.js (auto-wires every [data-body-source-block]).
</script>
<script src="/cms/_assets/body-mode-toggle.js" defer></script>
<?php endif; ?>

<?php if ($showReadTime): ?>
<script>
  // "Get estimate" computes a read-time estimate from the current body and
  // writes it into the input. No auto-management — the field stays in the
  // user's control. 225 wpm, round up, min 1. Mirrors estimate_read_minutes()
  // in lib/content.php for the JS-disabled path.
  (function () {
    const input  = document.getElementById('article-read-time');
    const btn    = document.getElementById('read-time-estimate');
    const result = document.querySelector('[data-rt-result]');
    const body   = document.getElementById('article-body');
    if (!input || !btn || !body) return;

    function plainTextFromHtml(html) {
      const div = document.createElement('div');
      div.innerHTML = html || '';
      return div.textContent || '';
    }
    function wordCount(text) {
      const t = (text || '').replace(/\s+/g, ' ').trim();
      return t ? t.split(' ').filter(Boolean).length : 0;
    }
    btn.addEventListener('click', () => {
      const text  = plainTextFromHtml(body.value);
      const words = wordCount(text);
      if (words === 0) {
        result.textContent = 'No body content yet. ';
        btn.textContent = '↻ Get estimate';
        return;
      }
      const mins = Math.max(1, Math.ceil(words / 225));
      input.value = String(mins);
      result.textContent = 'Estimate: ' + mins + ' min from ' + words + ' words ';
      btn.textContent = '↻ Refresh';
    });
  })();
</script>
<?php endif; ?>

<script src="/cms/_assets/scroll-actions.js" defer></script>

<script>
  // Move-to-draft (Published → Draft) needs explicit confirmation.
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this article back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }

  // Delete confirmation: typed-slug for Published, simple OK otherwise.
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published article is permanent.\n\n' +
          'Type the slug exactly to confirm:\n\n  ' + slug
        );
        if (typed === null) { e.preventDefault(); return; }
        if (typed.trim() !== slug) {
          e.preventDefault();
          window.alert('Slug did not match — nothing deleted.');
          return;
        }
        const inp = form.querySelector('input[name="typed_slug"]');
        if (inp) inp.value = typed.trim();
      } else {
        if (!window.confirm('Delete this article? This cannot be undone.')) {
          e.preventDefault();
        }
      }
    });
  }
</script>

<script src="/cms/_assets/publish-choreography.js" defer></script>
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
</body>
</html>
