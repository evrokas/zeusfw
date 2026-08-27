<?php

class user_rolesClassEx extends user_rolesClass {
    // Every role this user has, each carrying its own is_superuser flag
    // and granted-permission-name list -- exactly what
    // rbacClass::isPermitted() needs, in one query per role (there are
    // only ever a handful of roles per user, so this isn't worth
    // collapsing into a single join). $allPermissionSlugs is supplied by
    // the caller (an app-specific list -- see rbacClass::isPermitted())
    // rather than hardcoded here, since this file has no knowledge of any
    // particular app's permission vocabulary.
    static function getRolesForUser(int $userId, array $allPermissionSlugs = []): array {
        $sql = "SELECT r.* FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :user_id";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(":user_id", $userId, PDO::PARAM_INT);
        $st->execute();

        $roles = [];
        while ($row = $st->fetch()) {
            $roles[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'label' => $row['label'],
                'is_superuser' => (bool)$row['is_superuser'],
                'permissions' => $row['is_superuser']
                    ? $allPermissionSlugs
                    : role_permissionsClassEx::getPermissionNamesForRole((int)$row['id']),
            ];
        }
        return $roles;
    }

    static function roleNamesForUser(int $userId): array {
        return array_column(self::getRolesForUser($userId), 'name');
    }

    // Idempotent -- safe for a double-submit/re-run to never create a
    // duplicate assignment.
    static function assignRole(int $userId, int $roleId, string $cuser): void {
        $existing = dbConnection::getConnection()->prepare(
            "SELECT id FROM user_roles WHERE user_id=:user_id AND role_id=:role_id"
        );
        $existing->bindValue(":user_id", $userId, PDO::PARAM_INT);
        $existing->bindValue(":role_id", $roleId, PDO::PARAM_INT);
        $existing->execute();
        if ($existing->fetch()) {
            return;
        }

        $ur = new user_rolesClass([
            'guid' => guid(),
            'cuser' => $cuser,
            'cdate' => getDBtime(),
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
        $ur->insert();
    }

    static function removeRole(int $userId, int $roleId): void {
        $st = dbConnection::getConnection()->prepare(
            "DELETE FROM user_roles WHERE user_id=:user_id AND role_id=:role_id"
        );
        $st->bindValue(":user_id", $userId, PDO::PARAM_INT);
        $st->bindValue(":role_id", $roleId, PDO::PARAM_INT);
        $st->execute();
    }
}
