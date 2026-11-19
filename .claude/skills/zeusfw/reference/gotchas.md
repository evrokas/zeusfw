# Known traps

Each line: the trap, then what to do. Details: search `docs/CHANGELOG.md` in zeusfw.

- **Scalar config in two layers becomes an array** (`array_merge_recursive`). Set it in one file; use lists for
  switches.
- **Editing an included `.zetem` doesn't show** — compiled cache is per top-level file. `rm -rf web/cache/*`.
- **Generated class or `.sql` edited by hand** — lost on the next `spill:*`. Change the YAML.
- **`UNIQUE`/index needed** — put it in the YAML `type:` (`varchar(64) UNIQUE`).
- **`diff:sql` keeps reporting the same column** — a comparison bug, not drift. Known normalisations: int width,
  `tinyint(1)` = boolean, whitespace, UNIQUE. Fix in `core/maker/functions.php` `syncTableWithYAML()`.
- **MySQL 8 `utf8mb4_*` collations are PAD SPACE** — `=` ignores trailing spaces, even `COLLATE utf8mb4_bin`.
  For exact byte comparison use `CAST(col AS BINARY) = CAST(? AS BINARY)`.
- **Module present in two `modules: path:` folders** — first path wins now, but delete the stale copy
  (`web/modules/<name>/`) after a module moves into core.
- **`access:` takes role names, not permission slugs** — an unknown name kills the request. Use `permission:` on
  menu items, `rbacClass::require()` in handlers.
- **`SecurityClass::require()`** passes every logged-in user. Use `rbacClass::require()` / `userIsPermitted()`.
- **`|` inside `{{ }}`** is a filter separator. Compute bitmasks/ORs in PHP.
- **Unescaped `{{ $x }}`** — escape user data with `| e` or `{{{ }}}`.
- **Ad-hoc module and `attach_library()`** — too late to add assets; emit `<link>`/`<script>` yourself.
- **`*/` in a CSS comment** ends it early and invalidates the whole stylesheet.
- **`$_GET` contains the route as a key** under the `.htaccess` rewrite — use `$Request->getParam()`.
- **Test `php -S` router** must replicate Apache `[QSA]` (`QUERY_STRING = path&query`), or query params vanish.
- **`php -S -d auto_prepend_file`** is ignored for the router script on PHP 8.3 — a test harness relying on it
  may hit the real `config/db.php`. Define the test DB in the router.
- **Test server stdout/stderr as unread pipes** hang after 64 KB — write them to files.
- **Leading whitespace before `<?php`** in an included file leaks output (breaks XML/JSON/headers).
- **`Renderer::init()`** needs all its arguments; `boot()` does it right.
- **Login fails with "Role X is not valid"** — the account holds a role missing from `roles:`; reassign the role.
- **Checkbox columns** — the element submits `1`; unchecked submits nothing.
- **Analytics tables** (`analytics`, `pageanalytics`) are off by default; old rows may hold personal data from URLs.
- **Same core checkout, many apps** — run each affected app's tests, or say which you couldn't.
