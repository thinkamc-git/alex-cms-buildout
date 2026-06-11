<?php
/**
 * lib/recovery_codes.php — offline one-time recovery codes.
 *
 * The single author keeps up to MAX one-time codes to sign in if the password
 * is lost and SSH isn't handy. Codes are stored only as Argon2id hashes (shown
 * once at creation); a code is consumed on a successful recovery-code login,
 * which then forces a password change.
 *
 * Management is per-code (no destructive "regenerate all"): add one to an empty
 * slot, or delete one. Display format: `xxxx-xxxx`. See AUTH-SECURITY.md §13.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class RecoveryCodes
{
    public const MAX       = 8;   // slot cap
    private const LEN      = 8;
    // No 0/1/o/l/i — unambiguous when read off paper.
    private const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    /** Total codes (used + unused). */
    public static function total(int $uid): int
    {
        $st = db()->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = :u');
        $st->execute([':u' => $uid]);
        return (int)$st->fetchColumn();
    }

    public static function count_unused(int $uid): int
    {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM recovery_codes WHERE user_id = :u AND used_at IS NULL'
        );
        $st->execute([':u' => $uid]);
        return (int)$st->fetchColumn();
    }

    /**
     * Per-code state in creation order: [['id'=>int, 'used'=>bool], …].
     * Code text is never returned — only hashes are stored.
     */
    public static function status(int $uid): array
    {
        $st = db()->prepare(
            'SELECT id, used_at FROM recovery_codes WHERE user_id = :u ORDER BY id ASC'
        );
        $st->execute([':u' => $uid]);
        return array_map(
            static fn($r): array => ['id' => (int)$r['id'], 'used' => $r['used_at'] !== null],
            $st->fetchAll() ?: []
        );
    }

    /**
     * Generate up to $n new codes, respecting the MAX cap (never overwrites
     * existing codes). Returns the new ones as [['id'=>int, 'code'=>'xxxx-xxxx'], …]
     * — shown ONCE; never stored in plaintext.
     */
    public static function add(int $uid, int $n = 1): array
    {
        $room = self::MAX - self::total($uid);
        $n    = max(0, min($n, $room));
        if ($n === 0) {
            return [];
        }
        $ins = db()->prepare(
            'INSERT INTO recovery_codes (user_id, code_hash) VALUES (:u, :h)'
        );
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = self::random_code();
            $ins->execute([':u' => $uid, ':h' => password_hash($raw, PASSWORD_DEFAULT)]);
            $out[] = [
                'id'   => (int)db()->lastInsertId(),
                'code' => substr($raw, 0, 4) . '-' . substr($raw, 4, 4),
            ];
        }
        return $out;
    }

    /** Delete a single code (scoped to the user). */
    public static function delete_one(int $uid, int $id): void
    {
        db()->prepare('DELETE FROM recovery_codes WHERE id = :id AND user_id = :u')
            ->execute([':id' => $id, ':u' => $uid]);
    }

    /** Delete every spent (used) code for the user. Returns the count removed. */
    public static function purge_used(int $uid): int
    {
        $st = db()->prepare('DELETE FROM recovery_codes WHERE user_id = :u AND used_at IS NOT NULL');
        $st->execute([':u' => $uid]);
        return $st->rowCount();
    }

    /** Delete ALL of the user's codes (used + unused). */
    public static function purge_all(int $uid): void
    {
        db()->prepare('DELETE FROM recovery_codes WHERE user_id = :u')->execute([':u' => $uid]);
    }

    /**
     * Top up to MAX *unused* codes (additive, non-destructive): clear spent
     * codes — dead weight that otherwise blocks the cap — then generate enough
     * NEW codes to reach MAX. Existing UNUSED codes are preserved and stay
     * valid. Returns only the new codes (shown once).
     */
    public static function top_up(int $uid): array
    {
        self::purge_used($uid);
        return self::add($uid, self::MAX);   // add() caps at MAX − total
    }

    /**
     * Replace the WHOLE set: purge everything and issue a fresh MAX codes.
     * Invalidates every existing code (use only when codes may be compromised).
     * Returns the new codes (shown once).
     */
    public static function replace_all(int $uid): array
    {
        self::purge_all($uid);
        return self::add($uid, self::MAX);
    }

    /**
     * Verify a submitted code against the user's unused codes; consume it on
     * match. Input is normalised (lowercased, non-alphanumerics stripped).
     */
    public static function verify_and_consume(int $uid, string $input): bool
    {
        $cand = self::normalize($input);
        if (strlen($cand) !== self::LEN) {
            return false;
        }
        $st = db()->prepare(
            'SELECT id, code_hash FROM recovery_codes
              WHERE user_id = :u AND used_at IS NULL'
        );
        $st->execute([':u' => $uid]);
        foreach ($st->fetchAll() as $row) {
            if (password_verify($cand, (string)$row['code_hash'])) {
                db()->prepare('UPDATE recovery_codes SET used_at = NOW() WHERE id = :id')
                    ->execute([':id' => (int)$row['id']]);
                return true;
            }
        }
        return false;
    }

    private static function normalize(string $input): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($input)) ?? '';
    }

    private static function random_code(): string
    {
        $n = strlen(self::ALPHABET);
        $s = '';
        for ($i = 0; $i < self::LEN; $i++) {
            $s .= self::ALPHABET[random_int(0, $n - 1)];
        }
        return $s;
    }
}
