<?php

class UserModule extends moduleClass {
    function render($params = array()) {
        global $kernel;
        $loggedin = false;
        $user = '';
        $us = $kernel->getUserName();
        // echo "<pre>Username: $us</pre>";

        if(($us=$kernel->getUserName())) {
            $user = $us;
            $loggedin = true;
        }
        return $this->RenderTemplate(['loggedin' => $loggedin, 'name' => $user]);
    }
}


function register_userblock_module() {
    global $kernel;

    $kernel->registerModule( new UserModule('userblock', 'userblock.zetem'));
}
