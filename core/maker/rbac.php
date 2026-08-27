<?php

/**
 * rbac:* commands -- list/add/edit/remove for an app's `users` table and
 * its `permissions`/`roles`/`role_permissions`/`user_roles` tables (the
 * RBAC schema introduced for zpms; see that app's web/rbac.php for the
 * full design rationale, including two real bugs in an older ad hoc
 * roles-as-a-string-column scheme that this replaces).
 *
 * Framework-generic, not zpms-specific: works against whichever app's
 * web/classes/yaml/ happens to define these 4 tables with these column
 * names, following the same convention every other maker.php command that
 * touches app data already uses (form_load()/message_new(), this same
 * directory) -- talks only to the maker-generated base entity classes
 * (rolesClass, permissionsClass, role_permissionsClass, user_rolesClass,
 * usersClass), never an app's own hand-authored *ClassEx extension
 * classes (those are app-specific, e.g. zpms's web/rbac.php -- a
 * different app vendoring this framework could have its own, differently
 * shaped, ClassEx layer, or none at all).
 *
 * Seeding default permissions/roles, or migrating an app's pre-existing
 * legacy data into this schema, is deliberately NOT here -- that stays
 * app-specific (see zpms's own bin/migrate_roles.php). These commands are
 * for day-to-day single-row management once the schema and any initial
 * data already exist.
 */

// -- bootstrap ----------------------------------------------------------

// Idempotent -- loads the app's generated entity classes (rolesClass,
// permissionsClass, ...) the same way form_load()/message_new() do
// (functions.php/messages.php, same directory: require(DIR::$fw .
// "/bootstrap.php")), but wrapped in ob_start()/ob_end_clean() to swallow
// every maker-generated class file's known leading-whitespace codegen
// quirk (confirmed present in every generated class -- see e.g.
// zpms's web/classes/roles.php) instead of letting it leak into this
// command's own output the way those two older commands do.
function rbac_bootstrap(): void {
    static $done = false;
    if ($done) {
        return;
    }

    if (!class_exists('rolesClass')) {
        ob_start();
        require(DIR::$fw . '/bootstrap.php');
        ob_end_clean();
    }

    if (!class_exists('rolesClass') || !class_exists('permissionsClass')
        || !class_exists('role_permissionsClass') || !class_exists('user_rolesClass')) {
        mlog("This app's web/classes/yaml/ has no roles/permissions/role_permissions/user_roles.yaml.");
        mlog('See zpms (web/classes/yaml/{roles,permissions,role_permissions,user_roles}.yaml) for a reference schema.');
        exit(-1);
    }

    $done = true;
}

// -- small shared helpers -------------------------------------------------

function rbac_parse_bool($value): int {
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y'], true) ? 1 : 0;
}

function rbac_resolve_role($value): ?rolesClass {
    if ($value === null || $value === '') {
        return null;
    }
    if (ctype_digit((string)$value)) {
        return rolesClass::sgetById((int)$value);
    }
    $st = dbConnection::getConnection()->prepare('SELECT * FROM roles WHERE name = :name');
    $st->bindValue(':name', (string)$value, PDO::PARAM_STR);
    $st->execute();
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $r = new rolesClass();
    $r->loadFields($row);
    return $r;
}

function rbac_resolve_permission($value): ?permissionsClass {
    if ($value === null || $value === '') {
        return null;
    }
    if (ctype_digit((string)$value)) {
        return permissionsClass::sgetById((int)$value);
    }
    $st = dbConnection::getConnection()->prepare('SELECT * FROM permissions WHERE name = :name');
    $st->bindValue(':name', (string)$value, PDO::PARAM_STR);
    $st->execute();
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $p = new permissionsClass();
    $p->loadFields($row);
    return $p;
}

function rbac_resolve_user($value): ?usersClass {
    if ($value === null || $value === '') {
        return null;
    }
    if (ctype_digit((string)$value)) {
        return usersClass::sgetById((int)$value);
    }
    $st = dbConnection::getConnection()->prepare('SELECT * FROM users WHERE uname = :uname');
    $st->bindValue(':uname', (string)$value, PDO::PARAM_STR);
    $st->execute();
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $u = new usersClass();
    $u->loadFields($row);
    return $u;
}

