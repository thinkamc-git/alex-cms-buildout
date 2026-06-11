<?php
/**
 * cms/account.php — permanent redirect to Settings → Account.
 *
 * The change-password form (and now recovery codes + Force-All-Logout) moved
 * into the Account Settings tab at /cms/settings?tab=account (Phase 24). This
 * stub keeps old bookmarks/links working.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';

Auth::require_login();
header('Location: /cms/settings?tab=account', true, 301);
exit;
