<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/topbar.php — fixed top bar (logo · breadcrumb · log out).
 *
 * Inputs (set before include):
 *   $breadcrumb       string  Plain text breadcrumb (e.g. "Pipeline", "Articles / New").
 *                              Defaults to "Pipeline" if unset.
 *   $breadcrumb_href  string  Optional. When set, the breadcrumb renders as
 *                              a link to this URL (typically the parent
 *                              list view — clicking goes "back up").
 *   $csrf_token       string  CSRF token for the logout form. Required.
 *
 * Auto-derived hrefs: when $breadcrumb_href is unset, the partial maps a
 * handful of known breadcrumb prefixes to their list view so every section
 * is clickable without each view having to declare the href explicitly.
 *
 * The Log out button is a real <form method="post" action="/cms/logout"> per
 * AUTH-SECURITY.md §7 (state-changing requests are POST + CSRF). The mockup
 * uses a static <button class="btn-ghost">; this partial keeps the same
 * class so styling is identical.
 */

$breadcrumb = isset($breadcrumb) ? (string)$breadcrumb : 'Pipeline';
$csrf_token = (string)($csrf_token ?? '');

// Auto-derive the breadcrumb href when the view didn't set one. Matches the
// first segment of the breadcrumb (before ' → ' or ' / ') against known
// section list-view URLs.
if (!isset($breadcrumb_href) || $breadcrumb_href === '') {
    $_crumbHrefMap = [
        'Articles'        => '/cms/articles',
        'Journals'        => '/cms/journals',
        'Live Sessions'   => '/cms/live-sessions',
        'Experiments'     => '/cms/experiments',
        'Ideation'        => '/cms/ideation',
        'Pages'           => '/cms/pages',
        'Pipeline'        => '/cms/',
        'Draft Writing'   => '/cms/',
        'Navigation'      => '/cms/navigation',
        'Redirects'       => '/cms/redirects',
        'Categories'      => '/cms/categories',
        'Series'          => '/cms/series',
        'Indexes'         => '/cms/indexes',
        'Subscribers'     => '/cms/subscribers',
        'Post Templates'  => '/cms/post-template',
    ];
    $_firstCrumb   = trim((string)preg_split('/\s*(→|\/)\s*/u', $breadcrumb, 2)[0]);
    $breadcrumb_href = $_crumbHrefMap[$_firstCrumb] ?? '';
}
?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar dot-surface" role="banner">
  <div class="topbar-logo">alexmchong<span class="topbar-logo-sep"></span><em>cms</em><?php if (defined('APP_ENV') && APP_ENV === 'staging'): ?><span class="topbar-env-pill" title="Staging environment">staging</span><?php endif; ?></div>
  <div class="topbar-divider"></div>
  <div class="topbar-breadcrumb" id="breadcrumb">
<?php
  // Split on → or / so only the first segment is the back-link; the rest
  // render as static crumbs. Single-segment breadcrumbs ("Articles") get
  // the link treatment too — they ARE the back link.
  $_crumb_parts = preg_split('/\s*(→|\/)\s*/u', $breadcrumb);
  $_first       = htmlspecialchars(trim((string)$_crumb_parts[0]), ENT_QUOTES, 'UTF-8');
  if ($breadcrumb_href !== ''): ?>
    <a class="crumb-active" href="<?= htmlspecialchars($breadcrumb_href, ENT_QUOTES, 'UTF-8') ?>" title="Back to <?= $_first ?>"><?= $_first ?></a>
<?php else: ?>
    <span class="crumb-active"><?= $_first ?></span>
<?php endif; ?>
<?php for ($_i = 1, $_n = count($_crumb_parts); $_i < $_n; $_i++): ?>
    <span class="crumb-sep" aria-hidden="true"> → </span><span class="crumb-rest"><?= htmlspecialchars(trim((string)$_crumb_parts[$_i]), ENT_QUOTES, 'UTF-8') ?></span>
<?php endfor; ?>
  </div>
  <div class="topbar-right">
    <form method="post" action="/cms/logout" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn-ghost">Log out</button>
    </form>
  </div>
</header>
