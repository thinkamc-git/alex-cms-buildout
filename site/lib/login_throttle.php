<?php
/**
 * lib/login_throttle.php — adaptive per-IP login throttle.
 *
 * Replaces the per-account lockout (which could DoS the legitimate single
 * user). Defends the login form by budgeting *failed* attempts per client IP
 * inside a rolling window, with a decaying allowance as more distinct IPs
 * start failing (the distributed-attack signal):
 *
 *   - 1st failing IP in the window      → 10 attempts
 *   - 2nd                               →  5
 *   - 3rd                               →  3
 *   - 4th and beyond                    →  1
 *
 * A *correct* password (or valid recovery code) is never throttled — the
 * budget only governs failures. IPs that have authenticated successfully
 * within TRUSTED_DAYS are "trusted" and always get the full budget, so the
 * author's known locations keep their full runway even mid-attack.
 *
 * Backed by the login_attempts table (migration 0028), which also serves as
 * the login audit log. Client IP is REMOTE_ADDR — the real TCP peer on
 * DreamHost, not a spoofable X-Forwarded-For header.
 *
 * See AUTH-SECURITY.md §6.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class LoginThrottle
{
    public const WINDOW_MINUTES = 15;
    /** Decaying budget by the IP's order of appearance among failing IPs. */
    private const LADDER         = [10, 5, 3, 1];
    public const TRUSTED_BUDGET  = 10;
    private const TRUSTED_DAYS    = 90;
    private const PRUNE_DAYS      = 90;

    /** The real client IP (REMOTE_ADDR), clamped to the column width. */
    public static function client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? substr($ip, 0, 45) : '';
    }

    /**
     * Decide whether an attempt from $ip is currently allowed.
     *
     * Returns:
     *   allowed          bool   — false ⇒ over budget, reject before verifying
     *   budget           int    — the IP's current attempt budget
     *   used             int    — failures already recorded in the window
     *   elevated         bool    — escalation active (≥2 distinct failing IPs and IP not trusted)
     *   retry_after_secs int    — seconds until one slot frees (0 when allowed)
     */
    public static function check(string $ip): array
    {
        $win = self::WINDOW_MINUTES;

        $trusted = self::is_trusted($ip);

        // Failures from this IP inside the window.
        $stmt = db()->prepare(
            "SELECT COUNT(*) AS c, MIN(attempted_at) AS oldest
               FROM login_attempts
              WHERE ip_address = :ip
                AND outcome = 'fail'
                AND attempted_at > (NOW() - INTERVAL {$win} MINUTE)"
        );
        $stmt->execute([':ip' => $ip]);
        $r      = $stmt->fetch() ?: ['c' => 0, 'oldest' => null];
        $used   = (int)($r['c'] ?? 0);
        $oldest = $r['oldest'] ?? null;

        // Distinct failing IPs in the window, ordered by first failure, to
        // place this IP on the decay ladder.
        $ips = db()->query(
            "SELECT ip_address
               FROM login_attempts
              WHERE outcome = 'fail'
                AND attempted_at > (NOW() - INTERVAL {$win} MINUTE)
              GROUP BY ip_address
              ORDER BY MIN(attempted_at) ASC"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $distinct = count($ips);
        $pos      = array_search($ip, $ips, true);
        $ordinal  = ($pos === false) ? ($distinct + 1) : ($pos + 1);

        if ($trusted) {
            $budget = self::TRUSTED_BUDGET;
        } else {
            $idx    = min($ordinal - 1, count(self::LADDER) - 1);
            $budget = self::LADDER[$idx];
        }

        $allowed  = $used < $budget;
        $elevated = !$trusted && $distinct >= 2;

        $retry = 0;
        if (!$allowed && $oldest !== null) {
            $expires = strtotime((string)$oldest) + ($win * 60);
            $retry   = max(0, $expires - time());
        }

        return [
            'allowed'          => $allowed,
            'budget'           => $budget,
            'used'             => $used,
            'elevated'         => $elevated,
            'retry_after_secs' => $retry,
        ];
    }

    /** Record an attempt outcome and opportunistically prune old rows. */
    public static function record(string $ip, bool $success, ?string $email = null): void
    {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts (ip_address, outcome, email)
             VALUES (:ip, :outcome, :email)'
        );
        $stmt->execute([
            ':ip'      => $ip,
            ':outcome' => $success ? 'success' : 'fail',
            ':email'   => $email !== null ? substr($email, 0, 255) : null,
        ]);

        if ($success) {
            self::prune();
        }
    }

    /** True if this IP has a successful login within TRUSTED_DAYS. */
    private static function is_trusted(string $ip): bool
    {
        $days = self::TRUSTED_DAYS;
        $stmt = db()->prepare(
            "SELECT 1 FROM login_attempts
              WHERE ip_address = :ip
                AND outcome = 'success'
                AND attempted_at > (NOW() - INTERVAL {$days} DAY)
              LIMIT 1"
        );
        $stmt->execute([':ip' => $ip]);
        return (bool)$stmt->fetchColumn();
    }

    private static function prune(): void
    {
        $days = self::PRUNE_DAYS;
        db()->exec(
            "DELETE FROM login_attempts
              WHERE attempted_at < (NOW() - INTERVAL {$days} DAY)"
        );
    }
}
