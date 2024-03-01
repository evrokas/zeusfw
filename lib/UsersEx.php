<?php

// extend UsersClass to add some functionality

class UsersClassEx extends usersClass {
    static function getUser( $uname, $upass ) {

        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $sql = "SELECT * FROM users WHERE uname=:uname AND upass=:upass";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);
        $st->bindValue(":upass", $upass, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            $rclass = new usersClass( "users");
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }
}