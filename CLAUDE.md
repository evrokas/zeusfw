# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with the ZeusFW framework
itself. ZeusFW is the shared PHP framework behind mweb, ZPMS, zweb, and erweb: YAML-driven routes,
`.zetem` templates (`core/templates/ZETEMTemplate.php`), `maker.php`-generated entity classes, and a
feeder content pipeline. Changes here are framework-wide - anything touched under `core/` is shared
by every app that vendors this checkout, so keep changes additive/backward-compatible unless a specific
app's maintainer has confirmed the blast radius.

No dedicated documentation existed for this repo before the entry below; add further dated entries here
as the framework evolves, following the same convention `erweb/CLAUDE.md` already uses.

## `core/lib/*.php` - framework-level utility classes

Plain static-method PHP classes (not `moduleClass` subclasses - those render page-region UI blocks like
nav/footer, wired via `settings.info.yaml`'s `structure:` map, and don't fit a general-purpose utility
concern). Each is a single file under `core/lib/`, loaded via one explicit `require_once` line in
`core/bootstrap.php`'s existing list - there's no autoloader/directory scan, so a new file here needs its
own `require_once` line added by hand. File-naming convention: PascalCase filename (`ContentPage.php`) ->
lowercase-`Class`-suffixed class name (`contentPageClass`).

### `Recaptcha.php` / `recaptchaClass` (2026-08-19)

Server-side verification helper for Google reCAPTCHA v3 (or any provider using the same token/secret +
siteverify-shaped request/response contract). Added for erweb's 3 contact forms - see erweb's own
`CLAUDE.md` entry ("reCAPTCHA v3 human verification on the 3 contact forms") for the app-level wiring.
This is the **first outbound HTTP call anywhere in ZeusFW's history** (confirmed via exhaustive grep for
`curl_init`/`file_get_contents(...http...)` across mweb/ZPMS/zweb/erweb before this change - zero prior
matches) - keep that in mind if extending this file: nothing else in this codebase has precedent for
timeout/retry/error-handling conventions around a real network call.

Two static methods:
- `verify(?string $token, ?string $secretKey, ?string $remoteIp = null): array` - POSTs to
  `https://www.google.com/recaptcha/api/siteverify` via cURL (plain, unrestricted - no proxy config baked
  in, since this framework itself never assumes a particular network environment). Returns
  `['success' => bool, 'score' => ?float, 'action' => ?string, 'error' => ?string]`. **Fails closed on
  every error path** - this guards a security-relevant decision, so an unconfigured secret, a missing
  token, or a network failure must never be mistaken for a passed check: `missing_token` (empty/null
  token), `not_configured` (empty/null/`TODO`-prefixed secret - lets a caller ship with a real
  TODO-placeholder config value and still fail safely instead of silently allowing everything through),
  `request_failed: ...` (cURL error), `invalid_response` (non-array JSON decode). No app-specific config
  is baked into the class - the caller always supplies its own site/secret keys (see erweb's
  `erwebConfig.php` for the config-driven accessor pattern this pairs with).
