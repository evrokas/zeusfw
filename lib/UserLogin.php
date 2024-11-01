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
    userTokensClassEx::delete_user_token($uname);

    $days = 10;
    $expired_seconds = time() + 60 * 60 * 24 * $days;
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    $create = getDBtime();
    // prelog("expiry: $expiry\texpired_seconds: $expired_seconds");

    if(userTokensClassEx::insert_user_token($create, $uname, $selector, $hash_validator, $expiry)) {
        setcookie('zeusfwrememberme', $token, $expired_seconds);
        // prelog("setup cookie for future!");
    }
}

function login($params) {
    return (Renderer::render("login.zetem", ['action' => 'login']));
}

function login_post($params) {
    global $kernel;

    $us = UsersClassEx::getUser($_POST['username'], hash('sha256', $_POST['password']));
    // prelog("User: " . print_r( $us, 1 ));
    if($us) {

        echopre("try to login user ". $us->getuname() . " with rules: " . print_r($us->getroles()));
        
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
        header('location: ' . rel_url('/login'));
    }
}

function logout($params) {
    global $kernel;

    // echo "Logout user<br>";

    $us = $kernel->getUserName();
    if($us) {
        userTokensClassEx::delete_user_token($_SESSION['user']);
        if(isset($_COOKIE['zeusfwrememberme'])) {
            unset($_COOKIE['zeusfwrememberme']);
            setcookie('zeusfwrememberme', null, -1);
        }

        $kernel->logoutUser();
    }
    header('location: '.rel_url('/'));
    exit();
}
