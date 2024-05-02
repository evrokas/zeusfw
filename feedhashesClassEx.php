<?php

class feedhashesClassEx extends feedhashesClass {
    
    static function gethashByGuid($aguid) {
        // get hash 
        global $AppDBConnection;
        global $kernel;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return null;
            }
        }

        $sql = "SELECT * FROM feed_hashes WHERE guid=:guid LIMIT 1";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":guid", $aguid, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            // prelog(print_r($row, 1));
            $rclass = new feedhashesClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }
}