// Column-aligned plain-text table, printed via mlog() (the same helper
// the existing `help` command already uses for its own aligned output).
function rbac_print_table(array $headers, array $rows): void {
    if (!$rows) {
        mlog('(no rows)');
        return;
    }

    $widths = [];
    foreach ($headers as $i => $h) {
        $widths[$i] = mb_strlen((string)$h);
    }
    foreach ($rows as $row) {
        foreach (array_values($row) as $i => $cell) {
            $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string)$cell));
        }
    }

    // str_pad() pads by byte length, not display width -- Greek text
    // (multi-byte UTF-8) would come out under-padded since a 12-byte
    // string that mb_strlen() sees as 6 characters already looks "wide
    // enough" to str_pad(). Pad by hand using the same mb_strlen() the
    // width computation above already used.
    $renderRow = function (array $cells) use ($widths) {
        $out = '';
        foreach (array_values($cells) as $i => $cell) {
            $cell = (string)$cell;
            $out .= $cell . str_repeat(' ', max(0, $widths[$i] + 2 - mb_strlen($cell)));
        }
        return $out;
    };

    mlog($renderRow($headers));
    mlog(str_repeat('-', array_sum($widths) + 2 * count($widths)));
    foreach ($rows as $row) {
        mlog($renderRow($row));
    }
}

// Hidden (echo-disabled) STDIN read, so a plaintext password never has to
// touch the command line (shell history, `ps aux`) or the terminal
// scrollback. Unix-only (stty) -- matches this framework's deployment
// target; no Windows CLI support exists elsewhere in maker.php either.
function rbac_prompt_password(string $label): string {
    echo "$label: ";
    shell_exec('stty -echo');
    $password = trim((string)fgets(STDIN));
    shell_exec('stty echo');
    echo "\n";
    return $password;
}

function rbac_count_dependents(string $table, string $column, int $id): int {
    $st = dbConnection::getConnection()->prepare("SELECT COUNT(*) AS c FROM `$table` WHERE `$column` = :id");
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    return (int)$st->fetch()['c'];
}

// $table/$column are always literal strings from this file's own call
// sites below (never request/CLI-argument-controlled), so this is safe
// despite the direct interpolation.
function rbac_cascade_delete(string $table, string $column, int $id): int {
    $st = dbConnection::getConnection()->prepare("DELETE FROM `$table` WHERE `$column` = :id");
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    return $st->rowCount();
}

// -- users ----------------------------------------------------------------

function rbac_users_list(): void {
    rbac_bootstrap();
    $st = dbConnection::getConnection()->query(
        "SELECT u.id, u.name, u.uname, u.email, u.active, u.expired,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         GROUP BY u.id
         ORDER BY u.id"
    );
    $rows = [];
    while ($row = $st->fetch()) {
        $rows[] = [
            $row['id'], $row['name'], $row['uname'], $row['email'],
            $row['active'] ? 'yes' : 'no', $row['expired'] ? 'yes' : 'no',
            $row['roles'] !== null && $row['roles'] !== '' ? $row['roles'] : '-',
        ];
    }
    rbac_print_table(['ID', 'Name', 'Username', 'Email', 'Active', 'Expired', 'Roles'], $rows);
}

// Defaults active=1/expired=0 when omitted -- deliberately different from
// the web admin UI's (web/admin_crud.php) unchecked-by-default checkboxes:
// a CLI invocation with explicit flags is always a deliberate admin
// action, not a form whose checkbox could be left unchecked by accident,
// so there's no reason a CLI-created account should start out unusable.
function rbac_users_add(array $options): void {
    rbac_bootstrap();

    foreach (['name', 'email', 'uname'] as $required) {
        if (!isset($options[$required]) || trim((string)$options[$required]) === '') {
            mlog("--$required is required.");
            exit(-1);
        }
    }

    if (rbac_resolve_user($options['uname'])) {
        mlog("A user with uname '{$options['uname']}' already exists.");
        exit(-1);
    }

    $password = $options['password'] ?? '';
    if ($password === '') {
        $password = rbac_prompt_password('Password for new user');
        if ($password === '') {
            mlog('Password cannot be empty.');
            exit(-1);
        }
    }

    $u = new usersClass([
        'name' => $options['name'],
        'email' => $options['email'],
        'uname' => $options['uname'],
        'upass' => password_hash($password, PASSWORD_DEFAULT),
        'active' => isset($options['active']) ? rbac_parse_bool($options['active']) : 1,
        'expired' => isset($options['expired']) ? rbac_parse_bool($options['expired']) : 0,
        'wrongpasscount' => 0,
        // Legacy column -- not read by anything once an app has the
        // roles/permissions/role_permissions/user_roles schema this file
        // requires (see rbac_bootstrap()). Left blank on purpose, never a
        // real role assignment; use rbac:user-roles:add for that.
        'roles' => '',
    ]);
    $u->insert();

    mlog('Created user #' . $u->getid() . " ({$options['uname']}).");
}

