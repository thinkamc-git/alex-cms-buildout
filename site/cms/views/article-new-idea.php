<?php
/**
 * cms/views/article-new-idea.php — quick-capture POST handler.
 *
 * Both the Pipeline header bar and the Ideation view fire at this
 * endpoint. Creates an article at status='idea' with an auto-slugified
 * title; optional `notes` lands in `concept_text` so it surfaces when
 * the author advances to Concept.
 *
 * Returns the user to wherever they came from (Pipeline or Ideation)
 * via the `from` field. Anything unrecognized falls back to /cms.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$from = (string)($_POST['from'] ?? '');
$back = $from === 'ideation' ? '/cms/ideation' : '/cms';

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $back . '?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$title = trim((string)($_POST['title'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($title === '') {
    header('Location: ' . $back . '?flash=' . rawurlencode('Add a title before capturing.'));
    exit;
}

$slug = slugify($title);
if ($slug === '') {
    header('Location: ' . $back . '?flash=' . rawurlencode('Title needs at least one letter or number.'));
    exit;
}
$slug = unique_slug($slug);

$save = [
    'title'    => $title,
    'slug'     => $slug,
    'status'   => 'idea',
    'template' => 'article-standard',
];
if ($notes !== '') $save['concept_text'] = $notes;

save_article($save);

header('Location: ' . $back . '?flash=' . rawurlencode('Captured — ' . $title));
exit;