- `isHuman(?string $token, ?string $secretKey, float $minScore = DEFAULT_SCORE_THRESHOLD, ?string
  $expectedAction = null, ?string $remoteIp = null): bool` - convenience wrapper applying a score
  threshold (`DEFAULT_SCORE_THRESHOLD = 0.5`, Google's own suggested default) and an optional expected-
  action match on top of `verify()`. Callers needing the raw score/action/error detail should call
  `verify()` directly instead.

Wired into `core/bootstrap.php` right after `ContentPage.php`'s own `require_once` line, matching the
existing `core/lib/*.php` load-order convention (no particular ordering dependency between the two -
placed there simply because both are small, general-purpose utility classes).

**Known limitation, not a bug**: this class's live network call was never end-to-end verified against
Google's real API from the sandbox this was developed in - that sandbox's outbound-HTTPS proxy returns
`403` on a CONNECT tunnel to `www.google.com:443`. What *was* verified: every fail-closed short-circuit
path (missing token, empty/TODO secret) with no network call attempted, and `php -l`. A real production
server with normal internet access should work correctly since the cURL call itself has no sandbox-specific
workaround baked in - but if a real deployment reports an unexpected failure mode from `verify()`, start by
confirming the exact cURL error in the `request_failed: ...` string rather than assuming this class's logic
is wrong.

## `core/lib/yaml_compat.php` - `yaml_emit_file()` gap fix (2026-08-19)

`yaml_compat.php` (the PyYAML-subprocess fallback for environments without `ext/yaml` - see its own
docblock for the full rationale) shimmed `yaml_parse_file()`/`yaml_parse()`/`yaml_emit()` but not
`yaml_emit_file()` - a real gap, found because `core/maker/maker.php`'s `generate_feed()` calls
`yaml_emit_file($output, $out, YAML_UTF8_ENCODING)` directly, so `feed:gen:yaml` fatals with
`Call to undefined function yaml_emit_file()` on any box relying on this fallback (confirmed: this
sandbox has no `ext/yaml`, and the PPA normally used to install it - `ppa:ondrej/php` - is blocked by
this sandbox's outbound-HTTPS proxy, `403 Forbidden`, same restriction documented on `Recaptcha.php`
above).

Added `yaml_emit_file()` as a thin wrapper around the already-working `yaml_emit()` (temp-file-plus-
rename write, matching `yaml_parse_file()`'s own atomic-write cache pattern) plus the two constants
`generate_feed()` references (`YAML_UTF8_ENCODING`, `YAML_ANY_ENCODING` - `$encoding` is accepted but
ignored, since `yaml_emit()`'s PyYAML fallback always emits UTF-8 regardless of what's asked for; this
is a documented no-op, not a silently-wrong parameter). Both guarded by `function_exists()`/`defined()`,
matching every other symbol in this file - a real `ext/yaml` install is completely unaffected, this is
purely additive.

Exercised end-to-end (not just unit-tested in isolation) by running `feed:gen:yaml` against all 13 of
erweb's feeders - see erweb's own `CLAUDE.md` entry, "Feeders now scaffold new content via
`feed:gen:yaml`", for the full story of what that uncovered and fixed on the erweb side.

## `core/maker/maker.php` - portable (relative) `schema:` paths in generated feeds (2026-08-20)

`generate_feed()` (`core/maker/maker.php:801`) writes a `schema:` field into every item `.yml` it
generates via `feed:gen:yaml`, pointing back at that table's own class-schema YAML - but the value it
computed was always whatever `$template` resolved to, which in practice is built from `schemadir:`'s
`@core`/`@app` macro substitution (`DIR::$fw`/`DIR::$app`) and is therefore always an absolute,
machine-specific path baked in from wherever the command happened to run. erweb (the only app so far
with real `sections:` trees in its feeders, see the entry above) worked around this with a manual `sed`
cleanup step on every generated file - annoying, and a trap for any other app adopting `feed:gen:yaml`
in the future.

Confirmed via direct read of every call site: exactly two functions ever read a `.yml`'s `schema:` field
back - `load_feed_data()` (`:1235`/`:1245`) and `sync_feed_fields()` (`:1412`) - and both pass the string
straight to `yaml_parse_file()`/`pathinfo()` with **no** resolution of their own, i.e. purely relative to
whatever the process's cwd happens to be at read time. The one universal invocation convention in this
environment, `bin/update.sh`, always `pushd`s into `<app>/web/content/` before running both
`feed:gen:yaml` and `feed:load` in the same script, for every app - so a `schema:` value relative to that
directory (`../classes/yaml/<table>.yaml`) is correct everywhere, matching what zpms's own committed
feeds already rely on via an explicit relative `--dir` override. (A separate, unrelated "schema" field -
the feeder-descriptor's own top-level `schema:` key, e.g. inside a `.feeder.yaml` - already does its own
independent `@core`/`@app` resolution in `print_feed_info()`/`clean_feed_data()`/
`generate_feed_from_yaml()` and is untouched by this change.)

Added `maker_relative_schema_path(string $target): string`, next to `mguid()` - `realpath()`-normalizes
both `$target` and `getcwd()`, then computes the relative form by counting the common path prefix and
walking `../` for the remainder. Using `realpath()` on both sides means it produces the correct result
regardless of whether `$target` started absolute or was already relative (e.g. zpms's own `--dir` case),
so no branching is needed for "already relative" input. `generate_feed()`'s `$arr += ['schema' =>
$template]` (`:801`) now reads `$arr += ['schema' => maker_relative_schema_path($template)]` - the only
line changed; every other call site was already confirmed cwd-relative-tolerant above.

**Verified against erweb** (not just unit logic): re-ran `feed:gen:yaml` for `erweb_procedures` and
`erweb_techniques` (26 + 3 existing items, all pre-existing files) - every file came out byte-identical
to before the fix (the merge logic in `generate_feed()` already preserves an existing file's other
top-level fields like `cmd`/`createdate`, and `schema:` was already relative from the old `sed` workaround,
so a correct fix reproduces the same value with no diff). Also generated one genuinely new leaf item from
scratch (added and then removed a throwaway `sections:` entry) and confirmed its freshly-written `schema:`
came out `../classes/yaml/erweb_techniques.yaml` with no absolute-path artifact at any point. `feed:load`
for both feeders showed "hashes same" on every row (zero unintended DB writes), and
`bin/check_integrity.php` stayed clean. zweb's 55 pre-existing `.yml` files with absolute, host-specific
`schema:` paths are a real, separate portability bug from this exact code path - deliberately untouched by
this fix (they're not regenerated unless someone explicitly re-runs `feed:gen:yaml` there, at which point
they'd now come out correctly relative instead of getting a fresh absolute path).

## `core/router/ErrorHandlers.php` - `error_403()`/`error_500()` + opt-in crash catch-all (2026-08-21)

At erweb's request (its own bare, English-only 404 page was the trigger - see erweb's `CLAUDE.md` for the
app-level styling that motivated this): the framework only shipped `error_404()`/`error_401()` before this
- no 403, no 500, and nothing anywhere that ever calls them automatically. Added the two missing HTTP
error functions plus a genuinely new capability: a way for a 500 to ever actually be reachable at all,
since nothing in this framework previously caught an uncaught exception or fatal error - a real crash just
produced a raw PHP error dump or a blank response.

**`error_403()`/`error_500()`** - identical shape to the existing two: `Renderer::render('403.zetem', ['error'
=> $errmsg])` / `Renderer::render('500.zetem', ...)`. New bare fallback templates,
`core/templates/errors/403.zetem`/`500.zetem`, matching `404.zetem`/`401.zetem`'s existing plain style (a
generic app that never overrides them gets the same minimal look for all 4 codes now, not just 2).

**`zeusfw_register_error_handlers(?string $cssPath = null): void`** - opt-in, not called anywhere in
`bootstrap.php` or automatically by anything in core; an app calls it once, itself, from its own
`index.php` (after `Renderer::init()`, since `error_500()` needs the renderer ready). Registers exactly
two things:
- `set_exception_handler()` for any uncaught `Throwable`.
- `register_shutdown_function()` checking `error_get_last()`, but **only** for the genuinely fatal types
  (`E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`/`E_USER_ERROR`) - these already terminate the
  script regardless, a shutdown function is the standard way to notice and respond to them.

**Deliberately does *not* install a `set_error_handler()`** for ordinary warning/notice/deprecation
levels - confirmed via erweb's own `CLAUDE.md` that it carries a known, accepted baseline of PHP 8.1
deprecation warnings from earlier phases; converting every warning into a hard exception would turn each
of those into a live 500 on every request that hits one, a real regression this function must never cause.
Left completely untouched, exactly as if this function had never been called.

**Both handlers build a minimal, hand-assembled `<html>` document rather than going through the calling
app's normal page-rendering pipeline** (no `Kernel::renderPage()`, no nav/footer modules, no DB query) -
deliberately: whatever just crashed may have left DB/module state unreliable, and the crash page itself
must not risk a second failure by depending on the same pipeline. `error_500()`'s own `Renderer::render()`
call has no DB dependency of its own, so it supplies the body; the wrapper only adds `<head>`/`<style>`/
`<body>`, discarding any partial output buffer first (`ob_end_clean()`) so a half-rendered broken page
never leaks through underneath it. `$cssPath`, if given, is inlined verbatim via `file_get_contents()` -
optional, since the safest design for a real crash page is not depending on any external file (an app can
supply nothing and rely entirely on its own `500.zetem` carrying self-contained inline styles - see
erweb's own override for exactly that approach).

**Verified against erweb** (not just unit logic): a temporary throwaway route
(`/erweb-debug-crash` -> a handler that does `throw new RuntimeException(...)`, both removed before commit)
confirmed the exception handler fires, returns HTTP 500, and renders erweb's styled crash page correctly -
screenshotted, then the route/handler removed and `git status`/`diff` confirmed a byte-identical revert
(no leftover artifact). Every normal page (`/el`, `/en`, a procedure page, `/el/tags`, `/el/search`) still
returns 200 after registering the catch-all, confirming it changes nothing about the ordinary request
path. `php -l` clean on `ErrorHandlers.php`; the two new bare templates never independently tested beyond
`php -l`-equivalent manual review (no lint tool for `.zetem` files) since no app other than erweb currently
overrides or exercises them.

## RBAC engine + admin CRUD UI moved into core (2026-08-23)

A full RBAC (role/permission) system and a generic admin CRUD UI (`/admin/{entity}`)
were originally built entirely inside zpms (`web/rbac.php`, `web/rbac_seed.php`,
`web/admin_crud.php`) -- see zpms's own `CLAUDE.md` for that history, including two
real bugs it worked around in this framework's older `SecurityClass::require()`
(`core/lib/Security.php`, still present, still used for route-level `access:`/nav-menu
gating via `SecurityClass::userIsPermitted()` -- unaffected by either bug or by this
migration). The reusable *engine* half of that work is now framework-level, following
the exact "define once in core, every app just consumes it" pattern already used for
the `users` table -- zpms keeps only its own permission-slug vocabulary (`ZPMS_PERM_*`
constants) and seed data, exactly as an app is expected to for anything domain-specific.

**Schema** (`core/classes/yaml/{roles,permissions,role_permissions,user_roles}.yaml`,
generated `core/classes/sql/*.sql` + `core/classes/*.php`, wired into `core/classes/
bootstrap_classes.php`): standard RBAC shape -- `roles` (+ `is_superuser` bypass flag,
replacing the old, broken `'administrator' => 'all'` string), `permissions`,
`role_permissions`/`user_roles` (many-to-many joins, no DB-level FK constraints
anywhere in this framework -- referential integrity is app-level only, same convention
as every other table). Table names/columns are unchanged from zpms's original
app-level YAMLs -- this is a pure code-location move, not a data migration; an app
that already had these tables (zpms) keeps its existing rows untouched.

**Extension classes** -- standalone files under `core/`, one per table
(`core/rolesClassEx.php`, `core/permissionsClassEx.php`,
`core/role_permissionsClassEx.php`, `core/user_rolesClassEx.php`), following the
`extention:` YAML directive convention already established by
`core/classes/yaml/feed_hashes.yaml` (`extention: __FWDIR__ . '/feedhashesClassEx.php'`)
rather than the older, separate `core/ClassExFW.php` monolith (which still holds
`userTokensClassEx`, an inconsistency predating this change, left as-is). `spill_class()`
(`core/maker/maker.php`) reads a YAML's `table.extention` value and appends a conditional
`require_once` to the *generated* class file itself (e.g. `core/classes/roles.php` ends
with `if(file_exists(__FWDIR__.'/rolesClassEx.php')) { require_once(...); }`) -- so the Ex
file self-loads the moment its base class is required via `core/classes/bootstrap_classes.php`,
with no separate manual wiring needed anywhere. `rolesClassEx`, `permissionsClassEx`,
`role_permissionsClassEx`, `user_rolesClassEx` provide `sgetByName()` lookups, granted-
permission-name resolution, and `assignRole()`/`removeRole()`. `user_rolesClassEx::
getRolesForUser(int $userId, array $allPermissionSlugs = [])` takes the "every
permission slug" list as a parameter now (used only for an is_superuser role's
`permissions` array) rather than calling an app-specific function directly, since this
class has no knowledge of any one app's permission vocabulary -- in practice this
parameter is moot: `rbacClass::isPermitted()`'s is_superuser check short-circuits
before ever reading that array, so the default `[]` is always correct.

**Permission-check engine** (new `core/lib/Rbac.php`, required from `core/bootstrap.php`
next to `lib/Security.php`): `rbacClass::isPermitted(string $permission): bool` /
`rbacClass::require(string $permission): ?string` -- same contract as `SecurityClass::
require()` (null on success, a rendered 401 on failure), always re-querying the
database for the current user's actual roles rather than trusting `$_SESSION` (the
session role list `Kernel::loginUser()` builds always carries an extra `"authenticated"`
entry, which is what made `SecurityClass::require()` silently inert). Also defines the
plain global function `zeusfw_app_resolve_user_roles(usersClass $user): ?string` --
**must stay a plain function, not a class method**, since `core/lib/UserLogin.php`'s
`login_post()` probes it via `function_exists()` before falling back to the legacy
`users.roles` column (same pattern as `csrf_field()`, `core/lib/FormElement.php`). This
was previously an app-defined opt-in extension point (only zpms defined it); now core
defines it by default, so every app gets correct session-role resolution for free --
in practice no app can still override it (core's own definition, loaded early in
`bootstrap.php`'s require chain, always wins the `function_exists()` race before any
app code runs). `ZEUSFW_PERM_MANAGE_USERS` (`= 'users-manage'`) is the framework's own
canonical "can manage users/roles/permissions" slug, gating the admin module below --
kept as the exact string zpms's own (now-retired) `ZPMS_PERM_USERS_MANAGE` already
used/seeded, so adopting this needed zero data migration.

**Admin CRUD ("`admin_user_crud`" package)** -- new `core/modules/admin/admin_crud.php`
(required unconditionally from `core/bootstrap.php`, not gated behind the app opting into
the pre-existing, separate `core/modules/admin/` nav-landing module) provides list/new/
edit/delete for all 5 entities (`users`, `permissions`, `roles`, `role_permissions`,
`user_roles` -- `users`' fields, e.g. name/email/uname/active/expired, are all
core-generic `usersClass` columns, so it belongs here too, not just the 4 pure-RBAC
tables), one metadata-driven engine (`zeusfw_admin_entity_defs()`) rather than five
near-identical copies, matching this framework's "no autoloader/generic-engine-culture"
style by being one hand-written file, not an abstraction layer. Its 6 routes (`admin_list`/
`admin_new`/`admin_new_post`/`admin_edit`/`admin_edit_post`/`admin_delete`, all under
`/admin/{entity}[/{id}]`) live in **`core/config/zeusfw.info.yaml`**, merged into its
pre-existing `libraries: webform:` block. `Kernel::__construct()` (`core/kernel/Kernel.php`)
originally read `__FWDIR__ . '/zeusfw.info.yaml'` (no `config/`) -- confirmed by reading the
constructor directly, which meant `core/config/zeusfw.info.yaml`'s `libraries:` block was
never actually loaded by Kernel at all (dead config, an existing bug predating this change).
Fixed as part of this work: `Kernel.php` now reads `__FWDIR__ . '/config/zeusfw.info.yaml'`,
the intended, correct path -- activating that dormant `libraries:` block for every app on the
framework as a side effect, in addition to making the routes below live. This is the *first* of the three
`Kernel::addConfig()` merge layers (framework, then an app's own `config/site.info.yaml`,
then `config/settings.info.yaml`), so these routes are available to any app on the
framework unconditionally -- independent of any per-app module opt-in, unlike the previous
(superseded) design that lived in `core/modules/admin/admin.yaml` and required the app to
list `admin` under `modules:`. Every handler still checks `rbacClass::
require(ZEUSFW_PERM_MANAGE_USERS)`.

**Enabling/disabling framework packages** (new `core/lib/Packages.php`, `packagesClass::
isEnabled(string $package): bool`): a generic, reusable toggle for `admin_user_crud` and any
future optional framework package, checked as the *first* line of every gated handler (before
even the permission check) -- a disabled package returns `error_404()`, the same observable
result as a route that was never registered, without touching `Router.php`'s shared dispatch
path at all (a deliberately smaller blast radius than a router-level gate would need).
Controlled by `disabled_packages:`, a flat list of package-name strings -- declared empty in
`core/config/zeusfw.info.yaml` and in each app's own `config/site.info.yaml` (e.g. zpms's), with a
matching comment available in `config/settings.info.yaml` for an app-specific-only override.
**Deliberately a list, not a map of booleans** (`{package: {enabled: false}}`) -- confirmed via
direct test that `array_merge_recursive()` (what `addConfig()` uses for all three config
layers) corrupts a nested scalar overridden across layers into an array
(`array_merge_recursive(['enabled'=>true], ['enabled'=>false])` produces
`['enabled'=>[true,false]]`, not `false`), while two lists merge safely via concatenation. A
flat accumulating list means any layer can only ever *add* a package to the disabled set,
never silently re-enable one a less specific layer already turned off, and every layer uses
the exact same key with zero special-casing.

**Templates** (`core/templates/modules/admin/{admin_list,admin_form}.zetem`, moved
verbatim from zpms's `web/templates/content/`): fully generic -- no app-specific
markup. Both still reference zpms's own `boxicons`/`loader-library` CSS libraries
(`attach_library()`) and reuse zpms's own `patients-*`/`icon-btn`/`admin-tab*` CSS
classes from its `css/styles.css` -- a deliberate **verbatim** move (unstyled-but-
functional for another app that hasn't defined those libraries/classes, byte-identical
rendering for zpms, which still has them), matching this framework's existing
"bare unstyled fallback, app can override/extend" precedent (see `core/templates/
errors/*.zetem` above) rather than inventing a new core-owned design system as part of
this change.

**Critical migration-ordering note for any other app adopting this schema**: adding
these core-level classes is *not* independently safe to deploy against an app that
still has its own app-level `web/classes/yaml/{roles,permissions,role_permissions,
user_roles}.yaml` (or same-named routes in its own `settings.info.yaml`) -- both would
declare the same PHP class name ("Cannot redeclare class") or collide in the route
table's `array_merge_recursive()` (turning scalar route fields into arrays, breaking
`Router.php`'s dispatch, exactly the same corruption documented above for
`disabled_packages`). An app migrating onto this (as zpms did) must remove its own
copies in the same change that adopts the core schema/routes, not as a follow-up.

## `t()` gains `@placeholder` substitution; `dictionaryClassEx` gains admin/export helpers (2026-08-24)

Two small, independent, additive pieces salvaged from zeusfw's own long-abandoned
`dicom`/`nav` R&D branch (26 commits of Jan-Feb 2026 work that never merged --
reviewed feature-by-feature, most of it superseded or unsafe to port; see the
zpms-side salvage entry below for the larger, separate review of ZPMS's *own*
`dicom`/`nav` branches, which is a different line of work entirely).

**`t($token, array $values = [])`** (`core/kernel/utils.php`) -- the translation
function now takes an optional second parameter for `@key` placeholder substitution
in the resolved string, done via `strtr`-style `str_replace('@'.$key, $value, $text)`
after translation resolves (works for both the plain-string and array-of-language-keys
forms of `$token`). Fully backward-compatible -- every existing single-argument call
site across zpms/erweb/docarc was grepped and confirmed unaffected (new param is
optional). `core/kernel/utils.php`'s `echopre()` also picked up a real bug fix from
the same source branch: the `while($fs[0] === $ap[0])` path-stripping loop now guards
both sides with `isset()` first, so a call from a file whose path doesn't share every
segment with `__APPDIR__` no longer risks an undefined-array-key warning once `$fs`/
`$ap` are exhausted.

**`dictionaryClassEx`** (`core/dictionaryClassEx.php`) gained 8 new static admin/export
methods: `getAllTokens()`, `updateTranslation($token, $lang, $translation)`,
`deleteToken($token)`, `getUntranslated($lang)`, `exportToYAML($lang)`,
`importFromYAML($lang, $file)`, `getTranslationStats()`, `getRecentTokens($limit)` --
useful building blocks for a future dictionary-admin UI, ported verbatim in spirit but
**not** verbatim in code: the source branch's version of these methods (and a change it
made to the existing `translateToken()`, deliberately **not** ported) built every SQL
column-name list via `array_keys($kernel->getConfig('languages'))`. That's wrong --
`getConfig('languages')` already returns the flat list of language codes as-is (e.g.
`['en', 'gr']` for zpms, `['el', 'en']` for erweb), so `array_keys()` on it yields
`[0, 1]`, not language codes -- every generated query would have referenced SQL columns
named `0`/`1` instead of `en`/`gr`/`el`. Fixed while porting: every new method uses
`$kernel->getConfig('languages')` directly, matching the untouched `translateToken()`'s
own (already-correct) pattern. Verified against an in-memory SQLite stand-in for the
real DB (not provisioned in this sandbox) with `languages => ['en', 'gr']`: seeded a
row, round-tripped it through `updateTranslation()`/`getUntranslated()`/
`getTranslationStats()`/`exportToYAML()`/`deleteToken()`/`getRecentTokens()`, and
confirmed every generated query referenced the real `en`/`gr` columns (`getTranslationStats()`'s
result keyed by `'en'`/`'gr'`, not `0`/`1`) -- this is exactly the class of bug that
fix prevents, and the test would have failed loudly without it.

## `core/kernel/Kernel.php` - cache-busting for `<script>` tags, matching the existing `<link>` treatment (2026-08-28)

`renderPage()`'s `css:` block has always appended `?` . `time()` to every stylesheet's
`src` before `rel_url()`-ing it (`"href" => rel_url($css['src']. "?".time())`) --
but the two script-rendering blocks right below it (`head_script:`, `foot_script:`)
never did the same: `rel_url($sval)` with no query string at all. Found while
debugging zpms's own appointment-file delete button appearing to silently do nothing
on an iPhone -- the delete handler itself (`web/js/appointment-files.js`) had every
fix already deployed and working in every other tested environment, but with zero
cache-busting on script tags, a browser has no reason to ever re-fetch a JS file
after the first load, and mobile Safari in particular caches static JS far more
aggressively/persistently than desktop Chrome/Firefox test against typically shows.
An iPhone that had cached `appointment-files.js` from an earlier point in zpms's own
appointment-file-upload debugging session could keep running that stale copy
indefinitely, regardless of how many subsequent fixes were deployed and confirmed
working everywhere else -- CSS changes were always picked up fresh (forcing a new
URL every page load already does that), which is what made this JS-only gap
invisible until specifically tracked down.

Fixed by applying the exact same `?time()` treatment (not a `filemtime()`-based
scheme, or any other convention) to both `head_scripts` and `foot_links` --
deliberately mirroring the one cache-busting approach this codebase already has
precedent for, rather than introducing a second, different convention. Every
`<script src=...>` this framework renders (including this framework's own
`js/loader.js`, `core/modules/language_selector/js/language_selector.js`, and every
app's own `foot_script:`/`head_script:` entries -- zpms's `js/scripts.js`,
`js/appointment-files.js`, `js/textarea-autoexapand.js` confirmed among them) now
gets the same always-fresh query string CSS already had.

**Verified against zpms** (not just unit logic): booted a real MariaDB-backed test
server, confirmed every rendered `<script src=...>` tag on a real page now carries a
`?<timestamp>` suffix identical in shape to the pre-existing `<link href=...>` ones,
and ran a full upload-then-delete regression through a real browser session (zero
404s on any asset, both operations completed correctly) to confirm this doesn't
change how any of those tags actually resolve or load.