function rbac_users_edit(int $id, array $options): void {
    rbac_bootstrap();

    $u = usersClass::sgetById($id);
    if (!$u) {
        mlog("No user #$id.");
        exit(-1);
    }

    if (isset($options['name'])) $u->setname($options['name']);
    if (isset($options['email'])) $u->setemail($options['email']);
    if (isset($options['uname'])) $u->setuname($options['uname']);
    if (isset($options['active'])) $u->setactive(rbac_parse_bool($options['active']));
    if (isset($options['expired'])) $u->setexpired(rbac_parse_bool($options['expired']));

    $password = $options['password'] ?? null;
    if ($password === null && isset($options['prompt-password'])) {
        $password = rbac_prompt_password('New password');
    }
    if ($password !== null && $password !== '') {
        $u->setupass(password_hash($password, PASSWORD_DEFAULT));
    }

    $u->update();
    mlog("Updated user #$id.");
}

function rbac_users_remove(int $id, bool $confirmed): void {
    rbac_bootstrap();

    $u = usersClass::sgetById($id);
    if (!$u) {
        mlog("No user #$id.");
        exit(-1);
    }

    $dependents = rbac_count_dependents('user_roles', 'user_id', $id);

    if (!$confirmed) {
        mlog("Would remove user #$id ({$u->getuname()}), cascading $dependents user_roles row(s).");
        mlog('Re-run with --yes to actually remove it.');
        return;
    }

    rbac_cascade_delete('user_roles', 'user_id', $id);
    $u->delete();
    mlog("Removed user #$id ({$u->getuname()}), cascaded $dependents user_roles row(s).");
}

// -- permissions ------------------------------------------------------------

function rbac_permissions_list(): void {
    rbac_bootstrap();
    $rows = [];
    foreach (permissionsClass::sgetAll() as $p) {
        $rows[] = [$p->getid(), $p->getname(), $p->getlabel()];
    }
    rbac_print_table(['ID', 'Name', 'Label'], $rows);
}

function rbac_permissions_add(array $options): void {
    rbac_bootstrap();

    foreach (['name', 'label'] as $required) {
        if (!isset($options[$required]) || trim((string)$options[$required]) === '') {
            mlog("--$required is required.");
            exit(-1);
        }
    }
    if (rbac_resolve_permission($options['name'])) {
        mlog("A permission named '{$options['name']}' already exists.");
        exit(-1);
    }

    $p = new permissionsClass([
        'guid' => guid(), 'cuser' => 'maker-cli', 'cdate' => getDBtime(),
        'name' => $options['name'], 'label' => $options['label'],
    ]);
    $p->insert();
    mlog('Created permission #' . $p->getid() . " ({$options['name']}).");
}

function rbac_permissions_edit(int $id, array $options): void {
    rbac_bootstrap();
    $p = permissionsClass::sgetById($id);
    if (!$p) {
        mlog("No permission #$id.");
        exit(-1);
    }
    if (isset($options['name'])) $p->setname($options['name']);
    if (isset($options['label'])) $p->setlabel($options['label']);
    $p->update();
    mlog("Updated permission #$id.");
}

function rbac_permissions_remove(int $id, bool $confirmed): void {
    rbac_bootstrap();
    $p = permissionsClass::sgetById($id);
    if (!$p) {
        mlog("No permission #$id.");
        exit(-1);
    }

    $dependents = rbac_count_dependents('role_permissions', 'permission_id', $id);

    if (!$confirmed) {
        mlog("Would remove permission #$id ({$p->getname()}), cascading $dependents role_permissions row(s).");
        mlog('Re-run with --yes to actually remove it.');
        return;
    }

    rbac_cascade_delete('role_permissions', 'permission_id', $id);
    $p->delete();
    mlog("Removed permission #$id ({$p->getname()}), cascaded $dependents role_permissions row(s).");
}

// -- roles ------------------------------------------------------------------

function rbac_roles_list(): void {
    rbac_bootstrap();
    $rows = [];
    foreach (rolesClass::sgetAll() as $r) {
        $rows[] = [$r->getid(), $r->getname(), $r->getlabel(), $r->getis_superuser() ? 'yes' : 'no'];
    }
    rbac_print_table(['ID', 'Name', 'Label', 'Superuser'], $rows);
}

