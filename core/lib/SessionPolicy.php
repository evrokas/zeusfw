<?php

/*
 * Session lifetime policy: a sliding idle logout, a hard cap from login, and a longer
 * "keep me signed in" session. Opt-in: nothing here does anything until the app sets
 * `session_idle_minutes:` in its config (see Kernel::boot()), so every other app on the
 * framework keeps its current behaviour.
 *
 *   session_idle_minutes   idle limit of an ordinary session, sliding (reset by real requests)
 *   session_max_hours      hard cap of an ordinary session from login (default 12)
 *   remember_me_days       a "remember me" session has no idle limit and lasts this long
 *                          (the existing setting; default 10)
 *   session_warn_seconds   how long before the end the browser warns (default 120)
 *   session_passive_routes route names that do NOT count as activity (polling); session_status
 *                          is always passive
 *
 * Session data (server side only; nothing here is read from a request):
 *   zfw_login_at   login time           zfw_ttl       allowed age in seconds
 *   zfw_last_seen  last real request    zfw_remember  1 for a "remember me" session
 *
 * The server decides when a session ends. The browser banner (module session_timer) is a courtesy.
 */

/** A config value reduced to a scalar (getConfig() answers [] for an unset key, a list when two files set it). */
function zeusfw_session_cfg(string $key) {
    global $kernel;
    if (!isset($kernel)) return null;
    $v = $kernel->getConfig($key);
    if (is_array($v)) $v = $v ? end($v) : null;
    return $v;
}

function zeusfw_session_cfg_int(string $key, int $default, int $min, int $max): int {
    $v = zeusfw_session_cfg($key);
    if (!is_numeric($v)) return $default;
    return max($min, min($max, (int)$v));
}

/** The policy in seconds, or null when the app has not turned it on. */
function zeusfw_session_policy(): ?array {
    $idleMin = zeusfw_session_cfg('session_idle_minutes');
    if (!is_numeric($idleMin) || (int)$idleMin < 1) return null;
    return [
        'idle' => max(1, min(1440, (int)$idleMin)) * 60,
        'cap' => zeusfw_session_cfg_int('session_max_hours', 12, 1, 720) * 3600,
        'remember' => zeusfw_session_cfg_int('remember_me_days', 10, 1, 365) * 86400,
        'warn' => zeusfw_session_cfg_int('session_warn_seconds', 120, 10, 900),
    ];
}

/** Writes the lifetime fields of a session that has just logged in. */
function zeusfw_session_stamp(bool $remember): void {
    $p = zeusfw_session_policy();
    if (!$p) return;
    $now = time();
    $_SESSION['zfw_login_at'] = $now;
    $_SESSION['zfw_last_seen'] = $now;
    $_SESSION['zfw_remember'] = $remember ? 1 : 0;
    $_SESSION['zfw_ttl'] = $remember ? $p['remember'] : $p['cap'];
}

/** The allowed age of this session: a missing or out-of-range value falls back to the short cap. */
function zeusfw_session_ttl(array $p): int {
    $ttl = $_SESSION['zfw_ttl'] ?? null;
    $max = !empty($_SESSION['zfw_remember']) ? $p['remember'] : $p['cap'];
    if (!is_int($ttl) || $ttl < 60 || $ttl > $max) return $p['cap'];
    return $ttl;
}

/** 'cap', 'idle' or null (still valid). */
function zeusfw_session_expired(array $p, int $now): ?string {
    $at = $_SESSION['zfw_login_at'] ?? null;
    if (!is_int($at) || $at > $now) return 'cap';
    if ($now - $at > zeusfw_session_ttl($p)) return 'cap';
    if (empty($_SESSION['zfw_remember'])) {
        $last = $_SESSION['zfw_last_seen'] ?? $at;
        if (!is_int($last)) $last = $at;
        if ($now - $last > $p['idle']) return 'idle';
    }
    return null;
}

/**
 * Seconds left, and what ends the session first: ['left' => int, 'limit' => 'idle'|'cap'|'none'].
 * Does not touch the session.
 */
function zeusfw_session_seconds_left(array $p, int $now): array {
    $at = $_SESSION['zfw_login_at'] ?? $now;
    $leftCap = zeusfw_session_ttl($p) - ($now - (is_int($at) ? $at : $now));
    if (!empty($_SESSION['zfw_remember'])) {
        return ['left' => max(0, $leftCap), 'limit' => 'cap'];
    }
    $last = $_SESSION['zfw_last_seen'] ?? $at;
    $leftIdle = $p['idle'] - ($now - (is_int($last) ? $last : $now));
    return $leftIdle < $leftCap
        ? ['left' => max(0, $leftIdle), 'limit' => 'idle']
        : ['left' => max(0, $leftCap), 'limit' => 'cap'];
}

