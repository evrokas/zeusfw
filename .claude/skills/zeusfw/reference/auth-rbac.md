# Login, sessions, RBAC, admin, audit

## Login

- Core handlers `login`, `login_post`, `logout` (`core/lib/UserLogin.php`); the app routes to them.
- `LoginSecurityClass`: lockout after `$maxWrongPassCount` wrong passwords (`users.wrongpasscount`);
  account status (`active`/`expired`). CSRF via `csrf_field()` in forms, enforced by `boot()` options.
- Session roles: `zeusfw_app_resolve_user_roles(usersClass $u)` (core default reads `user_roles`; falls back to
  the legacy `users.roles` column). `Kernel::loginUser()` adds `authenticated`. A role name not in the app's
  `roles:` block makes login **fail hard** (`echo ...; exit`) — fix the data (`/admin/user_roles`), not the code.
- Remember me: cookie `zeusfwrememberme` (selector:validator, validator rotates on use), `user_tokens` table,
  lifetime `remember_me_days` (default 10). Not tied to IP or User-Agent. A client sending no User-Agent gets no
  cookie (NOT NULL column).
- Session policy (`core/lib/SessionPolicy.php`, opt-in via `session_idle_minutes`): idle logout, hard cap
  `session_max_hours`, remember-me sessions last `remember_me_days`; `session_timer` module shows warning/label.

## RBAC (`core/lib/Rbac.php`)

Tables (core YAMLs): `roles` (`is_superuser`), `permissions` (slug `name`, `label`), `role_permissions`,
`user_roles`. Apps define their own slug constants and seed (zpms: `web/rbac.php`, `web/rbac_seed.php`).

| Call | Use |
|---|---|
| `rbacClass::require('slug')` | in handlers: `null` if allowed, else rendered 401 — `if (($e = rbacClass::require(X))) return $e;` |
| `rbacClass::isPermitted('slug')` | bool, re-queries the DB, may throw on apps without RBAC tables |
| `rbacClass::currentUserHasPermission('slug')` | bool, never throws, memoised — for display decisions |
| `rbacClass::currentUserIsSuperuser()` | bool, never throws |
| `SecurityClass::userIsPermitted('roleA roleB')` | role-name check used by every `access:` (superuser passes) |

Don't use `SecurityClass::require()` for anything new (it lets every logged-in user through).
Framework slugs: `ZEUSFW_PERM_MANAGE_USERS` = `users-manage` (admin CRUD), `ZEUSFW_PERM_LIVE_EDIT` = `content-live-edit`.

## Admin CRUD (`core/modules/admin/admin_crud.php`)

`/admin/{users,permissions,roles,role_permissions,user_roles}` list/new/edit/delete, gated by
`packagesClass::isEnabled('admin_user_crud')` (turn off with `disabled_packages: [admin_user_crud]`) and
`users-manage`. The users edit form has a Roles checklist. Entity definitions: `zeusfw_admin_entity_defs()`.

## Audit hook

Define `zeusfw_app_audit_event(string $event, array $context)` to record: `login_success`, `login_failed`,
`login_refused`, `account_locked`, `logout`, `login_remembered`, `session_expired`, `admin_created`,
`admin_updated` (`fields` = names only), `admin_deleted`, `admin_user_roles_changed` (`added`/`removed`).
Context keys: `username` (for `login_failed` it is what was typed — sanitise), `method`, `reason`, `entity`,
`entity_id`, `fields`, `added`, `removed`. Exceptions in the hook are caught and logged. Core stores nothing.

## ErnsAuth SSO (`core/lib/ErnsAuth.php`, module `ernsauth_sso`)

Opt-in: list `ernsauth_sso` in `modules:` **and** provide `config/ernsauth.php` + vendored client in
`lib/ernsauth/`. Mandatory-username number-matching flow; identical responses for real and unknown usernames;
local rate limit table `ernsauth_sso_attempts`. zpms deliberately has **no** post-approval identity check
(single trusted approver) — for any other app, re-decide that (see ernsauth `CLIENT-INTEGRATION.md`).