function rbac_roles_add(array $options): void {
    rbac_bootstrap();

    foreach (['name', 'label'] as $required) {
        if (!isset($options[$required]) || trim((string)$options[$required]) === '') {
            mlog("--$required is required.");
            exit(-1);
        }
    }
    if (rbac_resolve_role($options['name'])) {
        mlog("A role named '{$options['name']}' already exists.");
        exit(-1);
    }

    $r = new rolesClass([
        'guid' => guid(), 'cuser' => 'maker-cli', 'cdate' => getDBtime(),
        'name' => $options['name'], 'label' => $options['label'],
        'is_superuser' => isset($options['superuser']) ? rbac_parse_bool($options['superuser']) : 0,
    ]);
    $r->insert();
    mlog('Created role #' . $r->getid() . " ({$options['name']}).");
}

function rbac_roles_edit(int $id, array $options): void {
    rbac_bootstrap();
    $r = rolesClass::sgetById($id);
    if (!$r) {
        mlog("No role #$id.");
        exit(-1);
    }
    if (isset($options['name'])) $r->setname($options['name']);
    if (isset($options['label'])) $r->setlabel($options['label']);
    if (isset($options['superuser'])) $r->setis_superuser(rbac_parse_bool($options['superuser']));
    $r->update();
    mlog("Updated role #$id.");
}

function rbac_roles_remove(int $id, bool $confirmed): void {
    rbac_bootstrap();
    $r = rolesClass::sgetById($id);
    if (!$r) {
        mlog("No role #$id.");
        exit(-1);
    }

    $rpDependents = rbac_count_dependents('role_permissions', 'role_id', $id);
    $urDependents = rbac_count_dependents('user_roles', 'role_id', $id);

    if (!$confirmed) {
        mlog("Would remove role #$id ({$r->getname()}), cascading $rpDependents role_permissions row(s) and $urDependents user_roles row(s).");
        mlog('Re-run with --yes to actually remove it.');
        return;
    }

    rbac_cascade_delete('role_permissions', 'role_id', $id);
    rbac_cascade_delete('user_roles', 'role_id', $id);
    $r->delete();
    mlog("Removed role #$id ({$r->getname()}), cascaded $rpDependents role_permissions row(s) and $urDependents user_roles row(s).");
}

// -- role_permissions (join table) ------------------------------------------

function rbac_role_permissions_list(): void {
    rbac_bootstrap();
    $st = dbConnection::getConnection()->query(
        'SELECT rp.id, r.name AS role_name, p.name AS permission_name
         FROM role_permissions rp
         JOIN roles r ON r.id = rp.role_id
         JOIN permissions p ON p.id = rp.permission_id
         ORDER BY rp.id'
    );
    $rows = [];
    while ($row = $st->fetch()) {
        $rows[] = [$row['id'], $row['role_name'], $row['permission_name']];
    }
    rbac_print_table(['ID', 'Role', 'Permission'], $rows);
}

function rbac_role_permissions_add(array $options): void {
    rbac_bootstrap();

    if (empty($options['role']) || empty($options['permission'])) {
        mlog('--role and --permission are required.');
        exit(-1);
    }
    $role = rbac_resolve_role($options['role']);
    $perm = rbac_resolve_permission($options['permission']);
    if (!$role) {
        mlog("No role matching '{$options['role']}'.");
        exit(-1);
    }
    if (!$perm) {
        mlog("No permission matching '{$options['permission']}'.");
        exit(-1);
    }

    $existing = dbConnection::getConnection()->prepare(
        'SELECT id FROM role_permissions WHERE role_id = :r AND permission_id = :p'
    );
    $existing->bindValue(':r', $role->getid(), PDO::PARAM_INT);
    $existing->bindValue(':p', $perm->getid(), PDO::PARAM_INT);
    $existing->execute();
    if ($row = $existing->fetch()) {
        mlog("Role '{$role->getname()}' already grants '{$perm->getname()}' (#{$row['id']}).");
        return;
    }

    $rp = new role_permissionsClass([
        'guid' => guid(), 'cuser' => 'maker-cli', 'cdate' => getDBtime(),
        'role_id' => $role->getid(), 'permission_id' => $perm->getid(),
    ]);
    $rp->insert();
    mlog("Granted '{$perm->getname()}' to role '{$role->getname()}' (#" . $rp->getid() . ').');
}

