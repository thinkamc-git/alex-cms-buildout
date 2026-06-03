<?php
declare(strict_types=1);

/**
 * cms/views/pages-archive-preview.php — raw-HTML preview for archive mocks.
 *
 * Archives capture the page-as-it-was — typically a full self-contained
 * HTML document, with its own <head>, hardcoded nav, etc. Wrapping that
 * in the current page-shell would re-apply today's chrome and defeat
 * the point. This endpoint streams the body verbatim.
 *
 * Auth-gated. Refuses to render mocks that aren't archives (so it can't
 * be repurposed to dump arbitrary mock bodies). noindex headers either
 * way — archives must never be discoverable.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing or invalid id.";
    return;
}

$mock = get_page_mock($id);
if ($mock === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Archive not found.";
    return;
}

if (!is_archive_mock_name((string)$mock['name'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not an archive mock.";
    return;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex,nofollow');

echo (string)$mock['body_html'];
