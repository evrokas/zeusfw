<?php

/*
 * takes this approach from the beginning, starting from the login page
 * and proceeding implementing cookie, selector&validateor hashes, storage
 * in database, retrieving the hases and validating selector&validator
 * implementing another approach into the code is difficult and unnecessary
 */

function loginUser($uname, $uroles) {
    session_start();
    session_regenerate_id();
    $_SESSION['user'] = $uname;
    $urolelist = SecurityClass::processRoles($uroles);
    if(!$urolelist) {
        echo "<pre>User roles are initialized falsely. Please check!";
        exit();
    }
    $_SESSION['user_roles'] = $urolelist;
}

function login($params) {
    global $Renderer;

    return ($Renderer->render("login.zetem", ['action' => 'login']));
}

function login_post($params) {
    global $kernel;

    $user = userTokensClassEx::getUserByToken($_POST['token']);

    prelog("User (login_post): " . print_r(user, 1));

    if($user && password_verify($_POST['password'], $user->['password'])) {

    }

    $us = UsersClassEx::getUser($_POST['username'], hash('sha256', $_POST['password']));
    // echo "<pre>User: " . print_r( $us, 1 ) . "</pre>";
    if($us) {
        $kernel->loginUser($us->getuname(), $us->getroles());
        header('location: '.rel_url('/profile'));
        exit();
    }
}

function logout($params) {
    global $kernel;

    echo "Logout user<br>";

    $us = $kernel->getUserName();
    if($us) {
        $kernel->logoutUser();
    }
    header('location: '.rel_url('/'));
    exit();

}