function rbac_role_permissions_edit(int $id, array $options): void {
    rbac_bootstrap();
    $rp = role_permissionsClass::sgetById($id);
    if (!$rp) {
        mlog("No role_permissions row #$id.");
        exit(-1);
    }

    if (isset($options['role'])) {
        $role = rbac_resolve_role($options['role']);
        if (!$role) {
            mlog("No role matching '{$options['role']}'.");
            exit(-1);
        }
        $rp->setrole_id($role->getid());
    }
    if (isset($options['permission'])) {
        $perm = rbac_resolve_permission($options['permission']);
        if (!$perm) {
            mlog("No permission matching '{$options['permission']}'.");
            exit(-1);
        }
        $rp->setpermission_id($perm->getid());
    }
    $rp->update();
    mlog("Updated role_permissions row #$id.");
}

function rbac_role_permissions_remove(int $id, bool $confirmed): void {
    rbac_bootstrap();
    $rp = role_permissionsClass::sgetById($id);
    if (!$rp) {
        mlog("No role_permissions row #$id.");
        exit(-1);
    }
    if (!$confirmed) {
        mlog("Would remove role_permissions row #$id. Re-run with --yes to actually remove it.");
        return;
    }
    $rp->delete();
    mlog("Removed role_permissions row #$id.");
}

// -- user_roles (join table) --------------------------------------------------

function rbac_user_roles_list(): void {
    rbac_bootstrap();
    $st = dbConnection::getConnection()->query(
        'SELECT ur.id, u.uname AS user_name, r.name AS role_name
         FROM user_roles ur
         JOIN users u ON u.id = ur.user_id
         JOIN roles r ON r.id = ur.role_id
         ORDER BY ur.id'
    );
    $rows = [];
    while ($row = $st->fetch()) {
        $rows[] = [$row['id'], $row['user_name'], $row['role_name']];
    }
    rbac_print_table(['ID', 'User', 'Role'], $rows);
}

function rbac_user_roles_add(array $options): void {
    rbac_bootstrap();

    if (empty($options['user']) || empty($options['role'])) {
        mlog('--user and --role are required.');
        exit(-1);
    }
    $user = rbac_resolve_user($options['user']);
    $role = rbac_resolve_role($options['role']);
    if (!$user) {
        mlog("No user matching '{$options['user']}'.");
        exit(-1);
    }
    if (!$role) {
        mlog("No role matching '{$options['role']}'.");
        exit(-1);
    }

    $existing = dbConnection::getConnection()->prepare(
        'SELECT id FROM user_roles WHERE user_id = :u AND role_id = :r'
    );
    $existing->bindValue(':u', $user->getid(), PDO::PARAM_INT);
    $existing->bindValue(':r', $role->getid(), PDO::PARAM_INT);
    $existing->execute();
    if ($row = $existing->fetch()) {
        mlog("User '{$user->getuname()}' already has role '{$role->getname()}' (#{$row['id']}).");
        return;
    }

    $ur = new user_rolesClass([
        'guid' => guid(), 'cuser' => 'maker-cli', 'cdate' => getDBtime(),
        'user_id' => $user->getid(), 'role_id' => $role->getid(),
    ]);
    $ur->insert();
    mlog("Assigned role '{$role->getname()}' to user '{$user->getuname()}' (#" . $ur->getid() . ').');
}

function rbac_user_roles_edit(int $id, array $options): void {
    rbac_bootstrap();
    $ur = user_rolesClass::sgetById($id);
    if (!$ur) {
        mlog("No user_roles row #$id.");
        exit(-1);
    }

    if (isset($options['user'])) {
        $user = rbac_resolve_user($options['user']);
        if (!$user) {
            mlog("No user matching '{$options['user']}'.");
            exit(-1);
        }
        $ur->setuser_id($user->getid());
    }
    if (isset($options['role'])) {
        $role = rbac_resolve_role($options['role']);
        if (!$role) {
            mlog("No role matching '{$options['role']}'.");
            exit(-1);
        }
        $ur->setrole_id($role->getid());
    }
    $ur->update();
    mlog("Updated user_roles row #$id.");
}

function rbac_user_roles_remove(int $id, bool $confirmed): void {
    rbac_bootstrap();
    $ur = user_rolesClass::sgetById($id);
    if (!$ur) {
        mlog("No user_roles row #$id.");
        exit(-1);
    }
    if (!$confirmed) {
        mlog("Would remove user_roles row #$id. Re-run with --yes to actually remove it.");
        return;
    }
    $ur->delete();
    mlog("Removed user_roles row #$id.");
}
