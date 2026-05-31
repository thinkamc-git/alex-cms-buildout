<?php
declare(strict_types=1);

/**
 * lib/subscribers.php — newsletter subscriber data layer (Phase 14).
 *
 * Mirrors CMS-STRUCTURE.md §20. The public form at /subscribe (handled in
 * index.php) calls subscribe_from_post(); the CMS at /cms/subscribers calls
 * the list/unsubscribe/delete/export helpers.
 *
 * Design notes:
 *   - Duplicate emails re-subscribe (bump subscribed_at, clear
 *     unsubscribed_at). Per §20 — re-subscribing is a feature, not a 409.
 *   - Honeypot field name is `website` (decision locked in Phase 14 brief).
 *     A non-empty value silently discards the submission with success-shaped
 *     output, so bots can't distinguish blocked from accepted.
 *   - Rate limit is per-IP: 1 submission per minute, 10 per day. Enforced
 *     by counting recent rows from the same ip_address. Cheaper than a
 *     separate request_log table and good enough for the volume we expect.
 *   - No CSRF token on the public form (per §20: not required for the
 *     static site/_pages/ preview). Spam protection is honeypot +
 *     rate-limit + email validation.
 */

require_once __DIR__ . '/db.php';

const SUBSCRIBER_HONEYPOT_FIELD = 'website';
const SUBSCRIBER_RATE_LIMIT_PER_MINUTE = 1;
const SUBSCRIBER_RATE_LIMIT_PER_DAY    = 10;
const SUBSCRIBER_TIME_TRAP_SECONDS     = 2;
const SUBSCRIBER_TIME_TRAP_COOKIE      = 'newsletter_form_loaded';

/**
 * Look up a subscriber by email. Returns NULL if no row exists.
 */
