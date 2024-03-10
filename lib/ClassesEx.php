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

class patientsClassEx extends patientsClass {
    static function search($aterm, $ascope = array()) {
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $aterm = trim($aterm);
        $aterm = str_replace(['  '], [' '], $aterm);
        $terms = explode(' ', $aterm);
        if(!count($terms))return null;

        // so seearch for words in array
        // build string
        $srch = implode('% ', $terms);
        $srch .= '%';

        $srch2 = '% '.$srch;

        // error_log("\nSearch string: ".iconv('utf-8', 'iso-8859-7',$srch) ."\n");

        $sql = "SELECT * FROM patients WHERE (pname LIKE :term) OR (pname LIKE :term2)";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":term", $srch, PDO::PARAM_STR);
        $st->bindValue(":term2", $srch2, PDO::PARAM_STR);
        $st->execute();

        $list = array();        
        while( $row = $st->fetch() ) {
            $rclass = new patientsClass();
            $rclass->loadFields( $row );
            $list[] = $rclass;
        }

        return ($list);
    }

}

class appointmentsClassEx extends appointmentsClass {
    static function getAppointmentsForPatient($pguid, $order = 'ASC') {
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $sql = "SELECT * FROM appointments WHERE pguid=:pguid ORDER BY adate $order";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":pguid", $pguid, PDO::PARAM_STR);
        // $st->bindValue(":order", $order, PDO::PARAM_STR);

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