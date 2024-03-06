<?php

// extend YAML generated classes to add some functionality

class usersClassEx extends usersClass {
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

    static function getUserAccount( $uname ) {
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $sql = "SELECT * FROM users WHERE uname=:uname";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            $rclass = new usersClass( "users");
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);

    }
}

// class patientsClassEx extends patientsClass {
// }

class appointmentsClassEx extends appointmentsClass {
    static function getAppointmentsForPatient($pguid) {
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $sql = "SELECT * FROM appointments WHERE pguid=:pguid ORDER BY adate";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":pguid", $pguid, PDO::PARAM_STR);
        $st->execute();

        $list = array();        
        while( $row = $st->fetch() ) {
            $rclass = new appointmentsClass( "appointments" );
            $rclass->loadFields( $row );
            $list[] = $rclass;
        }

        return ($list);
    }
}