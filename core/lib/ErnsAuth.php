<?php

/*
 * Client-side integration with ErnsAuth's number-matching SSO flow ("Flow
 * A"), the username-prompt variant documented in CLIENT-INTEGRATION.md
 * (ernsauth repo) under "Requiring a username before Flow A" -- for apps
 * with several accounts that need to know *which one* is signing in.
 * Config-driven, no app-specific values baked in here (same convention as
 * Recaptcha.php): an app supplies its own `config/ernsauth.php`
 * (sso_api_url/api_key) and vendors the client library itself at
 * `lib/ernsauth/` -- see that file's own docblock below for the exact
 * expected shape. Every failure mode here fails closed
 * (disabled/misconfigured/network error all behave like "not signed in",
 * never a fatal error or a silently-accepted login), matching this
 * framework's existing Recaptcha.php/rbacClass conventions.
 *
 * Opt-in twice over, deliberately:
 *   - packagesClass-style but inverted: apps must call enable() (done by
 *     core/modules/ernsauth_sso/ernsauth_sso.php's register_ernsauth_sso_
 *     module(), itself only invoked when an app lists `ernsauth_sso`
 *     under its own config/settings.info.yaml `modules:` block) before
 *     isEnabled() can ever return true -- unlike disabled_packages (an
 *     opt-OUT list a later config layer can only ever add to, never
 *     re-enable), this feature needs real per-app setup (a vendored
 *     client library, a live ErnsAuth server, an API key) to function at
 *     all, so it defaults OFF and an app must deliberately turn it on.
 *   - even then, isEnabled() also requires a valid config/ernsauth.php to
 *     be present -- listing the module without configuring it still
 *     leaves every route here returning "disabled", never a fatal error
 *     from a missing config file.
 *
 * *** NO POST-APPROVAL IDENTITY CHECK (deliberate, 2026-09-03) ***
 * CLIENT-INTEGRATION.md's step ⑥ -- comparing the ErnsAuth identity that
 * actually approved a challenge against an "expected" identity pinned
 * before it was created -- is intentionally NOT implemented here. finish()
 * trusts any successful exchangeCode() for the pending username, from
 * whichever ErnsAuth account approved it. This was a deliberate choice by
 * this app's maintainer after a full walkthrough of the tradeoff, not an
 * oversight -- see the "No post-approval identity check" entry in this
 * file's CLAUDE.md for the reasoning and its explicit limits (in short:
 * fine when the ErnsAuth dashboard's approver population is trusted and
 * small; does not survive a compromise of the ErnsAuth server itself,
 * which no client-side check could anyway). Do not silently reintroduce
 * step ⑥ here without the same conversation happening again -- ask first.
 *
 * Session keys used across a single browser session's challenge lifecycle
 * (mirroring CLIENT-INTEGRATION.md's own $_SESSION['ea_...'] examples):
 *   ea_pending_username    the username the browser submitted
 *   ea_pending_eligible    whether that username resolved to an active,
 *                          not-locked-out local account -- unrelated to
 *                          who approves; this is account-status parity
 *                          with password login, not an identity check
 *   ea_challenge_id / _number / _expires_at   the live challenge
 *
 * Local per-username rate limiting and the one-pending-challenge cap are
 * NOT done in $_SESSION (an attacker gets a fresh session on every
 * attempt) -- see ernsauthSsoAttemptsClassEx (core/ernsauthSsoAttemptsClassEx.php)
 * and ernsauth_sso_attempts.yaml's own docblock for why a small DB table
 * backs those two checks instead.
 */
class ernsauthClass {
    // Tuned conservatively for a staff login flow (a handful of real users,
    // not a public signup form) -- an app that needs different numbers can
    // extend this class or open an issue rather than editing framework code.
    const RATE_LIMIT_MAX_ATTEMPTS = 5;
    const RATE_LIMIT_WINDOW_SECONDS = 600;

    private static $moduleEnabled = false;
    private static $configLoaded = false;
    private static $config = null;
    private static $client = null;

    // Called by core/modules/ernsauth_sso/ernsauth_sso.php's
    // register_ernsauth_sso_module() -- never call this directly from app
    // code; list `ernsauth_sso` under config/settings.info.yaml's
    // `modules:` block instead, same convention as every other module.
    static function enable(): void {
        self::$moduleEnabled = true;
    }

    static function isEnabled(): bool {
        return self::$moduleEnabled && self::getConfig() !== null;
    }

