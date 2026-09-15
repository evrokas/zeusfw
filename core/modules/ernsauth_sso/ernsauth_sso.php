<?php

/*
 * Route handlers for the ernsauth_sso package -- the browser-facing side
 * of ernsauthClass (core/lib/ErnsAuth.php), which does all the actual
 * work. These three handlers are the "your app's own endpoint, which
 * forwards to ErnsAuth server-side" piece CLIENT-INTEGRATION.md describes
 * (ernsauth repo) -- the browser never talks to ErnsAuth directly, and the
 * API key never reaches client-side JS.
 *
 * Registered in core/config/zeusfw.info.yaml (routes are always merged in,
 * same as admin_user_crud), but only *functional* once an app has both
 * listed `ernsauth_sso` under its own config/settings.info.yaml `modules:`
 * block (which is what actually calls register_ernsauth_sso_module()
 * below, via core/lib/Modules.php's registerModules()) and provided a
 * valid config/ernsauth.php -- ernsauthClass::isEnabled() is checked first
 * in every handler, exactly like packagesClass::isEnabled() is checked
 * first in every admin_crud handler.
 */

function register_ernsauth_sso_module() {
    ernsauthClass::enable();
}

function ernsauth_sso_start($params) {
    header('Content-Type: application/json; charset=utf-8');

    if (!ernsauthClass::isEnabled()) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit();
    }

    // A JSON POST body carries no <form>-derived csrf_token field, so the
    // browser side sends it as X-CSRF-Token instead -- csrfClass::
    // verifyRequest() already reads both, same two-channel convention
    // this app's own ernsauth vendor and docarc both use.
    if (!csrfClass::verifyRequest()) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_csrf_token']);
        exit();
    }

    $username = trim((string)($_POST['username'] ?? ''));
    if ($username === '') {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request']);
        exit();
    }

    // The END USER's own IP/UA, not this server's -- see
    // CLIENT-INTEGRATION.md's own warning about this exact mistake
    // (wrong device shown to the approver, every user landing in one
    // supersede bucket).
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $result = ernsauthClass::startChallenge($username, $clientIp, $userAgent);
    if (isset($result['error']) && $result['error'] === 'rate_limited') {
        http_response_code(429);
    }
    echo json_encode($result);
    exit();
}

function ernsauth_sso_poll($params) {
    header('Content-Type: application/json; charset=utf-8');

    if (!ernsauthClass::isEnabled()) {
        http_response_code(404);
        echo json_encode(['status' => 'not_found']);
        exit();
    }

    echo json_encode(ernsauthClass::poll());
    exit();
}

function ernsauth_sso_exchange($params) {
    global $kernel;

    header('Content-Type: application/json; charset=utf-8');

    if (!ernsauthClass::isEnabled()) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit();
    }

    if (!csrfClass::verifyRequest()) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_csrf_token']);
        exit();
    }

    $authCode = (string)($_POST['auth_code'] ?? '');
    $result = ernsauthClass::finish($authCode);

    if (isset($result['error'])) {
        echo json_encode($result);
        exit();
    }

    // Identity confirmed -- log the LOCAL account in exactly the way
    // login_post() does (core/lib/UserLogin.php), reusing the same RBAC
    // role-resolution extension point.
    $us = $result['user'];
    $uroles = function_exists('zeusfw_app_resolve_user_roles')
        ? (zeusfw_app_resolve_user_roles($us) ?? $us->getroles())
        : $us->getroles();

    $kernel->loginUser($us->getuname(), $uroles);

    echo json_encode(['success' => true, 'redirect' => rel_url('/profile')]);
    exit();
}
