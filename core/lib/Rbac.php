<?php

/**
 * Framework-level RBAC (role-based access control) permission-check engine.
 *
 * Works against the schema in core/classes/yaml/{roles,permissions,
 * role_permissions,user_roles}.yaml and the *ClassEx classes in
 * core/ClassExFW.php. An app using this needs no code of its own beyond
 * defining its own permission-slug vocabulary (named constants + a seed
 * script -- see zpms's web/rbac.php / web/rbac_seed.php for a reference)
 * and calling rbacClass::require()/isPermitted() at its own permission
 * checkpoints.
 *
 * Originally built app-specific inside zpms (as zpms_require_permission()/
 * zpms_user_has_permission()) to work around two now-fixed bugs in this
 * same file's *previous* incarnation (SecurityClass::require(), core/lib/
 * Security.php): any role literally named "authenticated" auto-passed
 * every check (Kernel::loginUser() always appends "authenticated" to every
 * session), and 'administrator' => 'all' being a plain string rather than
 * an array made in_array() against it a fatal TypeError on PHP 8.
 * SecurityClass::userIsPermitted() (route-level access: checks, nav-menu
 * gating) is untouched -- it does a plain role-identity check, not a
 * permission-array lookup, so neither bug applies to it. Moved here so
 * every app gets a correct implementation without needing its own copy.
 */

class rbacClass {
    // Always re-queries the database for the current user's actual
    // roles/permissions rather than trusting $_SESSION (the role list
    // Kernel::loginUser() builds always carries an extra "authenticated"
    // entry -- see this file's own docblock above), so this is unaffected
    // by whatever the session role array happens to contain.
    static function isPermitted(string $permission): bool {
        global $kernel;

        $uname = $kernel->getUserName();
        if (!$uname) {
            return false;
        }

        $user = UsersClassEx::getUserAccount($uname);
        if (!$user) {
            return false;
        }

        foreach (user_rolesClassEx::getRolesForUser((int)$user->getid()) as $role) {
            if ($role['is_superuser']) {
                return true;
            }
            if (in_array($permission, $role['permissions'], true)) {
                return true;
            }
        }
        return false;
    }

    // Drop-in replacement for SecurityClass::require($permission) at any
    // app call site -- same return contract (null on success, a rendered
    // 401 page on failure). Callers must actually check the return value
    // (`if (($errmsg = rbacClass::require(...))) return $errmsg;`) for
    // this to do anything.
    static function require(string $permission): ?string {
        if (self::isPermitted($permission)) {
            return null;
        }
        return error_401();
    }
}

// The framework's own canonical permission slug for "can manage users,
// roles, and permissions" -- gates core/modules/admin/'s generic admin
// CRUD UI. Kept as the exact string value zpms's own (now-retired)
// ZPMS_PERM_USERS_MANAGE constant already used and seeded, so adopting
// this needs zero data migration for an app that already had that
// permission row.
if (!defined('ZEUSFW_PERM_MANAGE_USERS')) {
    define('ZEUSFW_PERM_MANAGE_USERS', 'users-manage');
}

// Opt-in extension point zeusfw core's login_post() (core/lib/
// UserLogin.php) checks for via function_exists() before falling back to
// the legacy users.roles column -- same pattern already used for
// csrf_field() (core/lib/FormElement.php). Must stay a plain global
// function (not a class method): UserLogin.php calls function_exists()
// on this exact name. Returns a space-separated role-name string, the
// exact shape usersClass::getroles() itself returns, so Kernel::
// loginUser() (which just splits on spaces) needs no changes. Returns
// null -- "no opinion, use the legacy column" -- for a user with zero
// user_roles rows, rather than an empty string: SecurityClass::
// processRoles() treats an empty role list as invalid input and
// Kernel::loginUser() hard-exits the request on that, so this is a real
// safety net for an account that hasn't been migrated to user_roles yet.
if (!function_exists('zeusfw_app_resolve_user_roles')) {
    function zeusfw_app_resolve_user_roles(usersClass $user): ?string {
        $names = user_rolesClassEx::roleNamesForUser((int)$user->getid());
        return $names ? implode(' ', $names) : null;
    }
}
