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

    static function userLoggedIn() {
        if(isset($_SESSION) && isset($_SESSION['user']))return true;
        else return false;
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
        // echopre("permissions list: " . print_r($permlist, 1));
        if(!$permlist) {
            echo "One or more roles in [" . print_r( $aperm, 1 ) . "] is not configured correct. Please check!";
            exit();
        }

        $uroles = $kernel->getUserRoles();
        // echopre("User access roles: " . print_r($uroles, 1));

        $pass = 0;
        if($uroles) {
            foreach($uroles as $urole) {
                if(in_array($urole, $permlist))$pass++;
            }
        }
        // echopre("Access: " . (($pass)?"granted":"denied"));
        return ($pass);
    }

    // checks user roles with permission $aperm,
    // return null if user is validated, otherwise
    // return error message to show
    static function require($aperm) {
        global $kernel;

        // check to see if a particular user has the necessery premissions
        // echo "<pre>Required permission: " . $aperm . "</pre>";
        // echopre("Required permission: $aperm");
        
        $uroles = $kernel->getUserRoles();
        if(!$uroles) {
            // echopre("User is not logged in");
        }
        // echopre("Roles `" . print_r( $uroles, 1 ) . "` for permissions " . print_r( self::$roles,1 ));
        $pass = 0;
        if(!empty($uroles)) {
            foreach($uroles as $urole) {
                // echopre("check role: $urole");
                switch( $urole ) {
                    case "authenticated":
                        $pass++;
                        break;
                    default:
                        if(in_array($aperm, self::$roles[ $urole ])){
                            // echopre("found permission: $aperm");
                            $pass++;
                        }
                }
            }
        } else {
            if($aperm === "anonymous")return null;
            // echopre("Empty roles for user");
        }
        if(!$pass) {
            // echo(" error_401() ");
            return error_401();
            // exit();
        }

        return null;
    }
}