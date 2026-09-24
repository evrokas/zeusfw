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
 * gating) does a plain role-identity check, not a permission-array lookup,
 * so neither bug above applies to it -- but as of 2026-09-24 it does call
 * back into this file for one thing: an is_superuser bypass, via
 * rbacClass::currentUserIsSuperuser() below. See that method's own
 * docblock and Security.php's own call site for why.
 */

class rbacClass {
    // Per-request cache for currentUserIsSuperuser() below -- see that
    // method's own docblock for why this exists.
    private static array $superuserCache = [];

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

    // Best-effort is_superuser check with no permission slug involved --
    // called from SecurityClass::userIsPermitted() (core/lib/Security.php)
    // so an is_superuser account bypasses role-identity access: checks
    // (nav-menu items, route-level access:, region/module access:) the
    // same way it already bypasses this class's own isPermitted(). Kept
    // as its own method rather than folded into isPermitted()'s existing
    // loop -- that method already does an equivalent check inline as part
    // of walking $role['permissions'], and touching that already-verified
    // RBAC code for an unrelated caller is unnecessary risk.
    //
    // Unlike isPermitted(), this must never throw. userIsPermitted() (and
    // therefore this method, transitively) is the one access-control
    // mechanism used by every app on this framework, including apps that
    // never adopted RBAC at all -- no roles/permissions/user_roles tables,
    // no app-level usersClassEx::getUserAccount() (the method isPermitted()
    // itself already depends on, and which is an app-supplied extension,
    // not part of this framework -- see zeusfw's own CLAUDE.md, "Kernel::
    // boot() adopted by mweb and zweb", confirming at least two apps on
    // this framework have no RBAC/login setup whatsoever). A missing table
    // or a missing app-level class extension must degrade to "not a
    // superuser", not crash every single access:-gated page render on an
    // app that was never part of RBAC to begin with.
    //
    // Memoized per username for the lifetime of the request: unlike
    // isPermitted() (called once per protected handler), userIsPermitted()
    // can call this once per access:-gated nav item/submenu/region on a
    // single page render -- without this cache that's a real multiplication
    // of the same two queries below, not just a theoretical one.
    static function currentUserIsSuperuser(): bool {
        global $kernel;

        $uname = $kernel->getUserName();
        if (!$uname) {
            return false;
        }
        if (array_key_exists($uname, self::$superuserCache)) {
            return self::$superuserCache[$uname];
        }

        $result = false;
        try {
            $user = UsersClassEx::getUserAccount($uname);
            if ($user) {
                foreach (user_rolesClassEx::getRolesForUser((int)$user->getid()) as $role) {
                    if (!empty($role['is_superuser'])) {
                        $result = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $result = false;
        }

        return self::$superuserCache[$uname] = $result;
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
