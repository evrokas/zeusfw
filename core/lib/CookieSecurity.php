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
    session_start([
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => zeusfw_request_is_https(),
        'use_strict_mode' => true,
    ]);
}
