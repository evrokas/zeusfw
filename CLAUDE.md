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

## ErnsAuth SSO integration (`core/lib/ErnsAuth.php`, `core/modules/ernsauth_sso/`) (2026-09-02)

Client-side integration with [ErnsAuth](https://github.com/evrokas/ernsauth)'s
number-matching SSO flow ("Flow A"), specifically the **mandatory-username**
variant documented in that repo's `CLIENT-INTEGRATION.md` under "Requiring a
username before Flow A" -- for an app with several accounts that needs to
know *which one* is signing in, rather than accepting whichever ErnsAuth
account happens to approve the shown number. First adopter: zpms (see its
own `CLAUDE.md`/`README.md` "ErnsAuth SSO login" section for the app-level
half -- config, vendored client library, login-page UI). Built as a
reusable core module, not app-specific code, since the whole engine (config
loading, the challenge lifecycle, rate limiting, identity verification,
session establishment) has nothing zpms-specific in it beyond the local
`uname` lookup, which itself goes through the same `usersClassEx`
convention `login_post()` already depends on.

**`core/lib/ErnsAuth.php` / `ernsauthClass`** -- config-driven, no
app-specific values baked in (same convention as `Recaptcha.php`): an app
supplies its own `config/ernsauth.php` (`sso_api_url`/`api_key`, gitignored,
outside the web root) and vendors ErnsAuth's client library itself at
`lib/ernsauth/` (`git clone -b stable`, also gitignored). Every failure mode
fails closed -- disabled/misconfigured/network error all behave like "not
signed in", never a fatal error or a silently-accepted login.

Three public methods carry the whole flow, one per step of the mandatory-
username variant:
- `startChallenge(string $username, string $clientIp, string $userAgent): array`
  -- validates the username against the app's own `usersClassEx::
  getUserAccount()` (respecting `LoginSecurityClass::$enforceLockout`/
  `$enforceAccountStatus`, exactly like password login), pins the *expected*
  ErnsAuth identity to session, and creates (or reuses) the challenge.
  **Returns the identical `{challenge_id, challenge_number, expires_at}`
  shape whether or not `$username` resolved to a real, eligible account** --
  the single most load-bearing line in this file, since a different
  response per case would be a free username-enumeration oracle. The
  expected-identity mapping itself is a `function_exists()` extension point
  (`zeusfw_app_resolve_ernsauth_username(usersClass $user): string`, same
  pattern as `zeusfw_app_resolve_user_roles()` in `Rbac.php`), falling back
  to a 1:1 `uname === ErnsAuth username` assumption when an app hasn't
  defined it.
- `poll(): array` -- thin pass-through to `pollChallenge()`, but also clears
  the locally tracked pending challenge on any terminal non-success status
  (`rejected`/`expired`/`not_found`) so a "new request" click isn't stuck
  reusing a challenge ErnsAuth will never approve again.
- `finish(string $authCode): array` -- exchanges the auth code, then
  **rejects unless the identity that actually approved matches the one
  pinned in `startChallenge()`** (`hash_equals()`), which is the entire
  security property of this variant -- see CLIENT-INTEGRATION.md's own
  "🔒 Security requirements" table, since this single comparison is what
  that whole section is about. A mismatch (or an unresolved username)
  increments the matched local account's `wrongpasscount` the same way a
  wrong password does, via the exact same counter `login_post()` already
  uses -- so SSO can't become a second, unthrottled guessing surface around
  an account password login already locks out. An upstream/network error
  during exchange does *not* touch that counter (not a failed attempt by
  the user). On a real match: resets `wrongpasscount` to 0, returns the
  local `usersClass` row -- **never logs the session in itself**; the
  caller (the module below) does that via `$kernel->loginUser()`, reusing
  `login_post()`'s exact RBAC role-resolution pattern.

**Local rate limiting + one-pending-challenge-per-username**
(`core/classes/yaml/ernsauth_sso_attempts.yaml` -> generated
`ernsauthSsoAttemptsClass`, hand-written `core/ernsauthSsoAttemptsClassEx.php`)
-- a new, small DB table, because neither ErnsAuth itself nor a PHP session
can provide either guarantee on their own. ErnsAuth's own `create_challenge`
rate limit is keyed on **whoever calls its API** -- for this server-to-
server integration, that's this app's own server IP, shared across every
one of its real users, so it can't see one username being targeted while
every other user keeps working. And a session-only "one pending challenge"
cap is trivially bypassed by an attacker who gets a fresh session on every
attempt. `ernsauthSsoAttemptsClassEx::checkAndRecordAttempt()` is the single
entry point covering both checks together (so a caller can't apply one
without the other), keyed on the *submitted* username (resolved or not, for
the same enumeration-safety reason as above) and the real end-user IP.

Its rate-limit counter uses the exact same atomic `INSERT ... ON DUPLICATE
KEY UPDATE ... IF(...)` technique as ernsauth's own `RateLimit::attempt()`
(`src/RateLimit.php` in that repo) -- ported deliberately, not
reinvented, since concurrent requests for the same key need to serialize on
a real row lock rather than race on a read-then-write gap, and that file
already solved this exact problem once. **The `UNIQUE` constraint this
depends on is baked directly into the yaml's `type: varchar(64) UNIQUE`
field definition, not a hand-added `ALTER TABLE` in the generated `.sql`
file** -- confirmed the hard way, within the same session this table was
built in: a hand-added `ALTER TABLE` survived exactly until the next
`spill:sql` regenerated the file from the yaml (zpms's own test suite does
this as part of its schema rebuild), silently dropping the index and
breaking the upsert's atomicity. `maker/functions.php`'s
`createFieldDefinition()` has no separate `unique:` field option, but
happily passes an arbitrary `type:` string straight through into the column
definition, which is what makes baking it into `type:` durable across
regeneration where the `.sql` file itself never is.

**`core/modules/ernsauth_sso/ernsauth_sso.php`** -- the browser-facing
route handlers (`ernsauth_sso_start`/`ernsauth_sso_poll`/
`ernsauth_sso_exchange`), registered unconditionally in
`core/config/zeusfw.info.yaml` under `/login/ernsauth/{start,poll,exchange}`
(same "always registered, framework-wide" precedent as `admin_user_crud`'s
routes) but only ever *functional* once `ernsauthClass::isEnabled()` says
so. Unlike `admin_user_crud`'s `packagesClass`/`disabled_packages` gate
(opt-OUT: enabled by default, and a later config layer can only ever add to
the disabled list, never re-enable something a less specific layer already
turned off -- see `Packages.php`'s own docblock), this package needs real
per-app setup (a vendored client library, a live ErnsAuth server, an API
key) to function at all, so it's opt-IN instead: `register_ernsauth_sso_
module()` (called by the classic `core/lib/Modules.php` `registerModules()`
mechanism, when an app lists `ernsauth_sso` under its own `config/
settings.info.yaml` `modules:` block) is the only thing that ever calls
`ernsauthClass::enable()` -- and `isEnabled()` additionally requires a valid
`config/ernsauth.php` on top of that, so listing the module alone still
isn't enough. This is the first use of the `modules:` opt-in list for
something that isn't a renderable page-region `moduleClass` block (nav/
footer/etc.) -- `register_ernsauth_sso_module()` calls `ernsauthClass::
enable()` instead of `$kernel->registerModule(...)`, which is fine: nothing
about `registerModules()` requires the callback to actually register a
module instance, and repurposing an existing, already-familiar app-facing
toggle beat inventing a second, parallel enablement mechanism next to
`disabled_packages` for a feature that structurally can't be modeled as
opt-out.

Also required unconditionally from `core/bootstrap.php` (`ErnsAuth.php`,
right after `UserLogin.php`, since it depends on `LoginSecurityClass`) --
same "handler functions always exist, the package check inside them is what
actually gates behavior" reasoning as `admin_crud.php`'s own require line.

**Verified end-to-end against zpms** (not just unit logic, though this
sandbox has no reachable live ErnsAuth server to complete a real approval
against): booted a real MariaDB-backed zpms test server and confirmed, over
real HTTP -- the login page renders the "Sign in with ErnsAuth" section only
once both the `modules:` entry and a valid `config/ernsauth.php` are
present; a nonexistent username and a real-but-uninvolved one (`zpms_test_user`)
produce byte-identical `{"error":"upstream_unavailable"}` responses once the
(unreachable, by design in this sandbox) ErnsAuth call fails, confirming the
enumeration-safety property actually holds at the HTTP layer, not just in
theory; the local rate limit blocks the 6th `create_challenge` attempt for
one username (429) while a different username is unaffected; a missing/
wrong CSRF token is rejected (403). Separately, with `ErnsAuthClient` stubbed
out (no network dependency) to isolate `ernsauthClass`'s own logic: a
matching identity logs the account in and clears session state; a mismatched
identity is rejected and increments `wrongpasscount` by exactly 1; a
username that never resolves to a local account is rejected without
touching any account's counter; a second `startChallenge()` call while one
is still pending reuses the exact same `challenge_id`/`challenge_number`
rather than creating (and paying the rate-limit cost of) a new one. zpms's
own `bin/run_tests.sh` (36/36 static, 35/35 functional) stayed fully green
throughout, both before and after the `UNIQUE`-constraint fix above was
found and corrected.

## `SecurityClass::enableLoginRedirect()` -- opt-in redirect-to-login instead of a bare 401 page (2026-09-02)

`Router.php`'s route-level `access:` gate (`SecurityClass::userIsPermitted()`,
checked before dispatch -- separate from `rbacClass::require()`/
`SecurityClass::require()`, which handlers call *internally* and which
render `error_401()` directly, untouched by this change) has always shown
a bare inline `error_401()` page on failure, whether the cause was "not
logged in at all" or "logged in but lacks the role". First requested by
zpms, which wanted an anonymous visitor sent straight to `/login` instead.

New `SecurityClass::$loginRedirectUrl` (default `null`) / `enableLoginRedirect(string
$url = '/login')`, same opt-in shape as `csrfClass::$enforceLogin`/
`LoginSecurityClass`'s switches -- an app that never calls this keeps the
exact same bare `error_401()` behavior as before. `Router.php`'s `case
401:` now checks it first: if set, redirects (`header('Location: ' .
rel_url($url)); exit();`) instead of rendering the page. Deliberately
framework-level rather than zpms-specific, since "send an anonymous user to
the login page instead of a 401" is generic behavior any app on this
framework might want, following the same "define once in core, opt in per
app" pattern as every other switch in this file.

**Only covers routes with a route-level `access:`.** A handler that calls
`rbacClass::require()`/`SecurityClass::require()` itself (e.g. every
`admin_crud.php` handler, zpms's own `clinics_edit()`) still renders
`error_401()` inline regardless of this setting -- those never reach
`Router.php`'s error-handling branch at all, since the route itself has no
`access:` and matches successfully; the handler decides on its own. An app
wanting the redirect there too would need to change those call sites
individually, not this one switch.

## `access:` on regions and modules -- chrome that requires login, without hardcoding route names (2026-09-02)

Follow-up to the redirect-to-login entry above: zpms wanted its
header/nav/footer chrome to disappear on `/login` (the one route still
reachable while logged out) rather than surrounding the login form the way
every other page's chrome surrounds real content. The first version of this
(zpms's own `web/templates/page/page.zetem`) hardcoded the `login`/
`login_post` route names -- worked, but silently failed to cover any future
pre-auth route (password reset, an invite link, ...) unless someone
remembered to add it to that list too. Replaced with the same `access:`
concept routes already have, applied one level down, at the two places
content actually gets composed: regions and modules. Both resolve through
the exact same `SecurityClass::userIsPermitted()` a route's own `access:`
already uses -- no new permission-matching logic anywhere.

**Region-level** (`Kernel::renderRegion($structure, $regionName, $access =
null)`, new third param): `config/settings.info.yaml`'s `regions:` list
entries can now be a plain name (unchanged, no restriction) or a
single-key map carrying that region's config -- same shape `structure:`
already uses one level down for blocks vs. sections:

```yaml
regions:
  - header:
      access: authenticated
  - main_navigation:
      access: authenticated
  - notification
  - main_content
  - footer:
      access: authenticated
```

`Kernel::renderRegions()` parses this (`is_array($region)` ->
`array_key_first()` for the name, `[$name]['access'] ?? null` for the
requirement) and passes the resolved `$access` into `renderRegion()`,
which checks it *before* building any module/section inside -- an
unpermitted region costs one `userIsPermitted()` call, nothing inside it
ever runs.

**Module-level** (`Kernel::renderModule()`): `modconf: <module>: access:
<role-string>` -- a new key alongside the existing `hide:`/`display:`
(which stay route-name-keyed and unchanged). `access:` answers a different
question than hide/display ("is this viewer even allowed to see this
module at all" vs. "should it show on this particular route") and is
checked independently: `$permitted = SecurityClass::userIsPermitted(...)`
computed alongside the existing `$display` boolean, module renders only if
`$display && $permitted`.

**Both are opt-in and additive** -- a region/module with no `access:`
behaves exactly as before this change; every existing `regions:`/`modconf:`
entry across every app on this framework needed zero changes.

**Empty wrapper divs needed no new code.** `renderRegion()` (and
`renderBlock()`'s nested-section branch) already only render their own
`<div class="region ...">`/`<div class="section ...">` wrapper when
`strlen($output)` -- the concatenated blocks inside -- is non-empty. Once
every module in a region resolves to `''` (via this same mechanism), the
region's own wrapper disappears too, recursively through nested sections,
for free -- this existed before today and just needed the modules to
actually go empty, which `access:` now does.

**zpms's own follow-up** (`web/templates/page/page.zetem`): the old
route-name check is gone. The only thing left for that template to derive
is whether to add the `wrapper-bare` CSS class (so `.login-page` fills the
viewport instead of leaving a gap sized for chrome that isn't there) --
now computed by checking whether `$regions['header']`/
`['main_navigation']`/`['footer']` actually rendered anything, not by
checking the route name:

```
{% $__bareLayout = trim(($regions['header'] ?? '') . ($regions['main_navigation'] ?? '') . ($regions['footer'] ?? '')) === ''; %}
```

Content-driven rather than identity-driven, but derived from the same
`access:` checks above -- correct for any current or future route that
ends up with empty chrome, with nothing to remember to add anywhere.

**Verified against zpms**: logged-out `/login` renders with the
`notification` region (so `login_post()`'s flash messages still show) and
`main_content` only, `wrapper-bare` present, zero `region-header`/
`region-main_navigation`/`region-footer` markup in the response. A
logged-in visit to `/patients` -- and, deliberately, a logged-in visit to
`/login` itself, which has no reason to hide chrome for someone already
authenticated -- both render full chrome. `bin/run_tests.sh` (36/36
static, 35/35 functional) stayed green throughout.

**Pre-existing, still-unrelated oddity found while touching this
file**: `config/settings.info.yaml`'s `modconf: message: display:
userprofile:` block has a nested `access: authenticated` key sitting
alongside `arguments:` -- confirmed via direct code reading that nothing
before or after this change ever reads a nested `display.<route>.access`
value (only `display.<route>.arguments`); it's inert config, not a
different form of the mechanism added here. Left untouched rather than
guessed-at rewritten -- flagged for zpms's own maintainer to decide
whether it was an abandoned earlier attempt at this exact feature or dead
copy-paste.

## ErnsAuth identity resolution: same-username convention, enforced on ErnsAuth's own side (2026-09-02, revised same day)

Real production incident on zpms (`pms.erns.eu`): a login attempt via the
ernsauth_sso module logged `ernsauth sso mismatched for guest` even though
the person approving was a genuine ErnsAuth user. Root cause: `ernsauthClass::
startChallenge()` (`core/lib/ErnsAuth.php`) had exactly one fallback for
resolving "which ErnsAuth identity is this ZPMS account allowed to sign in
as" when no app-defined `zeusfw_app_resolve_ernsauth_username()` hook
existed -- assume the ZPMS `uname` and the ErnsAuth username are spelled
identically. zpms's `guest` account and the approver's real ErnsAuth
username are two different strings, so step ⑥'s identity check (see
ernsauth's own `CLIENT-INTEGRATION.md`) correctly rejected it every time --
working as designed, but the *design* had no way to express "these two are
the same person, just spelled differently" short of writing a PHP function.

**First fix, shipped then reverted the same day**: a `users.ernsauth_username`
column (nullable `varchar(64)`) storing an explicit per-account mapping,
editable via `/admin/users`. Reverted after the app's maintainer objected --
correctly -- that storing this linkage in ZPMS's own database at all is a
needless reconnaissance/targeting surface on a DB that's otherwise
patient-data-only, and separately pointed out that ErnsAuth accounts have no
concept of being "mapped" to a client app's users in the first place, so
inventing a parallel mapping table on ErnsAuth's side to replace it wouldn't
be right either. The column, its `core/modules/admin/admin_crud.php` field,
and `startChallenge()`'s column-reading tier were all removed the same day
they were added; if a live database ever ran the earlier entry's
`ALTER TABLE ... ADD COLUMN ernsauth_username`, the column is simply unused
now and can be dropped at your convenience -- nothing in either app reads or
writes it any more.

**Actual fix: enforce the same-username convention where a human is already
standing, instead of resolving a stored mapping where nobody is.** ErnsAuth
(`src/SSO.php::approveChallenge()`, ernsauth repo) now checks, server-side,
at the moment someone tries to approve a challenge: does the challenge's
`requested_identity` (the raw username the client app submitted --
unchanged, still threaded through from `startChallenge()`'s `$username`,
see below) match *that approver's own ErnsAuth account username*? A
mismatch is rejected outright, and ErnsAuth's dashboard now greys out /
disables the number buttons on any pending card that isn't the logged-in
viewer's own request, so a mismatch is caught before a click even reaches
the network, not after. There is still no mapping table anywhere in this
system, on either side -- the enforced rule is exactly "your ErnsAuth
username must be spelled identically to the client app's username",
nothing more elaborate, and it's ErnsAuth itself that now guarantees it
rather than leaving it to a client app's own post-hoc string compare.

`startChallenge()`'s resolution for zeusfw's own client-side `$expected`
(used by `finish()`'s step ⑥ comparison, which stays in place as a second,
independent layer -- see CLIENT-INTEGRATION.md's updated security table)
is back down to two tiers: an app-defined `zeusfw_app_resolve_ernsauth_username()`
hook (unchanged extension point, `function_exists()`-gated, same convention
as `zeusfw_app_resolve_user_roles()`) if one exists, else the bare `uname`.
No column tier. `core/modules/admin/admin_crud.php`'s "ErnsAuth Username"
field is gone; the `users` entity's Username field comment now just states
the same-spelling requirement directly.

**Server-side enforcement reverted the same day, back to display-only.**
The app's maintainer asked for ErnsAuth to "approve any username" again --
`requested_identity` should let the human approver visually confirm what's
being claimed, not have ErnsAuth block the click itself. Reverted via
`git revert` of the enforcement commit (ernsauth repo): `approveChallenge()`
no longer takes an approver-username parameter or compares it against
anything, and the dashboard no longer greys out/disables numbers for a
"mismatched" card -- every pending challenge is equally clickable by any
logged-in ErnsAuth user again, exactly as when `requested_identity` first
shipped. This also un-breaks the `zeusfw_app_resolve_ernsauth_username()`
hook for any app using a real custom mapping: while ErnsAuth enforced literal
same-spelling, an app whose hook resolved to something else would have had
every one of its logins blocked at approval time regardless of what its own
step ⑥ check would have said.

**Net effect: the entire security property is, once again, squarely
zpms's/zeusfw's own `finish()` step ⑥ comparison** (`hash_equals($expected,
$user['username'])`) -- ErnsAuth's Pending Logins card is a courtesy for a
human to catch "that's not me" before tapping a number, nothing more. This
was always true before today's brief detour into server-side enforcement,
and CLIENT-INTEGRATION.md's own security table (ernsauth repo) has been
reverted to say so explicitly again -- never treat `requested_identity`, or
the fact that an approver picked the right number, as proof of identity on
its own.

See ernsauth's own `CLIENT-INTEGRATION.md` ("Requiring a username before
Flow A") for the full current design, and zpms's `README.md` for the
app-level note this pairs with. `guest`-style accounts are still best fixed
by making the ErnsAuth account's own username literally `guest` (or
renaming the ZPMS account to match) -- the same-spelling convention is
still the simplest thing that works with the default fallback, it's just no
longer enforced by ErnsAuth itself, only checked by your own app's step ⑥.

## No post-approval identity check -- `finish()`'s step ⑥ removed entirely (2026-09-03)

Same-day follow-up to the entry above: even with the same-spelling
convention as the only remaining rule, real accounts (`guest` among them)
kept failing SSO login because their ErnsAuth username genuinely wasn't
spelled the same as their ZPMS `uname`, with no config mismatch or bug
involved -- just two separately-administered systems whose usernames were
never coordinated. Walked through the actual threat model with the app's
maintainer before touching anything (this repo's established practice for
security-model changes): concretely, what does step ⑥ protect against that
decoys/throttling/IP-logging don't?

**The answer, and why it doesn't apply to this deployment**: step ⑥ stops
an ErnsAuth identity that *isn't* entitled to a given ZPMS account from
successfully claiming it -- e.g. account B's holder typing `admin` at
ZPMS's login form and approving their own request with their own (non-admin)
ErnsAuth login. Without step ⑥, that succeeds; a compromised or merely
curious ErnsAuth account becomes a skeleton key to every ZPMS username via
plain "username scanning" (type any name, approve it yourself), since Flow
A's decoys/throttling only defend against someone *guessing* a challenge
number they weren't shown -- not against someone approving a request they
generated themselves, which needs no guessing at all. This is a real,
general risk for any multi-account app using Flow A's username variant, and
CLIENT-INTEGRATION.md's guidance to make step ⑥ mandatory stands unchanged
for the general case.

zpms's actual deployment is materially narrower: ErnsAuth dashboard access
is held by a single trusted operator, not a wider staff population. In that
shape, the "wrong identity approves" scenario step ⑥ guards against can't
occur the way it does for a multi-approver deployment -- there is no
second, differently-privileged ErnsAuth account that could approve instead.
What step ⑥ *would* still do is limit the blast radius if that one
ErnsAuth account is ever compromised (an attacker inheriting it could only
reach the one ZPMS account whose uname matches it, instead of every
username they can type) -- a real, understood, and explicitly *accepted*
tradeoff, not an overlooked one. It also does nothing at all against a full
ErnsAuth **server** compromise (DB/RCE access), since an attacker at that
level can just make `exchangeCode()` return whatever identity the check
wants to see -- no client-side comparison survives that regardless.

**Removed, not just relaxed**: `ernsauthClass::startChallenge()` no longer
resolves or pins an "expected" ErnsAuth identity at all (no column, no
`zeusfw_app_resolve_ernsauth_username()` hook call, no bare-uname
fallback -- that whole block is deleted, along with the
`ea_expected_ernsauth_username` session key, replaced by `ea_pending_eligible`,
a plain boolean carrying forward only the pre-existing account-status/
lockout eligibility check, which is unrelated to identity and stays for
password-login parity). `finish()` now signs a login in the moment
`exchangeCode()` returns successfully for an eligible pending username --
full stop, no comparison against whichever ErnsAuth identity actually
clicked approve. `requested_identity` is still sent and still shown on
ErnsAuth's Pending Logins card ("Claiming to be `guest`") -- now purely a
courtesy for the human operator to eyeball, exactly like plain Flow A's
decoy numbers are a courtesy against guessing, not an enforced identity
check on either side of this integration. The former `identity_mismatch`
error code `finish()` returned is renamed `account_not_found` (`core/
modules/ernsauth_sso/ernsauth_sso.php` itself is untouched -- it just
forwards `finish()`'s result verbatim as JSON; `web/js/ernsauth-sso.js`'s
error map in zpms is the one place that needed updating to match) since
there's no identity comparison left to name it after -- the only remaining
failure this path can report is the submitted username not resolving to an
eligible local account at all. A successful approval still
logs which ErnsAuth identity clicked it (`error_log('ernsauth sso approved
for ' . $username . ' by ernsauth identity ' . ...)`) purely for an audit
trail -- not checked against anything, but worth having on record.

**If you're adopting `ernsauth_sso` for a *different* app**: do not copy
this file's current `finish()` as the template. This is zpms's own,
explicitly-made-with-full-context decision for its specific deployment
shape (single trusted operator) -- re-derive whether it's appropriate for
yours rather than assuming zeusfw's shipped default. See ernsauth's own
`CLIENT-INTEGRATION.md` ("Requiring a username before Flow A") for the
general, still-current guidance that step ⑥ is mandatory for a multi-
approver deployment, and its note flagging this specific departure.