    // `config/ernsauth.php` -- gitignored, outside the web root, returning
    // a plain array (never define()s -- see CLIENT-INTEGRATION.md):
    //   return ['sso_api_url' => 'https://auth.example.com/sso-api.php',
    //           'api_key'     => '...'];
    // A missing file, a file that doesn't return an array, or a blank
    // sso_api_url/api_key all resolve to "unconfigured" (null) rather than
    // a fatal error -- same fail-closed posture as Recaptcha::verify()'s
    // `not_configured` path.
    static function getConfig(): ?array {
        if (self::$configLoaded) return self::$config;
        self::$configLoaded = true;

        if (!defined('__APPDIR__')) return null;
        $path = __APPDIR__ . '/config/ernsauth.php';
        if (!file_exists($path)) return null;

        $cfg = require $path;
        if (is_array($cfg) && !empty($cfg['sso_api_url']) && !empty($cfg['api_key'])) {
            self::$config = $cfg;
        }
        return self::$config;
    }

    // Vendored at `lib/ernsauth/` via `git clone -b stable`, outside the
    // web root -- see CLIENT-INTEGRATION.md, "Vendor the client library".
    // Missing vendor directory behaves the same as missing config: null,
    // never a fatal require error.
    static function getClient(): ?ErnsAuthClient {
        if (self::$client !== null) return self::$client;

        $config = self::getConfig();
        if ($config === null) return null;

        if (!class_exists('ErnsAuthClient')) {
            $vendorPath = __APPDIR__ . '/lib/ernsauth/client/ErnsAuthClient.php';
            if (!file_exists($vendorPath)) return null;
            require_once $vendorPath;
        }

        self::$client = new ErnsAuthClient($config['sso_api_url'], $config['api_key']);
        return self::$client;
    }

    /*
     * Step ①-③ of the username-prompt variant: validate the submitted
     * username locally, create (or reuse) the challenge. Returns the
     * *same* {challenge_id, challenge_number, expires_at} shape whether or
     * not $username resolved to a real, eligible account -- see the
     * "Identical response whether the username resolves or not" row in
     * CLIENT-INTEGRATION.md's security table. Only ever returns an
     * {error: ...} shape for conditions that have nothing to do with
     * whether the username exists (disabled feature, rate limit,
     * ErnsAuth unreachable). No identity is "pinned" here to check later
     * -- see this file's top docblock, "NO POST-APPROVAL IDENTITY CHECK".
     */
    static function startChallenge(string $username, string $clientIp, string $userAgent): array {
        if (!self::isEnabled()) {
            return ['error' => 'disabled'];
        }

        $username = trim($username);
        if ($username === '') {
            return ['error' => 'invalid_request'];
        }

        $attempt = ernsauthSsoAttemptsClassEx::checkAndRecordAttempt(
            $username, $clientIp, self::RATE_LIMIT_MAX_ATTEMPTS, self::RATE_LIMIT_WINDOW_SECONDS
        );
        if ($attempt['action'] === 'blocked') {
            return ['error' => 'rate_limited'];
        }

        // Eligibility folds "no such account" together with "disabled" /
        // "expired" / "locked out" into one boolean, on purpose -- a
        // different response for each would be a free username-enumeration
        // oracle. Respects the same opt-in switches login_post() itself
        // does (LoginSecurityClass), so SSO never enforces a stricter
        // policy than password login until an app has actually turned
        // those on.
        $us = usersClassEx::getUserAccount($username);
        $eligible = false;
        if ($us) {
            $disabled = LoginSecurityClass::$enforceAccountStatus
                && (!$us->getactive() || $us->getexpired());
            $lockedOut = LoginSecurityClass::$enforceLockout
                && ((int)$us->getwrongpasscount() >= LoginSecurityClass::$maxWrongPassCount);
            $eligible = !$disabled && !$lockedOut;
        }

        if ($attempt['action'] === 'reuse') {
            $challengeId = $attempt['challenge_id'];
            $challengeNumber = $attempt['challenge_number'];
            $expiresAt = $attempt['expires_at'];
        } else {
            $client = self::getClient();
            if (!$client) {
                return ['error' => 'disabled'];
            }
            try {
                // Shown verbatim on the approver's Pending Logins card as
                // a courtesy ("Claiming to be ...") so they can
                // sanity-check it -- this app doesn't check it against
                // anything either (see this file's top docblock). Purely
                // for the human approving it to catch "that's not me".
                $challenge = $client->createChallenge($clientIp, $userAgent, $username);
            } catch (RuntimeException $e) {
                error_log('ernsauth createChallenge failed: ' . $e->getMessage());
                return ['error' => 'upstream_unavailable'];
            }
            $challengeId = $challenge['challenge_id'];
            $challengeNumber = (int)$challenge['challenge_number'];
            $expiresAt = (int)$challenge['expires_at'];

            ernsauthSsoAttemptsClassEx::recordChallenge($username, $challengeId, $challengeNumber, $expiresAt);
        }

        $_SESSION['ea_pending_username'] = $username;
        $_SESSION['ea_pending_eligible'] = $eligible;
        $_SESSION['ea_challenge_id'] = $challengeId;
        $_SESSION['ea_challenge_number'] = $challengeNumber;
        $_SESSION['ea_challenge_expires_at'] = $expiresAt;

        return [
            'challenge_id' => $challengeId,
            'challenge_number' => $challengeNumber,
            'expires_at' => $expiresAt,
        ];
    }

