<?php
/**
 * cms/views/settings.php — Settings, two tabs (Phase 24).
 *
 *   • Site settings    — the seven site-wide fields (Identity / Social /
 *                        Integrations) saved through lib/settings.php.
 *   • Account settings — change password, offline recovery codes, and
 *                        Force-All-Logout. Absorbs the old /cms/account page.
 *
 * Tabs reuse the canonical .cms-tabs + [data-tab-target]/[data-tab-panel]
 * toggle (preview-tab-guard.js). A recovery-code login sets
 * $_SESSION['must_change_pw]; require_login() confines the session here until
 * a new password is set (the ?force=1 banner explains why).
 *
 * Trust model: the analytics_script field accepts a raw <script> tag. The
 * view is admin-only behind Auth::require_login() — trusted-input surface.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/settings.php';
require_once __DIR__ . '/../../lib/recovery_codes.php';
require_once __DIR__ . '/../../lib/author.php';
require_once __DIR__ . '/../../lib/uploads.php';

Auth::require_login();
$csrf_token = Csrf::token();

$user      = Auth::current_user();
$uid       = (int)($user['id'] ?? 0);
$userEmail = (string)($user['email'] ?? '');

$errors    = [];   // site-settings form errors
$flash     = '';
$pwError   = '';
$pwFlash   = '';
$authorError   = '';
$justGenerated = []; // newly-created recovery codes [['id','code'],…] — shown once

$mustChange = !empty($_SESSION['must_change_pw']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? 'save_settings');

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        if ($action === 'change_password')      { $pwError = 'Session expired. Reload the page and try again.'; }
        else                                    { $errors[] = 'Session expired. Reload the page and try again.'; }
    } else {
        switch ($action) {
            case 'change_password':
                if ($mustChange) {
                    // Recovery-code login: no current password to supply.
                    $res = Auth::set_password_after_recovery(
                        (string)($_POST['new_password'] ?? ''),
                        (string)($_POST['new_password_confirm'] ?? '')
                    );
                } else {
                    $res = Auth::change_password(
                        (string)($_POST['current_password'] ?? ''),
                        (string)($_POST['new_password'] ?? ''),
                        (string)($_POST['new_password_confirm'] ?? '')
                    );
                }
                if ($res['ok']) {
                    header('Location: /cms/settings?tab=account&flash=' . rawurlencode('Password updated.'));
                    exit;
                }
                $pwError = (string)$res['error'];
                break;

            case 'generate_recovery':
                // Additive top-up: clears spent codes, then fills back to MAX
                // unused. Existing unused codes stay valid; only the new ones
                // are returned (shown once).
                $justGenerated = RecoveryCodes::top_up($uid);
                $pwFlash       = $justGenerated
                    ? 'Generated ' . count($justGenerated) . ' new recovery code' . (count($justGenerated) === 1 ? '' : 's') . '. Save them now — they will not be shown again. Your existing unused codes still work.'
                    : 'You already have the maximum number of unused recovery codes.';
                break;

            case 'replace_recovery':
                // Destructive: invalidates every existing code and issues a
                // fresh set. Gated behind a two-step confirmation in the UI.
                $justGenerated = RecoveryCodes::replace_all($uid);
                $replacedAll   = true;
                $pwFlash       = 'Replaced all recovery codes. Every previous code is now invalid. Save the new set — it will not be shown again.';
                break;

            case 'force_logout':
                Auth::logout_other_sessions(); // keeps THIS session
                header('Location: /cms/settings?tab=account&flash=' . rawurlencode('Signed out of all other sessions. This device is still signed in.'));
                exit;

            case 'save_author':
                $cur   = get_author();
                $image = (string)($cur['image'] ?? '');
                // Photo: remove > new upload > keep existing.
                if (!empty($_POST['author_photo_remove'])) {
                    $image = '';
                } elseif (!empty($_FILES['author_photo']['name']) && (int)($_FILES['author_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $up = accept_upload($_FILES['author_photo'], 'author');
                    if (!$up['ok']) {
                        $authorError = (string)$up['error'];
                        break;
                    }
                    $image = (string)$up['url'];
                }
                if ($authorError === '') {
                    save_author(
                        trim((string)($_POST['author_name'] ?? '')),
                        trim((string)($_POST['author_short'] ?? '')),
                        trim((string)($_POST['author_extended'] ?? '')),
                        $image
                    );
                    header('Location: /cms/settings?tab=author&flash=' . rawurlencode('Author info saved.'));
                    exit;
                }
                break;

            case 'save_settings':
            default:
                $values = [];
                foreach (SETTINGS_KEYS as $k) {
                    $values[$k] = (string)($_POST[$k] ?? '');
                }
                save_settings($values);
                header('Location: /cms/settings?tab=site&flash=' . rawurlencode('Settings saved.'));
                exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Active tab. A forced password change pins it to Account; an author-save
// error pins it to Author Info.
$reqTab = (string)($_GET['tab'] ?? 'site');
$tab    = in_array($reqTab, ['site', 'account', 'author'], true) ? $reqTab : 'site';
if ($mustChange || !empty($justGenerated) || $pwError !== '') {
    $tab = 'account';
}
if ($authorError !== '') {
    $tab = 'author';
}

$settings       = list_settings();
$unusedCodes    = RecoveryCodes::count_unused($uid);
$codeStatus     = RecoveryCodes::status($uid);  // [['id','used'],…]
$justMap        = [];                            // id → plaintext for just-created codes
foreach ($justGenerated as $g) { $justMap[(int)$g['id']] = (string)$g['code']; }
$author         = get_author();                  // raw row for the editor
$authorInitials = author_initials($author['name'] ?? null);

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
<link rel="stylesheet" href="/cms/_assets/style-cms.css<?= asset_ver('/cms/_assets/style-cms.css') ?>">
<link rel="stylesheet" href="/cms/_assets/codemirror/codemirror.min.css">
<style>
  /* Integrations snippet rendered as a CodeMirror code view (htmlmixed) —
     syntax-highlighted + monospaced, matching the page/template editors. */
  #set-analytics + .CodeMirror { border:1px solid var(--border); border-radius:var(--r-card); font-size:13px; height:auto; min-height:96px; }
  #set-analytics + .CodeMirror .CodeMirror-lines { padding:6px 0; }