/** Seconds until the hard cap, i.e. the most a renewal could ever leave. */
function zeusfw_session_cap_left(array $p, int $now): int {
    $at = $_SESSION['zfw_login_at'] ?? $now;
    return max(0, zeusfw_session_ttl($p) - ($now - (is_int($at) ? $at : $now)));
}

/** Marks a real request. Never called for polling. */
function zeusfw_session_touch(): void {
    if (isset($_SESSION['user']) && zeusfw_session_policy()) {
        $_SESSION['zfw_last_seen'] = time();
    }
}

/**
 * Called by Kernel::boot() right after the session starts: ends a session that ran out, and
 * stamps one that predates the policy (it then counts from now).
 */
function zeusfw_session_enforce(): void {
    global $kernel;
    $p = zeusfw_session_policy();
    if (!$p || empty($_SESSION['user'])) return;
    if (!isset($_SESSION['zfw_login_at'])) {
        zeusfw_session_stamp(false);
        return;
    }
    $why = zeusfw_session_expired($p, time());
    if ($why === null) return;

    $user = (string)$_SESSION['user'];
    $remembered = !empty($_SESSION['zfw_remember']);
    zeusfw_audit_event('session_expired', ['username' => $user, 'reason' => $why]);
    if ($remembered) {
        // The remember-me cookie would simply log the same device straight back in.
        $cookie = filter_input(INPUT_COOKIE, 'zeusfwrememberme');
        if ($cookie && ($parsed = userTokensClassEx::parse_token($cookie))) {
            userTokensClassEx::delete_by_selector($parsed[0]);
        }
        zeusfw_clear_rememberme_cookie();
    }
    // Keep the PHP session (so the message below survives) but drop the identity and every stamp.
    $_SESSION = [];
    session_regenerate_id(true);
    $kernel->addStatus('warning', t('Your session has ended. Please sign in again.'));
}

/** Route names that are polling, not activity. */
function zeusfw_session_passive_routes(): array {
    global $kernel;
    $list = isset($kernel) ? $kernel->getConfig('session_passive_routes') : [];
    $out = ['session_status', 'session_ping'];   // the ping handler touches the session itself, after its CSRF check
    array_walk_recursive($list, function ($v) use (&$out) { $out[] = (string)$v; });
    return $out;
}

/** After the route is known: a real request restarts the idle clock. */
function zeusfw_session_touch_for_route($match): void {
    $name = is_array($match) ? (string)($match['_routename'] ?? '') : '';
    if ($name !== '' && in_array($name, zeusfw_session_passive_routes(), true)) return;
    zeusfw_session_touch();
}

// ------------------------------------------------------------------ endpoints

function zeusfw_session_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit();
}

/** GET /session/status -- read-only: never restarts the idle clock. */
function zeusfw_session_status($params = null) {
    $p = zeusfw_session_policy();
    if (!$p || empty($_SESSION['user'])) {
        zeusfw_session_json(['ok' => false, 'signed_in' => false, 'enabled' => (bool)$p], $p ? 401 : 200);
    }
    $s = zeusfw_session_seconds_left($p, time());
    zeusfw_session_json(['ok' => true, 'signed_in' => true, 'enabled' => true, 'left' => $s['left'], 'limit' => $s['limit'], 'cap_left' => zeusfw_session_cap_left($p, time()), 'remember' => !empty($_SESSION['zfw_remember'])]);
}

/** POST /session/ping -- the only call that restarts the idle clock (CSRF header required). */
function zeusfw_session_ping($params = null) {
    $p = zeusfw_session_policy();
    if (!$p || empty($_SESSION['user'])) {
        zeusfw_session_json(['ok' => false, 'signed_in' => false], 401);
    }
    if (!csrfClass::verifyRequest()) {
        zeusfw_session_json(['ok' => false, 'error' => 'csrf'], 403);
    }
    zeusfw_session_touch();
    $s = zeusfw_session_seconds_left($p, time());
    zeusfw_session_json(['ok' => true, 'signed_in' => true, 'left' => $s['left'], 'limit' => $s['limit'], 'cap_left' => zeusfw_session_cap_left($p, time()), 'remember' => !empty($_SESSION['zfw_remember'])]);
}
