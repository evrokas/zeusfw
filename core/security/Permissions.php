<?php

require_once(__DIR__ . '/../bootstrap.php' );

class PermsClass extends permissionsListClass {
    private $perms;

    function __construct() {
        // initialize permissions data
        $perms = null;
    }
    
    
    function loadPermissions() {
        self::$perms = self::getAll();
        print_r( self::$perms );
    }
}