<?php

// security class for roles and permissions

class SecurityClass {
    static private $roles = array();
    static private $permissions = array();
    static function init($aroles) {
        self::$roles = $aroles;
        // echo "<pre>Roles<br>"; echo print_r( self::$roles); echo "</pre>";
        // echo "<pre><br>"; echo print_r( self::$roles); echo "</pre>";
    }

    function userHasRole() {
        if(isset($_SESSION) && isset($_SESSION['user']) && isset($_SESSION['user_roles'])) {
            // session exists && user is authenticated && user roles is setup
            echo "<pre>"; echo print_r( $_SESSION['user_roles']); echo "</pre>";
            foreach($this->roles as $role) {

            }
        }
    }

    static function processRoles($arole) {
        // validates if roles are ok and returns an array of them
        $arole = trim( $arole );
        $arole = str_replace(['  '],[' '], $arole);
        $rolelist = explode( ' ', $arole );
        // echo "<pre>processRoles: " . print_r( $rolelist, 1 ) . "</pre>";
        $loop = 0; $ok = 0;
        while($loop < count($rolelist)) {
            foreach(self::$roles as $rlkey => $rldata) {
                // echo "<pre>$loop: Checking " . $rlkey . " <> " . $rolelist[$loop] . "</pre>";
                if($rlkey == $rolelist[ $loop ]) {
                    $ok = 1;
                    break;
                }
            }
            if($ok) {
                $loop++;
                $ok = 0;
                continue;
            }

            echo "<pre>Role $rolelist[$loop] is not valid</pre>";
            return (null);
        }

      return ($rolelist);
    }

    static function userIsPermitted($aperm) {
        global $kernel;
        $permlist = self::processRoles($aperm);
        if(!$permlist) {
            echo "One or more roles in [" . print_r( $aperm, 1 ) . "] is not configured correct. Please check!";
            exit();
        }

        // echo "<pre>Access: " . print_r( $permlist, 1 ) . "</pre>"; 
        $uroles = $kernel->getUserRoles();
        // echo "<pre>User: " . print_r( $uroles, 1 ) . "</pre>";

        $pass = 0;
        foreach($uroles as $urole) {
            if(in_array($urole, $permlist))$pass++;
        }
        // echo "<pre>User can pass $pass</pre>";
        return ($pass);
    }
    static function require($aperm) {
        global $kernel;
        // check to see if a particular user has the necessery premissions
        // echo "<pre>Required permission: " . $aperm . "</pre>";
        $uroles = $kernel->getUserRoles();
        // echo "<pre>Roles " . print_r( $uroles, 1 ) . " for permissions " . print_r( self::$roles,1 ) . "</pre>";
        $pass = 0;
        foreach($uroles as $urole) {
            if(in_array($aperm, self::$roles[ $urole]))$pass++;
        }
        // echo "<pre>User can pass $pass</pre>";
    }
}