    // Step ④-⑤: poll from the browser's own loop. Clears the locally
    // tracked pending challenge on any terminal non-success status so a
    // "new request" click isn't stuck reusing a challenge ErnsAuth will
    // never approve again.
    static function poll(): array {
        if (!self::isEnabled()) {
            return ['status' => 'not_found'];
        }

        $username = $_SESSION['ea_pending_username'] ?? null;
        $challengeId = $_SESSION['ea_challenge_id'] ?? null;
        if (!$username || !$challengeId) {
            return ['status' => 'not_found'];
        }

        $client = self::getClient();
        if (!$client) {
            return ['status' => 'not_found'];
        }

        try {
            $poll = $client->pollChallenge($challengeId);
        } catch (RuntimeException $e) {
            error_log('ernsauth pollChallenge failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }

        if (in_array($poll['status'] ?? '', ['rejected', 'expired', 'not_found'], true)) {
            ernsauthSsoAttemptsClassEx::clearChallenge($username, $poll['status']);
        }

        return $poll;
    }

    /*
     * Step ⑥ -- exchanges the auth code and signs in the LOCAL account for
     * the pending username, once ErnsAuth confirms a real approval exists
     * for this challenge. Deliberately does NOT check *which* ErnsAuth
     * identity approved it -- see this file's top docblock, "NO
     * POST-APPROVAL IDENTITY CHECK". Returns ['user' => usersClass] on
     * success (the LOCAL account, not the ErnsAuth identity -- the caller
     * logs this in), or ['error' => ...] on any failure. Never creates a
     * session on its own; the caller
     * (core/modules/ernsauth_sso/ernsauth_sso.php) does that via
     * $kernel->loginUser(), same as login_post().
     */
    static function finish(string $authCode): array {
        if (!self::isEnabled()) {
            return ['error' => 'disabled'];
        }

        $username = $_SESSION['ea_pending_username'] ?? null;
        $eligible = $_SESSION['ea_pending_eligible'] ?? false;
        if (!$username || $authCode === '') {
            return ['error' => 'invalid_request'];
        }

        $client = self::getClient();
        if (!$client) {
            return ['error' => 'disabled'];
        }

        try {
            $result = $client->exchangeCode($authCode);
        } catch (RuntimeException $e) {
            error_log('ernsauth exchangeCode failed: ' . $e->getMessage());
            // Not a failed login attempt by the user -- don't touch the
            // lockout counter, just drop the pending state.
            ernsauthSsoAttemptsClassEx::clearChallenge($username, 'upstream_error');
            self::clearSession();
            return ['error' => 'upstream_unavailable'];
        }

        // exchangeCode() succeeding is proof ErnsAuth actually recorded a
        // real approval for this specific challenge -- that's all that's
        // checked here now. $eligible was computed in startChallenge(),
        // before the challenge existed, and is about local account
        // status/lockout only (password-login parity), not identity.
        if (!$eligible) {
            self::rejectAttempt($username, 'ineligible_account');
            return ['error' => 'account_not_found'];
        }

        $us = usersClassEx::getUserAccount($username);
        if (!$us) {
            // Defensive only -- $eligible is never true unless
            // getUserAccount() just resolved this same username in
            // startChallenge(). An account deleted mid-flow is the only
            // realistic way to reach this.
            self::rejectAttempt($username, 'account_missing');
            return ['error' => 'account_not_found'];
        }

        // Not enforced against anything, but worth a paper trail: which
        // ErnsAuth account actually clicked approve for this ZPMS login.
        error_log('ernsauth sso approved for ' . $username . ' by ernsauth identity ' . (string)($result['username'] ?? '?'));

        ernsauthSsoAttemptsClassEx::clearChallenge($username, 'matched');
        $us->setwrongpasscount(0);
        $us->update();
        self::clearSession();

        return ['user' => $us];
    }

    private static function rejectAttempt(string $username, string $outcome): void {
        ernsauthSsoAttemptsClassEx::clearChallenge($username, $outcome);

        $us = usersClassEx::getUserAccount($username);
        if ($us) {
            $us->setwrongpasscount($us->getwrongpasscount() + 1);
            $us->update();
        }

        error_log('ernsauth sso ' . $outcome . ' for ' . $username);
        self::clearSession();
    }

    private static function clearSession(): void {
        unset(
            $_SESSION['ea_pending_username'],
            $_SESSION['ea_pending_eligible'],
            $_SESSION['ea_challenge_id'],
            $_SESSION['ea_challenge_number'],
            $_SESSION['ea_challenge_expires_at']
        );
    }
}