function get_subscriber(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM subscribers WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * List subscribers for the CMS view. $filters is an assoc array:
 *   status — 'subscribed' | 'unsubscribed' | null (any)
 *   source — non-empty string to match exactly, or null
 *   since  — 'YYYY-MM-DD' (subscribed on/after), or null
 *   until  — 'YYYY-MM-DD' (subscribed before next day), or null
 */
function list_subscribers(array $filters = []): array
{
    $where  = [];
    $params = [];

    $status = $filters['status'] ?? null;
    if ($status === 'subscribed') {
        $where[] = 'unsubscribed_at IS NULL';
    } elseif ($status === 'unsubscribed') {
        $where[] = 'unsubscribed_at IS NOT NULL';
    }

    $source = $filters['source'] ?? null;
    if (is_string($source) && $source !== '') {
        $where[] = 'source = :source';
        $params[':source'] = $source;
    }

    $since = $filters['since'] ?? null;
    if (is_string($since) && $since !== '') {
        $where[] = 'subscribed_at >= :since';
        $params[':since'] = $since . ' 00:00:00';
    }

    $until = $filters['until'] ?? null;
    if (is_string($until) && $until !== '') {
        $where[] = 'subscribed_at < DATE_ADD(:until, INTERVAL 1 DAY)';
        $params[':until'] = $until;
    }

    $sql = 'SELECT * FROM subscribers';
    if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY subscribed_at DESC, id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

/**
 * Distinct source labels present in the table. Drives the source filter
 * dropdown on the CMS view.
 */
function list_subscriber_sources(): array
{
    $rows = db()->query(
        "SELECT DISTINCT source FROM subscribers WHERE source IS NOT NULL AND source <> '' ORDER BY source"
    )->fetchAll() ?: [];
    return array_map(static fn(array $r): string => (string)$r['source'], $rows);
}

/**
 * Aggregate counts shown in the view header.
 */
function subscriber_counts(): array
{
    $row = db()->query(
        "SELECT
            SUM(CASE WHEN unsubscribed_at IS NULL THEN 1 ELSE 0 END) AS subscribed,
            SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) AS unsubscribed,
            SUM(CASE WHEN subscribed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND unsubscribed_at IS NULL THEN 1 ELSE 0 END) AS recent
           FROM subscribers"
    )->fetch();
    return [
        'subscribed'   => (int)($row['subscribed']   ?? 0),
        'unsubscribed' => (int)($row['unsubscribed'] ?? 0),
        'recent'       => (int)($row['recent']       ?? 0),
    ];
}

/**
 * Insert or re-subscribe. $data accepts:
 *   email       (required)
 *   name        (optional, ≤255)
 *   source      (defaults to 'cms')
 *   ip_address  (optional)
 *   user_agent  (optional)
 *
 * Returns ['ok' => true, 'id' => N, 'resubscribed' => bool] on success
 * or ['ok' => false, 'error' => string] on validation failure.
 *
 * NOTE: this is a low-level upsert. The public submission path calls
 * subscribe_from_post() which layers honeypot + rate-limit on top.
 */
function save_subscriber(array $data): array
{
    $email = strtolower(trim((string)($data['email'] ?? '')));
    if ($email === '') {
        return ['ok' => false, 'error' => 'Email is required.'];
    }
    if (strlen($email) > 255) {
        return ['ok' => false, 'error' => 'Email is too long.'];
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'That email looks off — please double-check.'];
    }

    $name = trim((string)($data['name'] ?? ''));
    if (strlen($name) > 255) $name = substr($name, 0, 255);

    $source     = trim((string)($data['source']     ?? 'cms')) ?: 'cms';
    $ip         = (string)($data['ip_address'] ?? '');
    $userAgent  = (string)($data['user_agent'] ?? '');
    if (strlen($source)    > 100) $source    = substr($source, 0, 100);
    if (strlen($ip)        > 45 ) $ip        = substr($ip, 0, 45);
    if (strlen($userAgent) > 500) $userAgent = substr($userAgent, 0, 500);

    $existing = get_subscriber($email);
    if ($existing !== null) {
        $stmt = db()->prepare(
            'UPDATE subscribers
                SET subscribed_at   = CURRENT_TIMESTAMP,
                    unsubscribed_at = NULL,
                    name            = COALESCE(NULLIF(:name, \'\'), name),
                    source          = COALESCE(NULLIF(:source, \'\'), source),
                    ip_address      = :ip,
                    user_agent      = :ua
              WHERE id = :id'
        );
        $stmt->execute([
            ':name'   => $name,
            ':source' => $source,
            ':ip'     => $ip,
            ':ua'     => $userAgent,
            ':id'     => (int)$existing['id'],
        ]);
        return ['ok' => true, 'id' => (int)$existing['id'], 'resubscribed' => true];
    }

    $stmt = db()->prepare(
        'INSERT INTO subscribers (email, name, source, ip_address, user_agent)
              VALUES (:email, :name, :source, :ip, :ua)'
    );
    $stmt->execute([
        ':email'  => $email,
        ':name'   => $name,
        ':source' => $source,
        ':ip'     => $ip,
        ':ua'     => $userAgent,
    ]);
    return ['ok' => true, 'id' => (int)db()->lastInsertId(), 'resubscribed' => false];
}

/**
 * Mark a subscriber unsubscribed by id. Sets unsubscribed_at = NOW().
 * Returns the affected row count (0 or 1).
 */
function unsubscribe_subscriber(int $id): int
{
    $stmt = db()->prepare(
        'UPDATE subscribers
            SET unsubscribed_at = CURRENT_TIMESTAMP
          WHERE id = :id AND unsubscribed_at IS NULL'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

/**
 * Re-subscribe an already-unsubscribed row from the CMS (clears
 * unsubscribed_at and bumps subscribed_at). Mirror of unsubscribe.
 */
function resubscribe_subscriber(int $id): int
{
    $stmt = db()->prepare(
        'UPDATE subscribers
            SET unsubscribed_at = NULL,
                subscribed_at   = CURRENT_TIMESTAMP
          WHERE id = :id AND unsubscribed_at IS NOT NULL'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

function delete_subscriber(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM subscribers WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Emit a CSV of subscribers (respecting the same filters as list_subscribers).
 * Writes headers via header() and the CSV body directly to STDOUT, then
 * exits — call this from a route handler that has not yet written output.
 *
 * Columns (decision locked in Phase 14 brief):
 *   email, name, source, subscribed_at, ip, user_agent, status
 */
function export_subscribers_csv(array $filters = []): never
{
    $rows = list_subscribers($filters);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'name', 'source', 'subscribed_at', 'ip', 'user_agent', 'status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string)($r['email']         ?? ''),
            (string)($r['name']          ?? ''),
            (string)($r['source']        ?? ''),
            (string)($r['subscribed_at'] ?? ''),
            (string)($r['ip_address']    ?? ''),
            (string)($r['user_agent']    ?? ''),
            ($r['unsubscribed_at'] === null || $r['unsubscribed_at'] === '') ? 'subscribed' : 'unsubscribed',
        ]);
    }
    fclose($out);
    exit;
}

/**
 * MX-record check. Returns true when the email's domain has either an
 * MX record (the standard) or an A record (RFC fallback — some domains
 * accept mail on the bare hostname). Returns false on syntactically
 * weird input, no records, or DNS errors.
 *
 * Catches typos like 'gmail.con' / 'yhaoo.com' and a lot of throwaway
 * domains that never serve mail. Adds ~50ms per check; fine for a
 * form submission, not something you'd put in a tight loop.
 */
function email_domain_has_mx(string $email): bool
{
    $parts = explode('@', strtolower(trim($email)), 2);
    if (count($parts) !== 2 || $parts[1] === '') return false;
    $domain = $parts[1];
    if (!preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $domain)) return false;
    if (checkdnsrr($domain, 'MX')) return true;
    return checkdnsrr($domain, 'A');
}

/**
 * Per-IP rate limit. Returns true when the IP is within both windows
 * (1 sub/min, 10 sub/day) — i.e. allowed to submit. Returns false when
 * either window is exceeded.
 *
 * Empty IP is allowed (CLI testing, server-side calls): only enforced
 * when we actually have an IP to count against.
 */
function subscriber_rate_ok(string $ip): bool
{
    if ($ip === '') return true;

    $stmt = db()->prepare(
        "SELECT
            SUM(subscribed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)) AS recent_minute,
            SUM(subscribed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))    AS recent_day
           FROM subscribers
          WHERE ip_address = :ip"
    );
    $stmt->execute([':ip' => $ip]);
    $row = $stmt->fetch();

    $minute = (int)($row['recent_minute'] ?? 0);
    $day    = (int)($row['recent_day']    ?? 0);

    return $minute < SUBSCRIBER_RATE_LIMIT_PER_MINUTE
        && $day    < SUBSCRIBER_RATE_LIMIT_PER_DAY;
}

/**
 * Public-form submission entry point. Pull $_POST + $_SERVER, run all
 * spam checks, save. Returns a status string the route handler maps to
 * a redirect:
 *
 *   'ok'        → 302 to /subscribe/confirmed/
 *   'honeypot'  → 302 to /subscribe/confirmed/ (silent — bot can't tell)
 *   'fast'      → 302 to /subscribe/confirmed/ (silent — bot can't tell)
 *   'rate'      → 302 back to the form with ?error=rate
 *   'invalid'   → 302 back to the form with ?error=invalid
 *
 * Defense order (cheap → expensive):
 *   1. Honeypot field — instant memory check.
 *   2. Time-trap cookie — instant compare; only enforced if the cookie
 *      is present (don't punish users who block cookies — other checks
 *      still apply).
 *   3. Email syntax — local regex.
 *   4. Per-IP rate-limit — single indexed DB query.
 *   5. MX-record lookup — ~50ms DNS, only if everything else passed.
 *
 * Source defaults to 'newsletter-page' per Phase 14 decision but the
 * caller can override (e.g. an RSVP form posts with source='live-session-rsvp').
 */
function subscribe_from_post(string $defaultSource = 'newsletter-page'): string
{
    // Honeypot — if the hidden field has any value, a bot filled it.
    // Silently treat as success so the bot can't distinguish.
    if (trim((string)($_POST[SUBSCRIBER_HONEYPOT_FIELD] ?? '')) !== '') {
        return 'honeypot';
    }

    // Time-trap — only enforce when the cookie is present. Cookies are
    // set by newsletter.php on every GET, so legitimate humans always
    // have one. Bots that POST without first GETting the form have no
    // cookie; this check is a no-op for them (the other defenses apply).
    $loadedAt = (int)($_COOKIE[SUBSCRIBER_TIME_TRAP_COOKIE] ?? 0);
    if ($loadedAt > 0 && (time() - $loadedAt) < SUBSCRIBER_TIME_TRAP_SECONDS) {
        return 'fast';
    }

    $email = (string)($_POST['email'] ?? '');
    if (trim($email) === '' || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
        return 'invalid';
    }

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!subscriber_rate_ok($ip)) {
        return 'rate';
    }

    if (!email_domain_has_mx($email)) {
        return 'invalid';
    }

    $res = save_subscriber([
        'email'      => $email,
        'name'       => (string)($_POST['name']   ?? ''),
        'source'     => (string)($_POST['source'] ?? $defaultSource),
        'ip_address' => $ip,
        'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);
    if (!$res['ok']) {
        return 'invalid';
    }
    return 'ok';
}
