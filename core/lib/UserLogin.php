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
    
    userTokensClassEx::delete_user_token($uname, $remoteip, $useragent);

    $days = 10;
    $expired_seconds = time() + 60 * 60 * 24 * $days;
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    $create = getDBtime();
    // prelog("expiry: $expiry\texpired_seconds: $expired_seconds");

    if(userTokensClassEx::insert_user_token($create, $uname, $selector, $hash_validator, $remoteip, $useragent, $expiry)) {
        setcookie('zeusfwrememberme', $token, $expired_seconds, '/', null, true, true);
        // setcookie('zeusfwrememberme', $token, ['samesite'=>'true']);
        // prelog("setup cookie for future!");
    }
}

function login($params) {
    global $kernel;
    $ernsauth_enabled = false;
    $sso_url = '';
    if (defined('__APPDIR__')) {
        $ernsauth_config = __APPDIR__ . '/config/ernsauth.php';
        if (file_exists($ernsauth_config)) {
            require_once $ernsauth_config;
            $ernsauth_enabled = defined('ERNSAUTH_ENABLED') && ERNSAUTH_ENABLED;
            if ($ernsauth_enabled) {
                $sso_url = $kernel->rel_url('/sso.php');
            }
        }
    }
    return (Renderer::render("login.zetem", [
        'action'          => 'login',
        'ernsauth_enabled' => $ernsauth_enabled,
        'sso_url'         => $sso_url,
    ]));
}

function login_post($params) {
    global $kernel;


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
        $remoteip = $_SERVER['REMOTE_ADDR'];
        $useragent = $_SERVER['HTTP_USER_AGENT'];
    
        userTokensClassEx::delete_user_token($_SESSION['user'], $remoteip, $useragent);
        if(isset($_COOKIE['zeusfwrememberme'])) {
            unset($_COOKIE['zeusfwrememberme']);
            setcookie('zeusfwrememberme', '', time()-1, '/');
        }

        $kernel->logoutUser();
    }
    header('location: '.rel_url('/'));
    exit();
}
