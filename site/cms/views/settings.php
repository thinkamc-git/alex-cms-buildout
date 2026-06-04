<?php
/**
 * cms/views/settings.php — site-wide Settings editor (Phase 21).
 *
 * Single form, seven fields grouped into three blocks:
 *   - Identity   site_title, site_tagline, footer_copyright
 *   - Social     default_og_image, default_og_type, default_twitter_card
 *   - Integrations  analytics_script
 *
 * Saves through lib/settings.php's save_settings() — POST submits the full
 * known set; unknown keys are ignored. Reads cache invalidates on write so
 * the page-shell picks up new values on the next request.
 *
 * Trust model: the analytics_script field accepts a raw `<script>` tag.
 * The Settings view is admin-only behind Auth::require_login() so this is
 * a trusted-input surface — no validation, no escaping on render.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/settings.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $values = [];
        foreach (SETTINGS_KEYS as $k) {
            $values[$k] = (string)($_POST[$k] ?? '');
        }
        save_settings($values);
        $flash = 'Settings saved.';
        header('Location: /cms/settings?flash=' . rawurlencode($flash));
        exit;
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

$settings = list_settings();

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Settings — alexmchong.ca CMS</title>
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
</head>
<body>

<?php
$breadcrumb = 'Settings';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'settings';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-settings">
      <?php
      $title    = 'Settings';
      $subtitle = 'Site-wide defaults. These values render on every public page unless an individual page overrides them.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php
        require __DIR__ . '/../partials/flash.php';
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post" action="/cms/settings" class="cms-form cms-form-wide">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Identity</span>
                <span class="content-block-sublabel">Names and labels that appear in browser chrome and page footers</span>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-site-title">Site title</label>
              <input type="text" class="field-input" id="set-site-title" name="site_title"
                     value="<?= $e($settings['site_title']) ?>" maxlength="120" required>
              <p class="field-hint">Appended to every page's <code>&lt;title&gt;</code> as the suffix. e.g. <em>About — <span id="set-site-title-preview"><?= $e($settings['site_title'] !== '' ? $settings['site_title'] : 'Alex M. Chong') ?></span></em>.</p>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-site-tagline">Site tagline</label>
              <input type="text" class="field-input" id="set-site-tagline" name="site_tagline"
                     value="<?= $e($settings['site_tagline']) ?>" maxlength="200">
              <p class="field-hint">Short descriptor reserved for future use (homepage hero, OG description fallback). Optional.</p>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-footer-copyright">Footer copyright</label>
              <input type="text" class="field-input" id="set-footer-copyright" name="footer_copyright"
                     value="<?= $e($settings['footer_copyright']) ?>" maxlength="200">
              <p class="field-hint">The name after the © year. Year is auto-generated from the server clock.</p>
            </div>
          </div>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Social preview defaults</span>
                <span class="content-block-sublabel">Used when an individual page hasn't set its own og:image / og:type / twitter:card</span>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-og-image">Default og:image URL</label>
              <input type="text" class="field-input" id="set-og-image" name="default_og_image"
                     value="<?= $e($settings['default_og_image']) ?>" placeholder="/uploads/og/default.jpg">
              <p class="field-hint">Recommended 1200×630. Paths starting with / resolve to the site root; full URLs (<code>https://…</code>) also work.</p>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-og-type">Default og:type</label>
              <select class="field-input" id="set-og-type" name="default_og_type">
                <?php foreach (['website','article','profile'] as $t): ?>
                  <option value="<?= $t ?>"<?= $settings['default_og_type'] === $t ? ' selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-twitter-card">Default twitter:card</label>
              <select class="field-input" id="set-twitter-card" name="default_twitter_card">
                <?php foreach (['summary','summary_large_image'] as $t): ?>
                  <option value="<?= $t ?>"<?= $settings['default_twitter_card'] === $t ? ' selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Integrations</span>
                <span class="content-block-sublabel">Third-party snippets injected on every public page</span>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label" for="set-analytics">Analytics script</label>
              <textarea class="field-input" id="set-analytics" name="analytics_script" rows="6"
                        style="font-family:var(--font-mono);font-size:var(--text-small)"
                        placeholder="&lt;script async src=&quot;https://…&quot;&gt;&lt;/script&gt;"><?= $e($settings['analytics_script']) ?></textarea>
              <p class="field-hint">Paste the full <code>&lt;script&gt;</code> tag(s) from your provider. Renders immediately before <code>&lt;/body&gt;</code> on every public page. Leave blank to disable.</p>
            </div>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" class="btn-pri" data-save-btn>Save settings</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script src="/cms/_assets/dirty-flip.js" defer></script>
<script>
  // Mirror the live Site title value into the example shown in its hint.
  (function () {
    var inp = document.getElementById('set-site-title');
    var out = document.getElementById('set-site-title-preview');
    if (!inp || !out) return;
    inp.addEventListener('input', function () {
      out.textContent = inp.value.trim() !== '' ? inp.value : 'Alex M. Chong';
    });
  })();
</script>

</body>
</html>
