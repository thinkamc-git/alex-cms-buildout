<?php
/**
 * cms/views/experiment-delete.php — hard-delete an experiment (Phase 10).
 *
 * Per the Custom HTML Folder System spec (CMS-STRUCTURE.md §12), folder
 * deletion requires the folder to be empty. We do NOT auto-rm files —
 * deleting an experiment whose folder still has content surfaces a
 * "delete the files first" message and leaves the DB row in place. The
 * author resolves it manually via SSH, then re-runs delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/folders.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /cms/experiments?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/experiments');
    exit;
}

$experiment = get_experiment($id);
if ($experiment === null) {
    header('Location: /cms/experiments?flash=' . rawurlencode('Already gone.'));
    exit;
}

$status   = (string)($experiment['status']   ?? '');
$slug     = (string)($experiment['slug']     ?? '');
$template = (string)($experiment['template'] ?? '');
$bodyMode = (string)($experiment['body_mode'] ?? 'rtf');

if ($status === 'published') {
    $typed = trim((string)($_POST['typed_slug'] ?? ''));
    if ($typed === '' || $typed !== $slug) {
        header('Location: /cms/experiments/edit?id=' . (int)$id
            . '&flash=' . rawurlencode('Slug confirmation did not match — nothing deleted.'));
        exit;
    }
}

// Phase 20.3: if this experiment uses a file-backed body mode (html-body
// or html-swap), try to remove the content folder. Empty folder → quietly
// removed. Non-empty → bail and ask the author to clean up first.
$hadFolder = in_array($bodyMode, ['html-body', 'html-swap'], true);
if ($hadFolder && $slug !== '' && folder_exists('experiment', $slug)) {
    $res = folder_delete('experiment', $slug);
    if (!$res['ok']) {
        header('Location: /cms/experiments/edit?id=' . (int)$id
            . '&flash=' . rawurlencode('Cannot delete — folder is not empty. Remove its files via SSH first, then delete.'));
        exit;
    }
}

delete_experiment($id);

header('Location: /cms/experiments?flash=' . rawurlencode('Experiment deleted.'));
exit;