</style>
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
      $subtitle = 'Site-wide defaults and your account security.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="cms-tabs" role="tablist">
        <a href="?tab=site" class="cms-tab<?= $tab === 'site' ? ' active' : '' ?>" role="tab" data-tab-target="site" aria-selected="<?= $tab === 'site' ? 'true' : 'false' ?>">Site settings</a>
        <a href="?tab=account" class="cms-tab<?= $tab === 'account' ? ' active' : '' ?>" role="tab" data-tab-target="account" aria-selected="<?= $tab === 'account' ? 'true' : 'false' ?>">Account settings</a>
        <a href="?tab=author" class="cms-tab<?= $tab === 'author' ? ' active' : '' ?>" role="tab" data-tab-target="author" aria-selected="<?= $tab === 'author' ? 'true' : 'false' ?>">Author info</a>
      </div>

      <div class="content-area">
        <?php if ($flash !== ''): ?><div class="flash-success" role="status"><?= $e($flash) ?></div><?php endif; ?>

        <!-- ── Site settings tab ─────────────────────────────────────── -->
        <div data-tab-panel="site" class="<?= $tab === 'site' ? '' : 'is-hidden-tab' ?>">
          <?php if ($errors): ?>
            <div class="form-errors" role="alert">
              <strong>Couldn’t save:</strong>
              <ul style="margin:var(--space-4) 0 0;padding-left:var(--space-20)"><?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <form method="post" action="/cms/settings?tab=site" class="cms-form cms-form-wide reveal-page">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="save_settings">

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
                <textarea class="field-input" id="set-analytics" name="analytics_script" rows="6" spellcheck="false"
                          placeholder="&lt;script async src=&quot;https://…&quot;&gt;&lt;/script&gt;"><?= $e($settings['analytics_script']) ?></textarea>
                <p class="field-hint">Paste the full <code>&lt;script&gt;</code> tag(s) from your provider. Renders immediately before <code>&lt;/body&gt;</code> on every public page. Leave blank to disable.</p>
              </div>
            </div>

            <div class="form-actions form-actions-sticky">
              <button type="submit" class="btn-sec" data-save-btn>Save settings</button>
            </div>
          </form>
        </div>

        <!-- ── Account settings tab ──────────────────────────────────── -->
        <div data-tab-panel="account" class="<?= $tab === 'account' ? '' : 'is-hidden-tab' ?>">

          <?php if ($mustChange): ?>
            <div class="form-errors" role="alert">
              <strong>Set a new password.</strong> You signed in with a recovery code. Choose a new password below before continuing.
            </div>
          <?php endif; ?>
          <?php if ($pwFlash !== ''): ?><div class="flash-success" role="status"><?= $e($pwFlash) ?></div><?php endif; ?>
          <?php if ($pwError !== ''): ?><div class="form-errors" role="alert"><?= $e($pwError) ?></div><?php endif; ?>

          <!-- Change password -->
          <form method="post" action="/cms/settings?tab=account" class="cms-form cms-form-wide">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="content-block">
              <div class="content-block-header">
                <div>
                  <span class="content-block-label">Change password</span>
                  <span class="content-block-sublabel">Signed in as <?= $e($userEmail) ?></span>
                </div>
              </div>
              <div class="field-group">
                <label class="field-label" for="acct-current">Current password</label>
                <input class="field-input" id="acct-current" type="password" name="current_password" autocomplete="current-password" <?= $mustChange ? '' : 'required' ?>>
                <?php if ($mustChange): ?><p class="field-hint">You signed in with a recovery code, so the current-password field is optional here.</p><?php endif; ?>
              </div>
              <div class="field-group">
                <label class="field-label" for="acct-new">New password</label>
                <input class="field-input" id="acct-new" type="password" name="new_password" autocomplete="new-password" required minlength="12">
              </div>
              <div class="field-group">
                <label class="field-label" for="acct-confirm">Confirm new password</label>
                <input class="field-input" id="acct-confirm" type="password" name="new_password_confirm" autocomplete="new-password" required minlength="12">
              </div>
              <p class="field-hint">At least 12 characters, with at least one uppercase letter, one lowercase letter, and one digit.</p>
              <div class="form-actions">
                <button type="submit" class="btn-sec" data-save-btn>Change password</button>
              </div>
            </div>
          </form>

          <!-- Recovery codes -->
          <?php $unused = (int)$unusedCodes; $room = RecoveryCodes::MAX - $unused; $hasCodes = ($unused > 0 || count($codeStatus) > 0); ?>
          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Recovery codes</span>
                <span class="content-block-sublabel">Backup codes to sign in if you lose your password</span>
              </div>
              <span class="content-block-count"><?= $unused ?> unused</span>
            </div>

            <?php if (!empty($justMap)): ?>
              <!-- One-time reveal: the codes just generated. Shown only here, once. -->
              <div class="recovery-reveal" role="group" aria-label="Your new recovery codes">
                <p class="recovery-reveal-head"><strong>Save these now</strong> — they won’t be shown again. <?= !empty($replacedAll) ? 'Every previous code is now invalid.' : 'Your existing unused codes still work too.' ?></p>
                <div class="recovery-codes" id="recovery-new-codes">
                  <?php foreach ($justMap as $code): ?><code class="recovery-code recovery-code--new"><?= $e($code) ?></code><?php endforeach; ?>
                </div>
                <button type="button" class="btn-sec btn-tiny" data-copy-codes="#recovery-new-codes">Copy all</button>
              </div>
            <?php endif; ?>

            <p class="recovery-status">
              <?php if ($unused === 0): ?>
                You have <strong>no</strong> recovery codes yet.
              <?php else: ?>
                You have <strong><?= $unused ?></strong> unused recovery code<?= $unused === 1 ? '' : 's' ?><?= $room <= 0 ? ' — the maximum of ' . RecoveryCodes::MAX : '' ?>.
              <?php endif; ?>
            </p>
            <p class="recovery-note">Each works once. They’re stored as hashes and shown only when generated — so save them then.</p>

            <div class="recovery-actions" data-replace-all>
              <div class="recovery-actions-row">
                <?php if ($room > 0): ?>
                  <form method="post" action="/cms/settings?tab=account">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="action" value="generate_recovery">
                    <button type="submit" class="btn-sec" data-loading-btn data-loading-label="Generating…"><?= $unused === 0 ? 'Generate ' . RecoveryCodes::MAX . ' codes' : 'Generate ' . $room . ' more' ?></button>
                  </form>
                <?php endif; ?>
                <?php if ($hasCodes): ?>
                  <button type="button" class="recovery-danger-trigger" data-replace-toggle>Replace all codes&hellip;</button>
                <?php endif; ?>
              </div>
              <?php if ($hasCodes): ?>
                <!-- Destructive replace-all — two-step inline confirm so a single
                     click can't wipe a saved set. -->
                <div class="recovery-danger-confirm" hidden>
                  <p class="recovery-danger-warn">This <strong>immediately invalidates every recovery code you have</strong> — including ones you’ve already saved — and issues a brand-new set. Only do this if you think your codes have leaked. You’ll see the new set once.</p>
                  <div class="recovery-danger-actions">
                    <button type="button" class="btn-sec btn-tiny" data-replace-cancel>Cancel</button>
                    <form method="post" action="/cms/settings?tab=account" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="action" value="replace_recovery">
                      <button type="submit" class="btn-danger btn-tiny" data-loading-btn data-loading-label="Replacing…">Yes, replace all codes</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Sign out other sessions -->
          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Sign out other sessions</span>
                <span class="content-block-sublabel">Ends every active session on your other devices but keeps this one signed in. Use it if you left yourself signed in somewhere.</span>
              </div>
            </div>
            <form method="post" action="/cms/settings?tab=account" onsubmit="return confirm('Sign out of every other session on all your other devices? This device stays signed in.');">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="action" value="force_logout">
              <button type="submit" class="btn-sec" data-loading-btn data-loading-label="Signing out…">Sign out other sessions</button>
            </form>
          </div>

        </div>

        <!-- ── Author info tab ───────────────────────────────────────── -->
        <div data-tab-panel="author" class="<?= $tab === 'author' ? '' : 'is-hidden-tab' ?>">
          <?php if ($authorError !== ''): ?><div class="form-errors" role="alert"><?= $e($authorError) ?></div><?php endif; ?>

          <form method="post" action="/cms/settings?tab=author" enctype="multipart/form-data" class="cms-form cms-form-wide">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="save_author">

            <div class="content-block">
              <div class="content-block-header">
                <div>
                  <span class="content-block-label">Author info</span>
                  <span class="content-block-sublabel">Site-level author identity — shown on posts (byline + bio) and the Author Info index section.</span>
                </div>
              </div>

              <div class="field-group">
                <label class="field-label">Photo</label>
                <div class="author-photo-row">
                  <span class="author-photo-preview" id="author-photo-preview" data-initials="<?= $e($authorInitials) ?>">
                    <?php if (!empty($author['image'])): ?>
                      <img src="<?= $e((string)$author['image']) ?>" alt="">
                    <?php elseif ($authorInitials !== ''): ?>
                      <span class="author-photo-initials"><?= $e($authorInitials) ?></span>
                    <?php endif; ?>
                  </span>
                  <div class="author-photo-controls">
                    <div class="author-photo-actions">
                      <label class="btn-sec author-photo-choose">Choose photo<input type="file" id="author-photo-input" name="author_photo" accept="image/png,image/jpeg,image/webp,image/gif" hidden></label>
                      <?php if (!empty($author['image'])): ?>
                        <button type="button" class="btn-ghost" id="author-photo-remove-btn">Remove</button>
                      <?php endif; ?>
                      <span class="author-photo-filename" id="author-photo-filename"></span>
                    </div>
                    <p class="field-hint">PNG, JPG, WebP or GIF, up to 5&nbsp;MB. Square images look best — they crop to a circle.</p>
                    <input type="hidden" name="author_photo_remove" id="author-photo-remove" value="0">
                  </div>
                </div>
              </div>

              <div class="field-group">
                <label class="field-label" for="author-name">Name</label>
                <input class="field-input" id="author-name" type="text" name="author_name" value="<?= $e((string)($author['name'] ?? '')) ?>" maxlength="255">
              </div>

              <div class="field-group">
                <label class="field-label" for="author-short">Short description</label>
                <input class="field-input" id="author-short" type="text" name="author_short" value="<?= $e((string)($author['short_description'] ?? '')) ?>" maxlength="255">
                <p class="field-hint">The tagline shown next to your name in post bylines.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="author-extended">Bio</label>
                <textarea class="field-input" id="author-extended" name="author_extended" rows="4"><?= $e((string)($author['extended_description'] ?? '')) ?></textarea>
                <p class="field-hint">The longer author bio shown in the Author Bio block and the Author Info index section.</p>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-sec" data-save-btn>Save author info</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- preview-tab-guard drives the Site/Account tab toggle (generic
     [data-tab-target]/[data-tab-panel]) + the dirty-form beforeunload guard;
     dirty-flip flips the Save button btn-sec → btn-pri. -->
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
<!-- CodeMirror for the Integrations snippet field (htmlmixed = HTML + inline
     JS/CSS). htmlmixed depends on xml + javascript + css modes. -->
