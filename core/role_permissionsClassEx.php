<?php

class role_permissionsClassEx extends role_permissionsClass {
    // Every permission NAME (not id) a role grants directly -- empty for
    // an is_superuser role, which needs no rows here at all (see
    // rbacClass::isPermitted() in core/lib/Rbac.php).
    static function getPermissionNamesForRole(int $roleId): array {
        $sql = "SELECT p.name FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(":role_id", $roleId, PDO::PARAM_INT);
        $st->execute();

        $names = [];
        while ($row = $st->fetch()) {
            $names[] = $row['name'];
        }
        return $names;
    }
}
