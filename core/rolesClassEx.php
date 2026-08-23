<?php

class rolesClassEx extends rolesClass {
    static function sgetByName(string $name): ?rolesClass {
        $sql = "SELECT * FROM roles WHERE name=:name";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(":name", $name, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if ($row) {
            $rclass = new rolesClass();
            $rclass->loadFields($row);
            return $rclass;
        }
        return null;
    }
}
