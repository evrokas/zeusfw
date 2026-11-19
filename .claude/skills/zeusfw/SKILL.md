---
name: zeusfw
description: How to build and change apps on the ZeusFW PHP framework (zpms, erweb, mweb, zweb, zprime, zgeotrack, neurolog, medscribe) and the framework itself — YAML entities and maker.php, routes/config layers, .zetem templates, modules, RBAC/access, update.sh, known traps. Use whenever a task touches an app with a `fw` -> zeusfw link, `web/core`, `config/settings.info.yaml`, `web/classes/yaml/`, `.zetem` files or `core/`.
---

# ZeusFW

Shared PHP framework at `/var/www/shtml/apps/zeusfw` (git: github `evrokas/zeusfw`). Each app links it as
`<app>/fw -> zeusfw` and `<app>/web/core -> ../fw/core`. **Anything under `core/` or `bin/` affects every app**:
keep framework changes additive and opt-in (a config key, a `function_exists()` hook, a module listed in
`modules:`), and say which apps you could not test.

## App layout

```
<app>/
  config/            db.php (credentials, gitignored), site.info.yaml, settings.info.yaml, *.php.in templates
  web/index.php      requires, hooks (zeusfw_app_*), $kernel->boot([...]), then route handler functions
  web/classes/yaml/  one YAML per table  -> maker -> web/classes/sql/*.sql + web/classes/*.php (generated)
  web/ClassesEx.php  hand-written extensions (<table>ClassEx), e.g. usersClassEx::getUserAccount()
  web/templates/     .zetem templates (page/, content/, ...)
  web/modules/       app modules (core ones: fw/core/modules/)
  web/content/       feeders (*.feeder.yaml + item .yml) loaded by maker feed:*
  web/cache/         compiled templates — clear after editing an *included* template
  bin/post-update.sh optional, run by fw/bin/update.sh at the end
```

Framework: `core/kernel/Kernel.php` (config, boot, render), `core/router/{Router,Request,ErrorHandlers}.php`,
`core/templates/ZETEMTemplate.php` (`Renderer`), `core/db/dbal.php` (base class + `dbQuery`),
`core/lib/*.php` (Security, Rbac, UserLogin, WebForms, FormElement, Modules, SessionPolicy, ...; each needs its own
`require_once` in `core/bootstrap.php` — no autoloader), `core/maker/` (code generator), `core/modules/`,
`core/config/zeusfw.info.yaml` (framework routes + first config layer), `bin/{init.sh,update.sh}`.

## Everyday commands (run from the app root)

| Task | Command |
|---|---|
| Regenerate classes/SQL, apply schema diffs, load content | `fw/bin/update.sh` (asks) — unattended: `fw/bin/update.sh --non-interactive --yes`; preview: `--dry-run`; `--help` |
| Only the app's tables | `cd web/classes && php ../core/maker/maker.php spill:sql:all && php ../core/maker/maker.php spill:class:all && php ../core/maker/maker.php update:bootstrap` |
| Schema drift of one table | `cd web/classes && php ../core/maker/maker.php diff:sql <table>.yaml` (`diff:sql:all` for all) |
| Load a feeder | `cd web/content && php ../core/maker/maker.php --name=<x>.feeder.yaml feed:load` |
| Load a webform into the DB | `cd web/classes && php ../core/maker/maker.php form:load yaml/<form>.yaml` |
| RBAC from the shell | `maker.php rbac:roles:list`, `rbac:user-roles:add --user=<uname> --role=<name>`, ... (`maker.php help`) |

`--yes` in update.sh never applies `*-diff` or `--clean-feeds` (they change/empty live tables): pass
`--core-diff=yes`/`--web-diff=yes` explicitly. maker options work as `--name=value` or `--name value`, anywhere on the line.

## Rules that save time

- **Config layers merge with `array_merge_recursive()`**: a scalar set in two files becomes an array; lists
  concatenate. Put each scalar key in exactly one file. Booleans to switch off things are lists
  (`disabled_packages:`), never `{x: {enabled: false}}`.
- **Two access vocabularies**: `access:` (routes, regions, modules, menu items) = space-separated *role names*,
  checked by `SecurityClass::userIsPermitted()` (is_superuser passes). `permission:` (menu items only) and
  `rbacClass::require('<slug>')` in handlers = RBAC *permission slugs*. A hidden menu item protects nothing —
  handlers must call `rbacClass::require()` themselves. An unknown role name in `access:` echoes and `exit()`s.
- **Generated files are never edited**: change the YAML, then regenerate. Constraints (e.g. `UNIQUE`) go in the
  YAML `type:` string, not in the `.sql`.
- **Extension points are plain global functions** probed with `function_exists()`: `zeusfw_app_boot()`,
  `zeusfw_app_resolve_user_roles()`, `zeusfw_app_audit_event()`, `zeusfw_app_backup_permission()`,
  `zeusfw_app_live_edit_permission()`. Define them in the app, before `boot()`.
- **Globals**: handlers and modules use `global $kernel, $Request, $router;` (`$content_response` too). Query
  parameters: `$Request->getParam('page')` / `getParams()` (prefer over `$_GET`, which carries the route as a key).
- **Test against a real MySQL/MariaDB** — several framework bugs only showed up there. Use the app's own test
  suite if it has one (zpms: `bin/run_tests.sh`).

## Where to look next

Read only the file the task needs:
- `reference/entities-maker.md` — entity YAML (field types, `@guid`/`@cdate`/..., forms, views), generated class
  API, `dbQuery`, maker commands, `diff:sql`, feeders, update.sh/init.sh.
- `reference/config-routing.md` — config files and macros, routes and handlers, regions/structure, menu, modules
  and `modconf`, sessions, analytics, `Kernel::boot()` options.
- `reference/templates-modules.md` — ZETEM syntax and filters, `Renderer`, writing a module, assets, forms.
- `reference/auth-rbac.md` — login, remember-me, RBAC tables and checks, admin CRUD, ErnsAuth SSO, audit hook.
- `reference/gotchas.md` — known traps and past bugs that still matter.

History of every framework change (why things are the way they are): `docs/CHANGELOG.md` in zeusfw. Search it
by keyword; don't read it whole.

When a framework change alters how something is *used*, update the matching reference file here in the same
commit, and add the dated story to `docs/CHANGELOG.md`.
