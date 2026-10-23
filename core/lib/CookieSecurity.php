<?php

// Shared cookie/session hardening for the "remember me" feature -- see
// ZPMS's config/settings.info.yaml routes 'login'/'logout' and
// core/lib/UserLogin.php for the callers. Mirrors DocArc's
// includes/session.php + helpers.php cookie-hardening approach.

function zeusfw_request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return false;
}

function zeusfw_set_rememberme_cookie(string $token, int $expiresAt): void {
    setcookie('zeusfwrememberme', $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => zeusfw_request_is_https(),
    ]);
}

function zeusfw_clear_rememberme_cookie(): void {
    setcookie('zeusfwrememberme', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => zeusfw_request_is_https(),
    ]);
    unset($_COOKIE['zeusfwrememberme']);
}

function zeusfw_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $opts = [];
    // Optional (the app's config): its own session cookie name and its own session folder, so
    // sessions are not swept by the distribution's default 24-minute cleanup (Debian/Ubuntu
    // delete files in the shared folder older than php.ini's gc_maxlifetime) and two apps on one
    // host cannot overwrite each other's cookie. Falls back to PHP's defaults if the folder
    // cannot be created.
    global $kernel;
    if (isset($kernel)) {
        $name = zeusfw_session_cfg('session_name');
        if (is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $name)) {
            session_name($name);
        }
        $path = zeusfw_session_cfg('session_save_path');
        $policy = zeusfw_session_policy();
        if (is_string($path) && $path !== '') {
            $dir = rtrim(Kernel::macroToFsPath($path), '/');
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            if (is_dir($dir) && is_writable($dir)) {
                $opts['save_path'] = $dir;
                // the longest session the policy allows, with PHP's own cleanup on
                $opts['gc_maxlifetime'] = $policy ? max($policy['remember'], $policy['cap']) + 86400 : 86400;
                $opts['gc_probability'] = 1;
                $opts['gc_divisor'] = 100;
            } else {
                error_log('zeusfw: session_save_path ' . $dir . ' is not writable; using the default session folder');
            }
        }
    }
    session_start($opts + [
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => zeusfw_request_is_https(),
        'use_strict_mode' => true,
    ]);
}
