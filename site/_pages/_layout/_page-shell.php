<?php
/**
 * _page-shell.php — shared chrome for the marketing pages.
 *
 * Each per-page assembler (about.php, coaching.php, …) sets these
 * variables and then requires this file:
 *
 *   $title        string  required — <title> contents
 *   $body         string  required — slug of the file in _bodies/<slug>.html
 *   $description  string  optional — <meta name="description"> content
 *   $noindex      bool    optional — when true, emits noindex meta
 *
 * The shell emits the full <head>, opens <body>, then includes:
 *   _layout/header.html  →  shared nav
 *   _bodies/{body}.html  →  page-specific content
 *   _layout/footer.html  →  shared footer
 *
 * Header.html and footer.html are pure HTML (no PHP) so they preview
 * directly via file:// — the nav and footer are the parts you'll edit
 * often. The <head> lives here so per-page meta stays out of those
 * partials.
 */
$title       = $title       ?? 'Alex M. Chong';
$description = $description ?? '';
$noindex     = $noindex     ?? false;
$body        = $body        ?? '';

$_e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $_e($title) ?></title>
<?php if ($description !== ''): ?>
  <meta name="description" content="<?= $_e($description) ?>" />
<?php endif; ?>
<?php if ($noindex): ?>
  <meta name="robots" content="noindex" />
<?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/_layout/style-pages.css" />
  <link rel="icon" type="image/png" href="/_layout/favicon<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png" />
  <script src="/_layout/analytics.js" async></script>
</head>
<body>

<?php require __DIR__ . '/header.html'; ?>

<?php
$_body_file = __DIR__ . '/../_bodies/' . $body . '.html';
if ($body !== '' && is_file($_body_file)) {
    require $_body_file;
}
?>

<?php require __DIR__ . '/footer.html'; ?>

</body>
</html>