<script src="/cms/_assets/codemirror/codemirror.min.js"></script>
<script src="/cms/_assets/codemirror/mode/xml/xml.min.js"></script>
<script src="/cms/_assets/codemirror/mode/javascript/javascript.min.js"></script>
<script src="/cms/_assets/codemirror/mode/css/css.min.js"></script>
<script src="/cms/_assets/codemirror/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/matchbrackets.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/closebrackets.min.js"></script>
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

  // Integrations snippet → CodeMirror code view (htmlmixed, syntax-highlighted,
  // monospaced). Mirrors edits back to the textarea so dirty-flip + the POST
  // submit keep working; refreshes on tab show (CM mis-sizes if it initialised
  // inside a hidden tab panel).
  (function () {
    var ta = document.getElementById('set-analytics');
    if (!ta || typeof CodeMirror === 'undefined') return;
    var cm = CodeMirror.fromTextArea(ta, {
      mode: 'htmlmixed',
      lineNumbers: true,
      matchBrackets: true,
      autoCloseBrackets: true,
      indentUnit: 2,
      tabSize: 2,
      lineWrapping: true,
      viewportMargin: Infinity,
    });
    cm.on('change', function () {
      ta.value = cm.getValue();
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    });
    setTimeout(function () { cm.refresh(); }, 0);
    document.querySelectorAll('[data-tab-target]').forEach(function (t) {
      t.addEventListener('click', function () { setTimeout(function () { cm.refresh(); }, 0); });
    });
  })();

  // Loading states: spinner-in-button for [data-loading-btn]; shimmer reveal
  // for forms with [data-reveal-shimmer]. Both run on submit (full-page POST),
  // so the loading UI shows during the round-trip until the page reloads.
  (function () {
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        // A confirm() that returned false (Cancel) already cancelled the
        // submit — don't show a spinner for a submit that isn't happening.
        if (e.defaultPrevented) return;
        var btn = form.querySelector('[data-loading-btn]');
        if (btn) {
          btn.disabled = true;
          var label = btn.getAttribute('data-loading-label') || 'Working…';
          btn.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + label;
        }
        var shimId = form.getAttribute('data-reveal-shimmer');
        if (shimId) {
          var shim = document.getElementById(shimId);
          var block = form.closest('.content-block');
          if (shim && block) {
            block.querySelectorAll('.recovery-codes').forEach(function (g) {
              if (g !== shim) g.style.display = 'none';
            });
            shim.hidden = false;
          }
        }
      });
    });
  })();

  // Recovery codes: "Copy all" the one-time set, and the two-step "Replace
  // all" reveal (a quiet trigger expands a gated confirm panel — never a
  // single-click purge).
  (function () {
    document.querySelectorAll('[data-copy-codes]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var host = document.querySelector(btn.getAttribute('data-copy-codes'));
        if (!host) return;
        var text = Array.prototype.map.call(host.querySelectorAll('.recovery-code'), function (c) {
          return c.textContent.trim();
        }).join('\n');
        var done = function () {
          var orig = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = orig; }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () {});
        } else {
          var ta = document.createElement('textarea');
          ta.value = text; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          ta.remove();
        }
      });
    });

    document.querySelectorAll('[data-replace-all]').forEach(function (wrap) {
      var trigger = wrap.querySelector('[data-replace-toggle]');
      var panel   = wrap.querySelector('.recovery-danger-confirm');
      var cancel  = wrap.querySelector('[data-replace-cancel]');
      if (!trigger || !panel) return;
      trigger.addEventListener('click', function () { panel.hidden = false; trigger.hidden = true; });
      if (cancel) cancel.addEventListener('click', function () { panel.hidden = true; trigger.hidden = false; });
    });
  })();

  // Author photo: live-preview a chosen file, show its name, and a
  // Remove toggle. Programmatic value changes dispatch a change event so
  // the Save button's dirty-flip still fires.
  (function () {
    var input = document.getElementById('author-photo-input');
    var prev  = document.getElementById('author-photo-preview');
    var fname = document.getElementById('author-photo-filename');
    var rmBtn = document.getElementById('author-photo-remove-btn');
    var rmIn  = document.getElementById('author-photo-remove');
    if (!prev) return;
    var originalHTML = prev.innerHTML;
    var initials = prev.getAttribute('data-initials') || '';
    var initialsHTML = initials ? '<span class="author-photo-initials">' + initials + '</span>' : '';

    if (input) input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) return;
      prev.innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt="">';
      if (fname) fname.textContent = f.name;
      if (rmIn) rmIn.value = '0';
      if (rmBtn) rmBtn.textContent = 'Remove';
    });

    if (rmBtn && rmIn) rmBtn.addEventListener('click', function () {
      if (rmIn.value === '1') {
        rmIn.value = '0';
        prev.innerHTML = originalHTML;
        rmBtn.textContent = 'Remove';
        if (fname) fname.textContent = '';
      } else {
        rmIn.value = '1';
        prev.innerHTML = initialsHTML;
        rmBtn.textContent = 'Undo';
        if (fname) fname.textContent = 'Photo will be removed on save';
        if (input) input.value = '';
      }
      rmIn.dispatchEvent(new Event('change', { bubbles: true })); // trip dirty-flip
    });
  })();
</script>

</body>
</html>
