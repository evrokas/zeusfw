<?php

class permissionsClassEx extends permissionsClass {
    static function sgetByName(string $name): ?permissionsClass {
        $sql = "SELECT * FROM permissions WHERE name=:name";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(":name", $name, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if ($row) {
            $rclass = new permissionsClass();
            $rclass->loadFields($row);
            return $rclass;
        }
        return null;
    }
}
