<?php

/*
 * Local rate-limit + one-pending-challenge-per-username enforcement backing
 * ernsauthClass (core/lib/ErnsAuth.php) -- see ernsauth_sso_attempts.yaml's
 * own docblock for why this table exists and what each column means.
 *
 * checkAndRecordAttempt()'s atomic upsert depends on a UNIQUE index on
 * `username` -- baked directly into that field's `type:` in the yaml
 * (`varchar(64) UNIQUE`), not a hand-added ALTER TABLE, specifically
 * because a hand-added one doesn't survive spill:sql/spill:sql:all
 * regenerating the .sql file from the yaml (confirmed the hard way: it was
 * wiped by this exact table's own test-suite schema rebuild within the
 * same session the table was created in).
 */
class ernsauthSsoAttemptsClassEx extends ernsauthSsoAttemptsClass {

    static function sgetByUsername(string $username): ?ernsauthSsoAttemptsClass {
        $sql = "SELECT * FROM ernsauth_sso_attempts WHERE username = :username";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(":username", $username, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();
        if (!$row) return null;
        return new ernsauthSsoAttemptsClass($row);
    }

    /*
     * Single entry point covering both the app's own per-username rate
     * limit and the one-pending-challenge-per-username cap, so a caller
     * can't apply one without the other -- see CLIENT-INTEGRATION.md
     * (ernsauth repo), "Requiring a username before Flow A" for why both
     * are mandatory. Always call this BEFORE calling ErnsAuth's
     * createChallenge(), same ordering ernsauth's own RateLimit::attempt()
     * requires of its callers.
     *
     * Returns one of:
     *   ['action' => 'reuse', 'challenge_id' => ..., 'challenge_number' => ...,
     *    'expires_at' => ...]
     *     A challenge for this username is still live -- hand it back
     *     unchanged instead of creating a second one (closes the
     *     "flood the approver's Pending Logins list" push-fatigue vector).
     *   ['action' => 'blocked']
     *     The rate limit for this username is exhausted; the caller must
     *     not call createChallenge() at all.
     *   ['action' => 'proceed']
     *     Clear to create a new challenge. The attempt counter has already
     *     been recorded as part of this same call.
     */
    static function checkAndRecordAttempt(string $username, string $clientIp, int $maxAttempts, int $windowSeconds): array {
        $now = time();

        $existing = self::sgetByUsername($username);
        if ($existing && $existing->getchallenge_id() && (int)$existing->getchallenge_expires_at() > $now) {
            return [
                'action' => 'reuse',
                'challenge_id' => $existing->getchallenge_id(),
                'challenge_number' => (int)$existing->getchallenge_number(),
                'expires_at' => (int)$existing->getchallenge_expires_at(),
            ];
        }

        // Atomic upsert of the counter itself -- mirrors ernsauth's own
        // RateLimit::attempt() exactly (see src/RateLimit.php there): the
        // INSERT/UPDATE is one statement, so concurrent requests for the
        // same username serialize on the UNIQUE(username) row lock rather
        // than racing on a read-then-write gap. A window that has expired
        // rolls over to a fresh count of 1 in the same statement that
        // increments a still-current one.
        $db = dbConnection::getConnection();
        $db->prepare(
            "INSERT INTO ernsauth_sso_attempts
                (username, client_ip, attempt_count, window_started_at, updated_at)
             VALUES (:username, :client_ip, 1, :now, :now2)
             ON DUPLICATE KEY UPDATE
               attempt_count = IF(window_started_at IS NULL OR window_started_at + :win < :now3, 1, attempt_count + 1),
               window_started_at = IF(window_started_at IS NULL OR window_started_at + :win2 < :now4, :now5, window_started_at),
               client_ip = :client_ip2,
               updated_at = :now6"
        )->execute([
            ':username'  => $username,
            ':client_ip' => $clientIp,
            ':now'       => $now,
            ':now2'      => $now,
            ':win'       => $windowSeconds,
            ':now3'      => $now,
            ':win2'      => $windowSeconds,
            ':now4'      => $now,
            ':now5'      => $now,
            ':client_ip2' => $clientIp,
            ':now6'      => $now,
        ]);

        // Read back the count our own (now-committed) write just produced
        // -- reflects every attempt recorded up to this point, not a stale
        // pre-write value, same reasoning as RateLimit::attempt().
        $st = $db->prepare("SELECT attempt_count FROM ernsauth_sso_attempts WHERE username = :username");
        $st->execute([':username' => $username]);
        $attempts = (int)($st->fetchColumn() ?: 0);

        if ($attempts > $maxAttempts) {
            return ['action' => 'blocked'];
        }
        return ['action' => 'proceed'];
    }

    // Records the challenge ErnsAuth actually created, so the next request
    // for this username sees it as still-pending until it expires.
    static function recordChallenge(string $username, string $challengeId, int $challengeNumber, int $expiresAt): void {
        self::upsert($username, [
            'challenge_id' => $challengeId,
            'challenge_number' => $challengeNumber,
            'challenge_expires_at' => $expiresAt,
            'updated_at' => time(),
        ]);
    }

    // Clears the pending-challenge fields once resolved (matched,
    // mismatched, rejected, or expired) -- the next request for this
    // username is then free to create a fresh one, subject only to the
    // rate limit above. $outcome is a short free-text label for
    // troubleshooting only, e.g. 'matched' / 'mismatched' / 'expired' /
    // 'rejected' / 'unknown_username' -- never a patient/PII value, since
    // username here is an app account name, not patient data.
    static function clearChallenge(string $username, string $outcome): void {
        self::upsert($username, [
            'challenge_id' => null,
            'challenge_number' => null,
            'challenge_expires_at' => null,
            'last_outcome' => $outcome,
            'updated_at' => time(),
        ]);
    }

    // Plain upsert for fields outside the rate-limit counter itself (which
    // checkAndRecordAttempt() above handles with its own atomic
    // IF()-based statement). A benign race here (two requests for the same
    // username writing challenge state within milliseconds of each other)
    // can at most make one write's effect momentarily overwritten by the
    // other's -- not a security bypass, since the identity-match check in
    // ernsauthClass::exchangeAndVerify() is what actually gates a login,
    // not this table.
    private static function upsert(string $username, array $fields): void {
        $columns = array_merge(['username'], array_keys($fields));
        $placeholders = array_map(fn($c) => ":$c", $columns);
        $updateAssignments = implode(', ', array_map(fn($c) => "$c = VALUES($c)", array_keys($fields)));

        $sql = "INSERT INTO ernsauth_sso_attempts (" . implode(',', $columns) . ")
                VALUES (" . implode(',', $placeholders) . ")
                ON DUPLICATE KEY UPDATE $updateAssignments";

        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':username', $username, PDO::PARAM_STR);
        foreach ($fields as $col => $val) {
            $st->bindValue(":$col", $val, $val === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $st->execute();
    }
}
