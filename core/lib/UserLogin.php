<?php

/*
 * takes this approach from the beginning, starting from the login page
 * and proceeding implementing cookie, selector&validateor hashes, storage
 * in database, retrieving the hases and validating selector&validator
 * implementing another approach into the code is difficult and unnecessary
 * 
 * based on web pages: 
 * https://www.phptutorial.net/php-tutorial/php-remember-me/
 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
 * 
 */


 function setup_rememberme(string $uname) {
    prelog("Setup selector:validator");
    [$selector, $validator, $token] = userTokensClassEx::generate_tokens();
    prelog("Tokens: $selector:$validator");

    $remoteip = $_SERVER['REMOTE_ADDR'];
    $useragent = $_SERVER['HTTP_USER_AGENT'];

    // Every remember-me login inserts a fresh row rather than
    // delete-then-insert keyed on (uname, remoteip, useragent) -- that old
    // keying lost a device's remembered session on an IP change (mobile
    // networks, dynamic IP) and prevented more than one row per device+IP
    // combo from existing at once. N logins now simply means N rows, each
    // independently listable/revocable from the Devices section of /profile.
    // Expired rows are still cleaned up proactively by
    // userTokensClassEx::remove_expired_user_tokens() (Maintenance, runs on
    // every request), so this doesn't accumulate indefinitely.

    $days = 10;
    $expired_seconds = time() + 60 * 60 * 24 * $days;
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    $create = getDBtime();
    // prelog("expiry: $expiry\texpired_seconds: $expired_seconds");

    if(userTokensClassEx::insert_user_token($create, $uname, $selector, $hash_validator, $remoteip, $useragent, $expiry)) {
//        setcookie('zeusfwrememberme', $token, $expired_seconds, '/', null, true, true);
        // setcookie('zeusfwrememberme', $token, ['samesite'=>'true']);
        zeusfw_set_rememberme_cookie($token, $expired_seconds);
        // prelog("setup cookie for future!");
    }
}

function login($params) {
    return (Renderer::render("login.zetem", ['action' => 'login']));
}

function login_post($params) {
    global $kernel;

    // Opt-in only -- see csrfClass::$enforceLogin's docblock. An app that
    // hasn't called enableLoginProtection() (and put csrf_field() into its
    // own login.zetem) reaches this unchanged, exactly as before this
    // check existed.
    if(csrfClass::$enforceLogin && !csrfClass::verifyRequest()) {
        $kernel->addStatus('error', 'Μη έγκυρο token ασφαλείας (CSRF). Παρακαλώ προσπαθήστε ξανά.');
        header('location: ' . rel_url('/login'));
        exit();
    }

    $us = UsersClassEx::getUser($_POST['username'], hash('sha256', $_POST['password']));
    // prelog("User: " . print_r( $us, 1 ));
    if($us) {
        // user exists
        echopre("try to login user ". $us->getuname() . " with rules: " . print_r($us->getroles()));
        
        // ask kernel to login user
        $kernel->loginUser($us->getuname(), $us->getroles());

        if(isset($_POST['rememberme'])) {
            setup_rememberme($us->getuname());
        }

        // reset wrong password counter
        $us->setwrongpasscount(0);
        $us->update();

        header('location: '.rel_url('/profile'));
        exit();
    } else {
        // check for account with 'username' exists, if yes, then increase wrong password counter
        // for future reference
        $us = UsersClassEx::getUserAccount( $_POST['username'] );
        if($us) {
            $us->setwrongpasscount( $us->getwrongpasscount() + 1);
            $us->update();
        }

        $kernel->addStatus('warning', 'Username and password does not match, or do not exist');
        error_log('login failed for ' . $_POST["username"]);
        header('location: ' . rel_url('/login'));
        exit();
    }
}

function logout($params) {
    global $kernel;

    // echo "Logout user<br>";

    $us = $kernel->getUserName();
    if($us) {
        // Deletes only the row for *this* device's cookie (matched by
        // selector, not the old uname+remoteip+useragent guess) -- logging
        // out on one device must not revoke other devices' remember-me
        // tokens. Use the Devices list on /profile to revoke other devices.
        $cookie = filter_input(INPUT_COOKIE, 'zeusfwrememberme');
        if($cookie) {
            $parsed = userTokensClassEx::parse_token($cookie);
            if($parsed) {
                userTokensClassEx::delete_by_selector($parsed[0]);
            }
        }
        zeusfw_clear_rememberme_cookie();

        $kernel->logoutUser();
    }
    header('location: '.rel_url('/'));
    exit();
}
