<?php
declare(strict_types=1);

/**
 * lib/uploads.php — single entry point for every file upload.
 *
 * Per ENGINEERING.md §10 and CMS-STRUCTURE.md §13: no upload bypasses
 * accept_upload(). The function validates mime, validates size, makes
 * the destination folder, and moves the file. Callers receive the
 * public URL (suitable for a <img src=…>) or an error string.
 *
 * Phase 6a Decisions:
 *   - max size 5 MB
 *   - allowed: image/jpeg, image/png, image/webp, image/gif
 *   - hero images live at /uploads/content/article/{slug}/
 *   - inline-body images live at /uploads/content/article/{slug}/inline/
 *
 * The function never touches the database. Callers persist the URL on
 * their own (e.g. into content.hero_image, or into the Tiptap body).
 */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

const UPLOAD_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/**
 * Result shape: ['ok' => true,  'url' => '/uploads/...', 'path' => '/abs/...']
 *               ['ok' => false, 'error' => 'human-readable reason']
 *
 * $file is a single entry from $_FILES (e.g. $_FILES['hero']).
 * $subdir is appended to /uploads/ (e.g. 'content/article/my-slug').
 */
function accept_upload(array $file, string $subdir): array
{
    // PHP-level upload errors first.
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($err)];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Upload missing or invalid.'];
    }

    // Size check.
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'File too large (max 5 MB).'];
    }

    // Server-side mime check (don't trust $_FILES[type] — it's client-set).
    $mime = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    if (!is_string($mime) || !isset(UPLOAD_ALLOWED_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'Unsupported image type. Allowed: JPEG, PNG, WebP, GIF.'];
    }
    $ext = UPLOAD_ALLOWED_MIMES[$mime];

    // Sanitize sub-directory.
    $subdir = trim($subdir, '/');
    if ($subdir === '' || !preg_match('#^[a-z0-9/_-]+$#i', $subdir)) {
        return ['ok' => false, 'error' => 'Invalid upload directory.'];
    }

    // Public webroot is the parent of site/lib/ — i.e. site/.
    $webroot   = dirname(__DIR__);
    $targetDir = $webroot . '/uploads/' . $subdir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'error' => 'Could not create upload directory.'];
    }

    // Filename: keep the user-supplied stem if it's safe; otherwise generate.
    $original = (string)($file['name'] ?? '');
    $stem     = pathinfo($original, PATHINFO_FILENAME);
    $safeStem = upload_slugify($stem);
    if ($safeStem === '') {
        $safeStem = 'img-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    // Avoid collisions by appending a short hash if needed.
    $filename = $safeStem . '.' . $ext;
    if (file_exists($targetDir . '/' . $filename)) {
        $filename = $safeStem . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    }

    $destPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destPath)) {
        return ['ok' => false, 'error' => 'Could not save upload.'];
    }
    @chmod($destPath, 0664);

    return [
        'ok'   => true,
        'url'  => '/uploads/' . $subdir . '/' . $filename,
        'path' => $destPath,
    ];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing its temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file.',
        UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
        default               => 'Unknown upload error.',
    };
}

function upload_slugify(string $input): string
{
    $s = trim($input);
    if ($s === '') return '';
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = strtolower($s);
    if (strlen($s) > 80) $s = substr($s, 0, 80);
    return $s;
}
