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

### `Accessibility.php` / `accessibilityClass` (2026-08-27)

At direct request: a framework-level accessibility widget - a floating toggle button + panel offering
4 disability **profiles** (Blind, Color Blindness, Dyslexia, Epilepsy Safe) and 4 individual **page
options** (Contrast, Font Size, Letter Spacing, Hide Images). Added for erweb (user chose erweb over
zweb/zpms when asked which app should get the visible widget - the module itself is framework-wide and
any app can adopt it the same way).

**Why this is one big self-contained `render()` call, not a `moduleClass` or a shared asset file.**
Confirmed by exhaustive search before writing this: ZeusFW core has **never** shipped a shared CSS/JS
asset directory - every app's `web/css`/`web/js` has always been entirely local to that app, with core
only ever providing PHP (`core/lib/`, `core/templates/`). `moduleClass` (`core/lib/Modules.php`) is also
the wrong shape - it renders page-*region* UI blocks wired via `settings.info.yaml`'s `structure:` map
(nav/footer-style), not a floating overlay with no region of its own. The one real precedent for
"drop-in UI surface with zero app-side asset dependency" is `ErrorHandlers.php`'s crash pages, which
hand-assemble their own `<style>` rather than linking an external file - `accessibilityClass::render()`
does the same thing at larger scale: one call returns a single self-contained HTML string (inline
`<style>`, inline `<script>`, its own CSS custom-property namespace `--zfa-*` so it never depends on or
collides with the calling app's own design tokens) that a caller echoes once into its page, typically
near the end of `<body>` (see erweb's own `CLAUDE.md` entry for the exact call site next to its
back-to-top button).

**How it actually changes the page.** Every effect is a class or CSS custom property toggled on
`<html>`, read back by generic selectors - the widget has no knowledge of the calling app's markup:
- `zfw-a11y-contrast-high` / `zfw-a11y-contrast-invert` - a 3-state cycle (normal/high/inverted) on the
  **Contrast** option. Inverted mode counter-inverts the widget's own root (`#zfw-a11y-root`) so the
  panel itself doesn't turn into an unreadable inverted mess along with the rest of the page.
- `--zfa-font-scale` (CSS custom property, 5 steps 100/115/130/145/160%) drives `html { font-size:
  calc(100% * var(--zfa-font-scale, 1)); }` - scales every rem-based size site-wide for the **Font
  Size** option, since every app in this ecosystem already uses rem-based type scales.
- `zfw-a11y-spacing-wide` / `zfw-a11y-spacing-wider` - letter-spacing/word-spacing on `body` for the
  **Letter Spacing** option.
- `zfw-a11y-hide-images` - `img { visibility: hidden !important; }` for **Hide Images**. Scoped to
  `<img>` only (not background-images/`<picture>`/`<video>` posters) - a real, documented scope limit,
  not an oversight.
- **Blind** profile (`zfw-a11y-blind`) - stronger focus-visible outlines, plus a page-level read-aloud
  control (Web Speech API `SpeechSynthesisUtterance`) that appears in the panel only while this profile
  is active. Reads `document.querySelector('main, [role="main"]')` (overridable via
  `render()`'s `readAloudSelector` arg), falling back to `<body>` if nothing matches; `lang` is taken
  from `<html lang>` so the correct voice/pronunciation is selected. Gracefully disables itself
  (button disabled, "not available in this browser" label) when `window.speechSynthesis` doesn't exist,
  rather than silently doing nothing.
- **Dyslexia** profile (`zfw-a11y-dyslexia`) - increased `line-height`/letter-spacing/word-spacing,
  forces left-aligned text (`text-align: left !important` on `p`/`li`, overriding any `justify`), and a
  `max-width: 68ch` cap - all pure CSS, no new font asset added (see the color-blindness note below for
  why this class stays conservative rather than reaching for an unverified "ideal" fix).
- **Epilepsy Safe** profile (`zfw-a11y-epilepsy`) - `* { animation-duration: 0.001ms !important;
  animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; scroll-behavior:
  auto !important; }`. This is a *forced* override (works regardless of the visitor's OS-level
  `prefers-reduced-motion` setting), not a re-read of that media query - deliberately, since this
  profile exists specifically for a visitor who wants motion killed on this one site regardless of what
  their OS is currently set to.
- **Color Blindness** profile (`zfw-a11y-colorblind`) - `filter: saturate(0.35) contrast(1.15)` on the
  whole page (again excluding the widget's own root). **Deliberately not three separate per-deficiency
  "correction" filters** (protanopia/deuteranopia/tritanopia) - true daltonization correction is a
  composed multi-step color transform, and no verified, authoritative single-matrix correction values
  were available while building this (a websearch during development surfaced only *simulation*
  matrices - Viénot/Brettel/Mollon-style, meant to show a sighted person what a colorblind person sees -
  which is the opposite of what a colorblind visitor needs applied to their own screen; shipping an
  unverified "correction" matrix risked doing active harm rather than helping). Desaturation + a
  contrast boost is a simpler, safe, well-understood mitigation that helps regardless of deficiency
  type, at the cost of not being type-specific - documented here so a future revision with genuinely
  verified matrices knows why this started simpler rather than assuming the simplicity was an oversight.

**State** persists client-side only, in the visitor's own browser via `localStorage`
(`zfw_a11y_state`) - never sent to the server, never shared across visitors, the same
browser-storage-is-per-viewer-only convention DocArc's own docs describe elsewhere in this ecosystem.
A **Reset all** button clears every profile/option back to defaults in one action.

**Every string dropped into the emitted `<script>` block goes through `json_encode()`**, never raw
PHP-to-JS interpolation - a label or a caller-supplied `readAloudSelector` containing a quote,
backslash, or a literal `</script>` sequence can never break out of the JS string it's assigned to.
`json_encode()` here always contributes to a purely internal helper-value assignment, e.g. `var
LABEL_PLAY = {$readAloudPlayJs};` - the JSON *is* the entire right-hand side of a `var ... =` statement,
never spliced into the middle of an existing string literal, which is what actually makes this safe
against the closing-tag/quote-escaping class of bug.

**Bilingual labels** (`el`/`en`) are baked into `self::LABELS`, matching this whole ecosystem's
established two-language convention; a caller passing an unlisted `lang` falls back to `en`, the same
fallback pattern erweb's own `erweb_axis_label()`-style lookups already use. `render(array $args)`
accepts `lang`, `position` (`'left'`/`'right'` - which bottom corner the toggle sits in, so an app with
its own bottom-right chrome, like erweb's back-to-top button, can push this one to the other corner),
and `readAloudSelector`.

Wired into `core/bootstrap.php` right after `Recaptcha.php`'s own `require_once` line, matching the
existing load-order convention (no ordering dependency between the two, just grouped as "recently added
utility classes").

**Verified against erweb**: `php -l` clean on both `Accessibility.php` and `bootstrap.php`; a standalone
`accessibilityClass::render()` smoke test confirmed zero unresolved `{$...}` placeholders in the output
(a real bug caught and fixed during development - the emitted `<script>` block originally referenced
`$stateKeyJs`/`$contrastNormalJs`/etc. as if a removed private helper's return values had been extracted
into scope, when they hadn't; fixed by assigning each one explicitly, via `json_encode()`, right before
the heredoc); a Python `html.parser`-based tag-balance check confirmed the emitted markup is
well-formed; `node --check` confirmed the emitted `<script>` block is syntactically valid JS. Live
end-to-end on erweb's dev server (`php -S`): the panel opens/closes, every one of the 4 profiles and 4
page options visibly does what it claims (Playwright screenshots of contrast-invert - confirming the
widget's own panel is correctly counter-inverted rather than becoming unreadable - dyslexia+epilepsy
combined, 130% font size, hidden images, and the Blind profile's read-aloud control appearing), state
survives a full page reload via `localStorage`, Reset clears everything back to defaults, zero
console/page errors, and desktop (1440px) + mobile (390px) viewports both render the panel within the
viewport with no overflow. `bin/check_integrity.php` clean (unrelated to this change, but a cheap
regression check since it exercises the whole erweb DB/route layer). See erweb's own `CLAUDE.md` for the
one-line `main.zetem` call site.

**Follow-up, same day: the toggle button itself vanished after changing Contrast (or Color Blindness) -
a real bug in the `filter`-based approach above, found in real use, not just a naming issue.** The
original Contrast/Invert/Color Blindness effects worked by putting `filter: contrast()/invert()/
saturate()` on `<html>` (or on every element except the widget via a `:not()` sweep that, as a side
effect, also matched `<body>` itself, since `<body>` is an *ancestor* of `#zfw-a11y-root`, not a
descendant excluded by `:not(#zfw-a11y-root *)`). `filter` on any element - like `transform`/
`perspective`/`backdrop-filter`/`will-change` naming one of those - establishes a new **containing
block** for every `position: fixed` descendant of that element. Once `<body>` (or `<html>`) had a
`filter`, the toggle button's `bottom: 1.25rem` stopped resolving against the viewport and started
resolving against `<body>`'s own box - which, on a real page, is as tall as the whole document, not
the viewport. Reproduced and confirmed directly: before the fix, toggling Contrast to "high" moved the
button's `getBoundingClientRect().top` from `830px` to `7134px` on a 900px-tall viewport, with
`document.body` itself showing up as the filtered ancestor.

**This wasn't only a self-inflicted bug.** The counter-filter this file originally put on
`#zfw-a11y-root` for Invert mode fixed the *visual* color inversion of the panel, but did nothing for
the *positioning* problem, since `#zfw-a11y-root` becoming a filtered element just moved the same
containing-block issue one level down, now breaking its own fixed children (the toggle/panel) instead
of the page's. And the underlying mechanism - `filter` on an ancestor of *any* `position: fixed`
element - would have broken every other fixed/sticky element a host app already has (erweb's own sticky
header, back-to-top button, and Neural Thread all sit inside `<body>`, all `position: fixed` or
`sticky`) the moment any of the three states were toggled, regardless of whether the wrapping was
scoped to the widget correctly. A framework-level widget has no way to know what fixed/sticky elements
a given host app has, so `filter` on a shared ancestor was never going to be a safe mechanism here,
independent of the `:not()`-selector mistake.

**Fixed by switching Contrast/Invert/Color Blindness from `filter` to `mix-blend-mode` overlays** - two
new `position: fixed; inset: 0;` divs (`#zfw-a11y-fx-contrast`, `#zfw-a11y-fx-colorblind`), siblings of
the toggle/panel inside `#zfw-a11y-root`, each `pointer-events: none` so they're inert to clicks
regardless of state:
- **Invert**: a white overlay with `mix-blend-mode: difference` - the standard compositing trick for
  exact color inversion (`result = |backdrop - white|` per channel, identical to `filter: invert(1)`),
  achieved without ever touching any element's `filter` property.
- **High contrast**: a mid-gray overlay with `mix-blend-mode: overlay` at partial opacity - the same
  "push toward an S-curve" trick used in photo editing, a reasonable visual approximation of a contrast
  boost (not pixel-identical to `filter: contrast()`, an acceptable, disclosed simplification).
- **Color Blindness**: a mid-gray overlay with `mix-blend-mode: saturation` at partial opacity -
  `saturation` blending takes the *source* (overlay)'s saturation and the *backdrop*'s hue/luminosity,
  so a 0%-saturation gray overlay desaturates the backdrop proportionally to the overlay's own opacity,
  reproducing the same visual result as `filter: saturate()` through compositing instead.

Because none of these three properties (`background`, `mix-blend-mode`, `opacity`) establishes a new
containing block, the overlays change what gets *painted*, never anyone's positioning - the host page's
own fixed/sticky elements are now provably unaffected by construction, not just by coincidence of
scoping. The now-unnecessary counter-filter on `#zfw-a11y-root` for Invert mode was removed entirely -
nothing needs counter-inverting anymore, since nothing outside the two new overlay divs is ever
filtered.

**Verified against erweb**: reproduced the original bug first (`toggle.getBoundingClientRect()` moving
from viewport-relative to document-relative, confirmed via ancestor-walk that `<body>` was the filtered
element), then confirmed the fix directly - the toggle button's rect is now byte-identical
(`{top:830,left:20,bottom:880}`) before any change, after Contrast=high, after Contrast=invert, after
also enabling Color Blindness on top of Invert, and after scrolling 800px with both active
simultaneously; `.back-to-top` and `.thread` (erweb's own pre-existing fixed elements) never moved
either; `.site-header` stayed pinned to `top: 0` on scroll throughout. Screenshots confirm all three
effects still look visually correct (inverted, higher-contrast, desaturated) and that the widget's own
panel stays fully legible in every state, same as before - just achieved without depending on the
panel-level counter-filter this used to require. `php -l`, the standalone `render()` smoke test (zero
unresolved placeholders), `node --check` on the extracted `<script>`, and the HTML tag-balance check
all re-run clean. `bin/check_integrity.php` clean. **Files**: `core/lib/Accessibility.php` only.

### Accessibility widget converted into a real module: `core/modules/accessibility/` (2026-08-27)

At the requester's ask, redesigned - not just relocated - the accessibility widget above around
ZeusFW's own `moduleClass`/`.info.yaml`/`.zetem` module convention (`core/modules/`, the same shape
`core/modules/admin/` and zpms's `web/modules/backup/` already use), replacing the standalone
`core/lib/Accessibility.php`/`accessibilityClass` entirely. Requested explicitly as "keep features the
same if not extend" - every profile/option/interaction from the entry above is unchanged; one real
extension was added (see below), and two others considered were deliberately left out.

**Why no smaller pilot module was converted first.** Every existing module in `core/modules/` (9) and
zpms's `web/modules/` (9) already follows the same convention - none of them build HTML/CSS as inline
PHP strings the way the old `Accessibility.php` did, so there was no smaller "convert this trivial one
first" candidate; the only other inline-HTML/CSS code anywhere in this framework is
`ErrorHandlers.php`'s crash-page builder, deliberately self-contained for crash-resilience (a real crash
is more likely to have already broken the same render/DB pipeline a `moduleClass` depends on) and
therefore not a valid pilot either. Converted `Accessibility.php` itself directly.

**Why `attach_library()` isn't the asset-delivery mechanism here.** Read `Kernel::renderPage()`
(`core/kernel/Kernel.php:482-568`) directly: it calls `renderRegions()` first, then builds `$links`/
`$foot_links` from `getConfig('css')`/`getConfig('foot_script')`, and only *then* renders `main.zetem`
with those arrays already finalized. Since this widget is invoked ad hoc from inside `main.zetem`'s own
body (not a `structure:` region), any `attach_library()` call from its template would run *after*
`$links`/`$foot_links` were already built - a silent no-op, not just a cascade-order surprise. Instead,
`accessibilityModule::render()` resolves its own CSS/JS URLs directly via `resolveModuleDir()`+
`rel_url()` (the same helpers `attach_library_helper()` uses internally) and emits plain `<link>`/
`<script src>` tags at the template's own position - functionally equivalent, without depending on
`renderPage()`'s asset-array timing. `accessibility.yaml` still declares a `libraries:` block for shape-
consistency with every other module (and as a ready path to a future `structure:`-region adoption), it
just isn't the mechanism actually delivering the assets today.

**Files** (`core/modules/accessibility/`): `accessibility.info.yaml` (`name=accessibility`/
`template=accessibility.zetem`/`class=accessibilityModule`); `accessibility.yaml` (the `libraries:`
block above, plus a new `default_options:` block - see below); `css/accessibility.css` and
`js/accessibility.js` (the old inline `<style>`/`<script>` moved verbatim - the `mix-blend-mode`-not-
`filter` fix from the entry above is unchanged, still fully documented in the CSS's own comment);
`accessibility.php` (`class accessibilityModule extends moduleClass` - `self::LABELS` copied verbatim,
constructor loads `accessibility.yaml` via `resolveModuleDir()`/`addConfig()` matching zpms's
`backupModule`, captures its own `$adir` in a private property since `moduleClass`'s own `$moduledir` is
private/not inherited); `core/templates/modules/accessibility/accessibility.zetem` (the markup, `$L()`
calls replaced with `{{ $labels['x'] | e }}`, matching the template-placement convention both real
examples use - a module's `.zetem` lives under a separate `templates/modules/` tree, not colocated with
its own directory).

**The one real extension: config-driven profile/option enablement.** `accessibility.yaml` gains:
```yaml
default_options:
  profiles: [blind, colorblind, dyslexia, epilepsy]
  options: [contrast, fontsize, letterspacing, hideimages]
```
(all 8 enabled, matching prior behavior exactly). `accessibilityModule::render($params)` accepts an
optional `profiles`/`options` array in `$params`, intersected against the fixed 4+4 valid-key set (a
caller can only narrow what renders, never invent a nonexistent profile/option) and merged over the YAML
defaults. A disabled profile/option is omitted from the rendered markup **entirely**, not just hidden
via CSS - `js/accessibility.js` null-checks every corresponding `getElementById()` lookup before wiring
its listener, since a control it expects may genuinely not exist in the DOM. This is a real capability
the old bare `core/lib/*.php` class never had a natural home for (no per-module config file of its own);
erweb's own call site doesn't use it today (still renders all 8), but a future app - or erweb later -
can disable e.g. the Blind profile's read-aloud complexity via one YAML edit.

**The dynamic JS label strings** (contrast-level labels, read-aloud play/pause/stop labels, the
"unsupported" message) moved from `json_encode()`'d PHP-to-JS interpolation in the old inline `<script>`
to `data-zfw-a11y-*` attributes on `#zfw-a11y-root`, read via `dataset` in the now-external
`accessibility.js` - the same mechanism zpms's `pdflib.js`/`location.js` already use (a module's own
template renders values onto an element server-side, external JS reads them via `dataset`), reusing
`{{ ... | e }}`'s existing HTML-attribute escaping rather than inventing a new data-passing convention
(grepped: no `<script>window.X = {...}</script>` data-island precedent exists anywhere in this
ecosystem).

**The external `<script src>` deliberately carries no `defer`** - the original inline `<script>`
executed synchronously at its DOM position, applying a returning visitor's saved state as early as
possible; `defer` would delay that until the whole document finishes parsing, a real regression against
this widget's own purpose.

**Two extensions considered and deliberately left out** (so their absence isn't mistaken for an
oversight): server-side-persisted preferences tied to a logged-in user (erweb has no user/auth system to
attach them to); an "Accessibility Statement" link inside the panel (no real statement page exists to
point at). Cookie/session-based state so the *server* pre-applies saved effects on first paint was also
considered and rejected - the module's own template only renders a body-scoped fragment, and the effect
classes it controls live on `<html>`, reachable only from `main.zetem` (an app-level template, not this
module).

**Registration is app opt-in, not core-wide.** `core/bootstrap.php` had exactly one line removed
(`require_once(__DIR__ . "/lib/Accessibility.php");`) and nothing added - unlike `admin_crud.php`'s
unconditional-registration precedent, this module is picked up only when an app adds `accessibility` to
its own `settings.info.yaml`'s existing `modules:` list (see erweb's own `CLAUDE.md` entry). Chosen
deliberately to keep this change's blast radius to whichever app opts in, since `core/bootstrap.php` is
shared by every app vendoring this checkout (zweb/zpms/mweb included, none of which reference this
widget).

**Verified**: `php -l` on `accessibility.php` and `core/bootstrap.php`; `node --check` directly on the
now-standalone `js/accessibility.js` (no more heredoc-extraction step needed, an improvement over the
old workflow); a real end-to-end request against erweb's dev server (not just a standalone render)
confirmed the widget's markup, all 14 `data-zfw-a11y-*` attributes, and both asset URLs resolve
correctly, and that `/core/modules/accessibility/{css,js}/accessibility.*` actually load over HTTP
through erweb's `web/core -> ../fw/core` symlink (the concrete check that would have caught the
`attach_library()` timing hazard had it been missed); a full Playwright re-run of every prior
verification - all 4 profiles including Blind's read-aloud, all 4 page options, `localStorage`
persistence, Reset, **the toggle button's `getBoundingClientRect()` staying viewport-stable across every
Contrast/Invert/Colorblind state and after scrolling 800px** (re-run specifically since the asset-
delivery mechanism changed, even though the CSS bytes didn't - this is the exact regression the
`mix-blend-mode` fix above addressed), zero console/page errors (one pre-existing, unrelated
`favicon.ico` 404 confirmed present on a bare page load with no widget interaction at all), desktop
1440px and mobile 390px, both `/el` and `/en`; a standalone-render config-driven-extension check
confirmed `['profiles' => ['dyslexia'], 'options' => ['contrast']]` renders only those 2 controls (the
other 6 fully absent from the markup - the `zfw-a11y-fx-colorblind`/`readaloud` elements gated off too,
not just the buttons) with zero unresolved template tokens and a balanced div count, that an unknown key
(`'nonexistent'`/`'bogus'`) is silently dropped rather than rendered, and that the no-override call still
renders all 8 exactly matching `default_options`; `bin/check_integrity.php` clean on erweb;
`grep -rn accessibilityClass` across zeusfw/erweb returns no live code references (only this file's own
historical prose and the new module's explanatory comments, both expected). **Files**:
`core/modules/accessibility/{accessibility.info.yaml,accessibility.yaml,accessibility.php,
css/accessibility.css,js/accessibility.js}`, `core/templates/modules/accessibility/accessibility.zetem`
(all new), `core/bootstrap.php` (1 line removed), `core/lib/Accessibility.php` (deleted).

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

## `diff:sql`/`diff:sql:all` falsely flagging `UNIQUE` columns forever (2026-09-05)

`syncTableWithYAML()` (`core/maker/functions.php`) compares each yaml
field's `createFieldDefinition()`-built type string against a `DESCRIBE
$table`-introspected definition to detect schema drift. MySQL's
`DESCRIBE` never echoes `UNIQUE` back into a column's `Type`/`Extra` --
it's a separate index, surfaced only via the `Key` column (`'UNI'`). But
this codebase's own established convention (`ernsauth_sso_attempts.yaml`'s
`username` field, see its own docblock) deliberately bakes `UNIQUE`
straight into the yaml `type:` string, since `createFieldDefinition()` has
no separate `unique:` option and a hand-added `ALTER TABLE` in the
generated `.sql` doesn't survive the next `spill:sql`. Comparing that
yaml-side string verbatim against the DB-introspected one (which can
*never* contain the word) produced a permanent, unfixable diff --
`ernsauth_sso_attempts`'s `username` column showed up on every
`diff:sql`/`diff:sql:all` run, even immediately after applying the exact
`ALTER TABLE ... MODIFY ... UNIQUE` statement the tool itself recommended.

Fixed by splitting the comparison in two: strip `UNIQUE` out of the
type-string comparison entirely, and separately compare whether the yaml
expects it (`preg_match('/\bUNIQUE\b/i', ...)`) against whether the real
column already has it (`$existing['Key']` is `'UNI'` or `'PRI'`). Only a
genuine mismatch between those two now triggers the diff.

Verified against a real MariaDB test DB in both directions: the
`ernsauth_sso_attempts.username` false positive (already has the index
applied) is gone, while a throwaway copy of that table with the `UNIQUE`
index dropped still correctly reports the missing-constraint diff --
confirming the fix distinguishes "already unique" from "not yet unique"
rather than just silencing the check outright. zpms's own
`bin/run_tests.sh` (30/30 static, 35/35 functional) stayed green
throughout.

### `core/modules/google_analytics/` -- Google Analytics (GA4) module, consent-gated (2026-09-17)

At direct request: a reusable framework-level module wrapping GA4's `gtag.js` behind a real
cookie-consent banner, so any app on this framework gets working, GDPR-appropriate analytics via a
one-line `modules:` list addition instead of hand-rolling the same consent/gtag dance per app. First
adopter: erweb, which already had exactly this need reserved in its own config
(`erweb_ga4_measurement_id`, a still-TODO placeholder) and a hand-rolled, single-app version of the
same idea (`web/js/consent.js`/`web/css/consent.css`/`templates/partials/erweb-consent-banner.zetem`)
sitting **completely unhooked** since it was first built -- see erweb's own `CLAUDE.md` for the
migration off those files onto this module.

**Shape: identical to `core/modules/accessibility/`, not a coincidence.** Same
`moduleClass`/`render($params)`/opt-in-via-an-app's-own-`modules:`-list convention, same reason a
`moduleClass` delivers its own CSS/JS directly via `resolveModuleDir()`/`rel_url()` rather than
`attach_library()` (this module is meant to be invoked ad hoc from inside a template's own body -- e.g.
right next to the accessibility widget's own call site near `</body>` -- not via a `structure:` region,
so `attach_library()` would run after `Kernel::renderPage()`'s `$links`/`$foot_links` arrays are already
built, the identical timing hazard accessibility's own docblock documents). Bilingual `LABELS` const with
an `en` fallback, mirroring `accessibilityModule::LABELS` exactly -- except every string here is also
overridable per call via `render()`'s own `labels` param (`array_merge`'d over the built-in set), since a
consent banner's exact wording is more likely to need a site-specific tweak than an accessibility panel's
fixed UI chrome.

**No app-specific config baked in anywhere** -- unlike `accessibility.yaml`'s `default_options` (which
narrows a fixed, framework-owned set of profiles/options), this module has nothing of its own to
default: the one thing every app must supply, its GA4 `measurementId`, is passed as a `render()` param
every single time, resolved however that app already resolves its own config (erweb's
`erweb_ga4_measurement_id()`; a different app might read a different `site.info.yaml` key entirely). This
mirrors `Recaptcha.php`'s own "no app-specific config baked into the class -- the caller always supplies
its own site/secret keys" convention, not `accessibility.yaml`'s config-file pattern, since a
measurement ID is a per-deploy secret-shaped value, not a feature toggle.

**Fails closed on consent, matching `Recaptcha.php`'s own fail-closed-on-verification convention**:
`gtag.js` is never even requested until an explicit "Accept" click is stored in `localStorage` --
per-visitor, never sent to the server, same convention `accessibilityClass`'s own widget state already
established. A missing/empty/TODO-containing `measurementId` also short-circuits before any network call
fires (`js/google-analytics.js`'s own `loadGtag()` guard), regardless of what a visitor clicks -- so a
fresh app that hasn't configured a real ID yet, or one deliberately keeping analytics off pending
consent-banner review, never leaks a request to Google no matter what happens client-side.

**A real bug in exactly that guard, caught only by driving it with a real browser, not by reading the
code.** The first cut copied erweb's own original (never-actually-run) `consent.js` guard verbatim --
`measurementId.indexOf('TODO') === 0` -- which only catches a placeholder where `TODO` is the literal
*prefix* of the string. erweb's real placeholder is `G-TODOTODOTO` (`G-` first, `TODO` starting at index
2), so the guard silently never fired: a Playwright run against the live erweb dev server, clicking
Accept with that exact placeholder still in `config/site.info.yaml`, showed a real outbound request
queued to `googletagmanager.com` -- the precise failure this module exists to prevent. This bug was
**already present** in erweb's own original `consent.js`, just never caught, because that file's banner
was fully unhooked from the page shell and had never been exercised end to end before this module wired
it up for the first time. Fixed by widening the check to `indexOf('TODO') !== -1` (contains `TODO`
anywhere, not just as a literal prefix) -- re-run against the same live erweb page afterward: 0 requests
to `googletagmanager.com` after clicking Accept with the placeholder ID, confirmed via a Playwright
network-request listener, not just by reading the fixed code. A second run with a fabricated
real-shaped id (`G-REAL12345`, `gtag.js`'s own network calls stubbed via `page.route()` so nothing
actually left the sandbox) confirmed the *happy* path -- `gtag.js` genuinely requested,
`window.gtag`/`window.dataLayer` genuinely defined -- so the fix didn't just make the guard stricter, it
left the working case working.

**CSS is self-contained with its own `--zga-*` custom-property namespace** (deliberately neutral gray +
plain-blue-accept-button default, not this specific practice's oxblood), matching `accessibility.css`'s
own `--zfa-*` convention for exactly the same reason: a framework-level asset with no idea what design
tokens the calling app has, or whether it has any at all. An app that wants its own brand colors on the
Accept button overrides `--zga-accent-bg`/etc. from its own stylesheet rather than forking this file --
documented directly in the CSS's own header comment, with the concrete override line spelled out.

**Data passed to the client via `data-zfw-ga-*` attributes on the banner root**, read via `dataset` in
`js/google-analytics.js` -- the same mechanism `accessibility.zetem`'s own `data-zfw-a11y-*` attributes
use (HTML-attribute escaping via the existing `| e` filter, never a raw PHP-to-JS string interpolated
into a `<script>` body), since `measurementId`/`storageKey` only ever need to reach an HTML attribute,
not a JS string literal.

**Verified against erweb** (not just unit logic): `php -l`/`node --check` clean on the new module files;
booted a real dev server (MariaDB-backed) and confirmed the banner renders correctly in both languages
with the real bilingual copy, the module's own CSS/JS URLs resolve over HTTP (200) through the
`web/core -> ../fw/core` symlink, and `bin/check_integrity.php`/`bin/list_routes.php` stay clean/
unchanged (172 routes, same as before this change -- this is chrome, not content) as a regression check.
Full Playwright pass covering every state transition: banner visible on first load with no prior
consent, hidden immediately after Accept, hidden on a subsequent reload (consent already stored),
visible again once `localStorage` is cleared, hidden after Reject, `localStorage` correctly holding
`accepted`/`rejected` at each step, zero console errors across all of the above, and the accessibility
widget (bottom-left) and this banner (bottom, full-width) confirmed **not** to collide -- both remain
independently clickable/visible with the accessibility panel open at the same time the consent banner is
showing. Screenshotted at desktop (1280px) and mobile (390px), both languages.

**Files**: `core/modules/google_analytics/{google_analytics.info.yaml,google_analytics.yaml,
google_analytics.php,css/google-analytics.css,js/google-analytics.js}` (all new),
`core/templates/modules/google_analytics/google_analytics.zetem` (new). No `core/bootstrap.php` change
-- same as `accessibility`, `registerModules()` already `require()`s a module's own `.php` file
dynamically the moment an app lists it under its own `modules:` config, so nothing in core needs to
change for a new opt-in module to become available framework-wide. See erweb's own `CLAUDE.md` for the
app-level wiring (the `modules:` list addition, the `main.zetem` call site, and the removal of the
now-superseded `consent.js`/`consent.css`/`erweb-consent-banner.zetem`).

## Three real bugs found running `bin/init.sh`/`update.sh` end-to-end against a live MariaDB server (2026-09-18)

At direct request ("actually run init.sh and update.sh against a real MySQL server") -- prompted by
zgeotrack (the GPS-tracking app these two scripts were just fixed for, see that repo's own history)
having no way to complete its own setup instructions without a real database. Installed a real MariaDB
server in the sandbox (`apt-get install mariadb-server`, started via `service mariadb start` -- systemd
itself is blocked by this sandbox's `policy-rc.d`, but the plain SysV `service` wrapper still works) and
drove the *entire* pipeline for real: `fw/bin/init.sh` -> `fw/bin/update.sh` -> `bin/setup.php` -> login
-> add a device through its real webform -> POST a real Overland-shaped GPS batch -> confirm it lands in
the database and shows up on the map/table pages. Every bug below was found because something in that
real chain either lied about succeeding or genuinely 404'd/crashed -- none of the three would have shown
up from reading the code, running `php -l`, or a mocked/SQLite-substituted unit test (the three previous
CLAUDE.md entries' own "not verified: no MySQL server in this sandbox" caveats were exactly the gap that
let these sit undiscovered).

### `bin/init.sh` -- the database-creation step silently did nothing, every time, while reporting success

`sudo mysql -u root -p < admin.sql` combines two things that both want the same stdin: `-p` makes mysql
*interactively* prompt for a password on stdin, and `< admin.sql` has already redirected that exact
stdin to the SQL script. There is only one stdin -- mysql's password prompt reads (and discards, as
a garbled password attempt) admin.sql's own first line instead of ever executing the script, and *still
exits 0* regardless, so the very next line's `if [ $? -eq 0 ]` printed "User and database created
succesfully." on every single run. Confirmed directly, twice, before touching the fix: ran the exact
original line against a real MariaDB server with a real `CREATE USER`/`CREATE DATABASE` script,
watched it print success, then confirmed via `SHOW DATABASES`/`mysql.user` that neither the database nor
the user existed.

Fixed by prompting for the root password into a shell variable *first* (`read -srp`, so it isn't echoed
to the terminal), then feeding `admin.sql` on a stdin nothing else is competing for, passing the password
via `MYSQL_PWD` -- the exact same convention `sql/msql.sh`/`msqldump.sh` already use for this identical
reason (see their own comments, and the previous CLAUDE.md entry documenting them). An empty root
password (the common case on a fresh Debian/Ubuntu MariaDB install, where root authenticates via the
`unix_socket`/`mysql_native_password`-with-no-password default) skips `MYSQL_PWD` entirely rather than
setting it to an empty string, since `mysql -u root` with no password argument at all is the more
standard invocation for that case.

**Verified against a real MariaDB server, both the specific failure and the fix**: the original line
reproduced with a real `CREATE USER`/`CREATE DATABASE` script -- exit 0, "created succesfully" printed,
`SHOW DATABASES`/`mysql.user` confirmed neither existed. The fixed block, run standalone first (same
script, empty password path) -- database and user genuinely appeared this time. Then the *entire real
`init.sh` script*, invoked exactly as a user would (`bash bin/init.sh` from a fresh directory, piped
answers including an empty string at the new password prompt) -- `admin.sql`/`db.php` generated
correctly, the database/user genuinely created, and a real `mysql -u <newuser> -p<newpass> <newdb>`
connection with those exact credentials succeeded. **Files**: `bin/init.sh` only.

### `core/lib/FormElement.php` -- `CheckboxElement` never bound a value, and ignored the per-instance value it was given

Every other input element type (`BasicInputElement`/`InputElement`) renders `value="..."` from
`$this->default_value ?? $this->element['default'] ?? null` -- `CheckboxElement::generateHTML()` set
no `value` attribute at all. HTML's own default for a checkbox with no `value` is the literal string
`"on"` -- harmless for a `varchar` column, but a hard `INSERT`/`UPDATE` failure
(`SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect integer value: 'on'`) for the far more common
case this element exists for: a `boolean`/`tinyint` column, which is every `active`/`expired`/
`is_superuser`-shaped field in this codebase (`users.yaml`, `roles.yaml`, zgeotrack's own
`devices.yaml`). Confirmed live: submitting zgeotrack's own Devices "Add a device" form with Active
checked crashed with exactly that `PDOException`, uncaught, straight through `formsClass::
storeFormResults()` -> `devicesClass->insert()`.

Second, independent bug in the same method: the `checked` attribute only ever read
`$this->element['default']` (the static yaml-declared default), never `$this->default_value` (the
per-instance value a caller's own `$default_values` array supplies via `generateHTMLFormFieldElement()`
-- e.g. an existing row's current state, exactly what an edit form needs). Every other element type
checks `$this->default_value` first; this one silently never did, so re-opening an edit form for any
row already showed the checkbox in whatever state the *schema* default said, never the actual row --
confirmed directly: `zgt_devices_row_edit()` passes the real device's `active` value into
`$default_values`, and before this fix the rendered checkbox ignored it completely (devices.yaml's
`active` form input has no static `default:` at all, so it would never render checked regardless of the
real row's value).

Fixed both in the same method: `$attributes['value'] = '1'` (matching this framework's own existing
boolean convention everywhere else -- `0`/`1`, never a string like `"on"`), and the checked-state check
changed to `!empty($this->default_value ?? $this->element['default'] ?? null)`, matching the other
element types' own fallback order exactly. An unchecked box still submits nothing at all (that's plain,
unavoidable HTML checkbox behavior) -- unchanged, and already exactly what `storeFormResults()`'s update
path treats as "leave the existing value alone" (see that function's own docblock) rather than something
this fix needed to touch.

**Verified against zgeotrack, against a real MariaDB server**: reproduced the exact `PDOException` first
(devices' Active checkbox, checked, submitted as literal `active=on`) with the bug still in place. After
the fix: the same form's checkbox now renders `<input name="active" type="checkbox" value="1">`;
submitting it (now `active=1`, matching what a real checked checkbox in a browser sends) inserted the
row successfully with `active=1` in the database; re-opening that same device's edit form rendered
`checked="checked"` correctly, reflecting the real row's actual value, not the yaml's (nonexistent)
static default. **Files**: `core/lib/FormElement.php` only.

### `core/router/Request.php` -- any route with query-string parameters 404s, full stop

`web/.htaccess`'s `RewriteRule ^(.*)$ index.php?$1 [QSA,L,PT]` is the entire mechanism `Router::
matchRoute()` relies on for matching: the requested *path* itself arrives as `QUERY_STRING` (`$1`), not
`REQUEST_URI`/`PATH_INFO`. `[QSA]` ("query string append") means a request that *also* carries real
query parameters -- e.g. `?device_id=X&key=Y` -- arrives with `QUERY_STRING = "api/overland&device_id=X
&key=Y"`, not just `"api/overland"`. `RequestClass`'s constructor tokenized that whole string on `/`
with no awareness of this, so the route's last path segment ("overland") arrived glued to
`&device_id=X&key=Y` as one token, which can never equal the route table's own literal `"overland"` --
`Router::matchRoute()` finds no match and the request 404s, for *any* route that receives so much as one
query parameter, regardless of the route's own definition.

**Why this went undiscovered until now**: grepped every route across zpms/erweb/zgeotrack -- every
existing dynamic route segment on this framework, on any app, has always been a path token
(`/patient/{id}/edit`, `/admin/{entity}/{id}/edit`), never a `?key=value` query parameter. zgeotrack's
own `/api/overland` (built specifically to match Overland iOS's own webhook contract, which has no way
to carry auth in a path segment) is very plausibly the first route on any app on this framework that
actually needed one -- and confirmed the hard way: reproduced this exact 404 against a real request
before touching anything, using a `php -S` router script that deliberately replicates Apache's real
`[QSA]` rewrite target byte-for-byte (not php -S's own different default PATH_INFO-style dispatch for a
router-less request, which would have masked this) -- so this is a real Apache-shape reproduction, not a
dev-server-only artifact.

Fixed at the source, in `RequestClass::__construct()`: `$this->query` (and the `$this->tokens` route-
match array derived from it) now takes only the part of `QUERY_STRING` before the first `&`
(`strtok($asrvr['QUERY_STRING'], '&')`, cast back to `string` since `strtok()` of an empty string -- the
homepage's own case, once `$1` is empty -- returns `false`, not `''`, and every existing caller of
`getQueryString()` expects a string). Real query parameters never legitimately appear in `$1` itself
(that's a path segment from before the original request's own `?`, if any) -- anything from the first
`&` onward here is always the browser's own appended query string, safe to drop for *routing* purposes.
Confirmed this doesn't lose those parameters for application code: PHP's own native `$_GET` is parsed
independently and upstream of this class, directly from the real `QUERY_STRING`, so `$_GET['device_id']`/
`$_GET['key']` were already correct the whole time -- only the framework's own separate route-matching
tokenization was broken. Grepped every other caller of `getQueryString()`/`getQueryRoute()` before
fixing (`Router::matchRoute()`'s own token array, plus `mainnavigation`/`breadcrumbs`' nav-trail
highlighting) -- none of them want the query-string suffix glued onto the path either, so narrowing
`$this->query` itself at the source is correct for every current caller, not just the one that surfaced
this.

**Verified against a real MariaDB-backed zgeotrack, both the failure and the fix, over real HTTP**: a
POST to `/api/overland?device_id=<guid>&key=<key>` 404'd with the bug in place (confirmed via the actual
route table, not a guess); after the fix, the identical request matched correctly -- a wrong key
returned `401`, a real Overland-shaped GeoJSON batch returned `{"result":"ok"}` and landed in the
database, and confirmed zero regressions on every query-param-free route already covered by this run
(`/`, `/login`, `/devices`, `/profile`, `/admin/users`, `/locations`) -- still 200 on every one.
**Files**: `core/router/Request.php` only.

## `bin/init.sh` -- accepting a shown `[default]` by pressing Enter silently wrote empty values instead (2026-09-18, same-day follow-up)

Reported directly against zgeotrack: "`init.sh` doesn't create the correct `.php` files" -- i.e.
`config/db.php` itself, not the `maker.php`-generated entity classes `update.sh` produces (those are a
separate step; this report was specifically about `init.sh`'s own output). Reproduced by driving the
actual script with piped answers rather than guessing at the cause: every one of the 4 credential
prompts (`read -e -i "$default" -p "..." var`) uses `-i` to pre-fill the bracketed default shown in the
prompt (e.g. `[localhost]`), intending that pressing Enter with no input accepts it -- but `-i`'s
pre-fill only actually takes effect through GNU readline's interactive line-editing, which requires a
real TTY. Outside that (piped input -- exactly how this same file's own `init.sh`/`update.sh` CLAUDE.md
entry above drove every one of its own verification runs -- or any other non-fully-interactive shell),
the `-i` default is silently dropped and an empty Enter produces an **empty string** for that variable,
not the value shown in brackets. Confirmed directly: even the hardcoded `host_in="localhost"` fallback
(used when no `admin.sql` exists yet) came out as `DB_HOST` = `''` after accepting every default this
way -- not a database/username/password-only issue, all 4 fields are affected identically.

**Why this matches "doesn't create the correct files" specifically, not "doesn't create files at all"**:
the script's own `sed` substitution has no validation of what it's substituting -- an empty `$host`
still cleanly replaces `<<host>>` with nothing, `db.php` is still written, and the script still prints
"db.php created succesfully!". The file is real and well-formed PHP, just quietly wrong. The single most
realistic trigger, confirmed directly: re-running `init.sh` on an *already-configured* app (the normal
case for re-running it at all -- e.g. to add the "create the database" step after already having a
working `db.php`) and pressing Enter through every prompt to keep the existing real values, expecting
"just confirm what's already there" -- before this fix, that exact flow silently wiped a previously
correct `db.php`/`admin.sql` down to all-empty values, overwriting real working credentials with nothing
while reporting success at every step.

Fixed with an explicit `var="${var:-$default_in}"` fallback immediately after each of the 4 `read`
calls -- this is a plain bash parameter-expansion default, unrelated to readline/TTY state, so accepting
a shown default now works identically whether the script is run from a real interactive terminal or
driven with piped/redirected input. Also fixed, found while touching this same block: `echo "\n"`
(intended to print a blank line after the silent password prompt) doesn't do that at all -- without
`echo -e`, bash's `echo` prints the two literal characters `\`+`n`, not a newline (visible directly in
this run's own output, a stray literal `\n` line); changed to a bare `echo`, which does what the
original comment says it's for.

**Verified against a real MariaDB server, three ways**: (1) a completely fresh setup accepting every
default -- host now correctly falls back to `localhost`; database/username/password correctly stay empty
(there is no real prior value to fall back to on a truly first-ever run, so this is correct, not a
remaining bug -- the script still requires you to actually type those the first time). (2) The specific
regression scenario: ran a real initial setup with real credentials (`zgeotrack_recheck`/`RecheckPass2026`),
confirmed the database and MySQL user were genuinely created, then re-ran `init.sh` a second time
accepting every prompt's default -- `admin.sql`/`db.php` came out byte-identical to the first run (real
host/user/pass/db, not blanked), confirming the fix. (3) Connected live (`mysql -h ... -u ... -p...`)
using the exact credentials `db.php` held after that second run -- succeeded, proving the "preserved"
values are the real, working ones, not just visually present in the file. **Files**: `bin/init.sh` only.

## `bin/init.sh` -- credential substitution corrupted, or outright emptied, `admin.sql`/`db.php` for a real class of passwords (2026-09-18, second same-day follow-up)

Reported directly, with the exact error: `sed: -e expression #1, char 44: unknown option to `s'` right
after the previous entry's fix. Reproduced immediately rather than guessing: the `sed -s "s/<<pass>>/
$password/g"` chain uses `/` as sed's own delimiter, so any credential containing a `/` (e.g. a password
of `Ab/c123`) makes sed read `s/<<pass>>/Ab` as the whole command, `c123` as a bogus extra field --
exactly the reported error. **Worse than the error message alone**: the pipe's stdout was empty when
sed errored, so `> $ofile` still truncated the file to zero bytes, and this block never checked sed's
own exit status -- it printed `$ofile created succesfully!` immediately after silently emptying a
previously-working file.

**First fix attempt, caught as wrong by testing it rather than trusting it**: switched the substitution
to pure bash (`${content//<<pass>>/$password}`, no sed at all) on the theory that literal string
replacement has no delimiter to collide with. Tested against an `&` password before moving on, and it
failed too -- bash 5.2 (confirmed via `bash --version` in this sandbox) gives a literal `&` inside a
parameter-substitution *replacement* the exact same "insert whatever the pattern matched" meaning sed's
own `&` backreference has (isolated proof: `x="X"; r="A&B"; echo "${x/X/$r}"` prints `AXB`, not `A&B`) --
so an `&` password would have silently written the literal placeholder text into the credential instead
of itself, the same "wrong file, no error" failure shape as the sed bug, just via a different mechanism.
A lone backslash in the replacement is separately consumed as an escape character too, confirmed the
same way.

**Second fix, layer 1**: escape both hazards in each value before it ever reaches the substitution --
double every backslash, then prefix every `&` with the now-doubled backslash. Fixes the substitution
mechanism itself.

**Third, independent layer, found only by testing the layer-1 fix against a real live database rather
than trusting file contents**: host/user/pass all sit inside a single-quoted string literal in *both*
generated files, and MySQL's and PHP's own single-quoted-literal parsers each apply their own escaping
rules when *they* read the file -- completely independent of how carefully the file was written. Layer 1
alone wrote a `\&` password byte-for-byte into both files, but MySQL silently drops a backslash before
any character it doesn't recognize as an escape sequence (confirmed against a real server:
`SELECT HEX('Re/check\&2026')` decodes to `Re/check&2026`, backslash gone) -- so the account MySQL
actually created ended up with a different password than the byte-identical string `db.php` held, and a
real login with `db.php`'s "correct" password then failed outright (`ERROR 1045 Access denied`,
reproduced against a real MariaDB server before touching the fix). PHP single-quoted strings share the
identical rule (only `\\` and `\'` are recognized), so `db.php` has the same exposure for a raw `'`.

Fixed by escaping for the *target-language* literal first (backslash doubled, single quote escaped --
the one rule both MySQL and PHP single-quoted strings share), then layering the bash-substitution
escaping from layer 1 on top of that already-escaped value, not the raw one. `database` is deliberately
exempt from this target-language layer -- it's the one placeholder that's never inside quotes in either
template (`CREATE DATABASE IF NOT EXISTS <<db>>;`, a bare identifier), so literal-escaping it would be
wrong, not just unnecessary; it still gets the layer-1 substitution-safety escaping like the others.
Verified by having real MySQL (`HEX()`, to see past `mysql -B`'s own batch-mode output re-escaping, which
first looked like a fourth bug and wasn't -- it was this test harness reading the client's redisplay
convention, not the actual stored value) and real PHP (`php -r "echo '...';"`) parse the generated
literals back, not just diffing file bytes, since correct output is now expected to *differ* from the
raw typed input (a lone `\` legitimately becomes `\\` on disk).

**A fourth bug, found by testing the layer-1+2 fix's own re-run path**: re-running `init.sh` against an
already-saved tricky password and accepting the shown default doubled its backslash count on every
successive re-run. Cause: the block that reads an *existing* `admin.sql` back to offer its real values as
this run's defaults (`grep -oP "IDENTIFIED BY '\K[^']+(?=')"`) extracts the raw on-disk SQL-literal bytes
-- already escaped -- and had always treated them as if they were the original unescaped value, which
was harmless before today (escaping was a no-op for a plain password) but now feeds an already-escaped
value straight back through `escape_for_quoted_literal()` a second time on the next write. Fixed with
`unescape_quoted_literal()`, the exact inverse of the write-side function, applied to `host_in`/
`username_in`/`password_in` right after extraction. Verified stable across 3 successive re-runs of a
`Re/check\&2026` password, plus a real login on the account after all 3.

**Read separately breaks a backslash before any of the above ever sees it**: `read` (no `-r`) treats a
backslash in the *typed line itself* as an escape character and drops it, confirmed in isolation
(`Ab\c123` piped into `read` without `-r` comes back as `Abc123`) -- independent of every fix above,
since it happens before the value is even stored in the shell variable. Added `-r` to all 4 `read`
calls (host/database/username/password), matching the convention `sql/msql.sh`/`msqldump.sh` already
use for the identical reason.

**One remaining, narrower, pre-existing, and disclosed-not-fixed limitation**: a password containing a
literal `'` (not `\`) still gets truncated on *re-read* from an existing `admin.sql`, since the
extraction regex's `[^']+` has no way to distinguish an escaped `\'` from the real closing quote --
unchanged from before today, only reachable by re-running `init.sh` against an already-saved
quote-containing password, and confirmed as exactly this shape (not a new regression) rather than left
unexamined.

**Verified end-to-end against a real MariaDB server and real PHP, not just bash string comparison**: a
battery of passwords (`/`, `&`, `\`, `'`, `\&` combined, `\` + `'` combined, and a plain password as a
control) each round-tripped through real `mysql ... < admin.sql` account creation, a real
`mysql -h ... -u ... -p...` login using exactly what `db.php` held, and real `php -r` parsing of
`db.php`'s own literal -- for the exact password that produced the originally-reported error
(`Re/check\&2026`): real account created, real login succeeded, and the value stayed byte-stable (via
PHP's own parsing, not raw file bytes) across 3 further re-runs accepting every default. **Files**:
`bin/init.sh` only.

## "Remember me" silently failing to log a returning visitor in -- a leftover device-fingerprint filter one step past where the same bug was already half-fixed (2026-09-19)

Reported directly against zgeotrack, generalizing "remember me doesn't work, do not filter by ip" -- the
literal IP filter was already gone (see `core/ClassExFW.php::getUserByToken()`'s own pre-existing "stop
using ip for logging in" comment, from earlier work), but a second, equally strict filter on
**user-agent** was sitting one step further down the exact same code path and had been missed at the
time.

`Kernel::isUserLoggedin()` (`core/kernel/Kernel.php`) already validates a `zeusfwrememberme` cookie
correctly, in two steps: `userTokensClassEx::token_is_valid($token)` first -- selector+validator only, a
real cryptographic check, no device fingerprint involved at all -- and only once that passes does it call
`getUserByToken($token, $remoteip, $useragent)` to actually fetch the account. That second call's own SQL
still had `AND useragent=:useragent` in its `WHERE` clause. Since the token was already proven
cryptographically valid by the first check, this second filter was pure redundancy that could only ever
turn a legitimate remember-me login into a silent failure -- exactly what happened the instant the UA
string differed even slightly from whenever the cookie was first issued (an app update, a browser/OS
update, a different WebView/browser context -- all realistic on a phone, none of them a sign the cookie
was stolen). And the failure was *silent*: that branch of `isUserLoggedin()` doesn't clear the cookie or
show any message on a mismatch, it just quietly falls through to `return false` -- from a visitor's side,
indistinguishable from "remember me just doesn't work."

**Fixed** by dropping the `useragent` filter from `getUserByToken()`'s `WHERE` clause too (mirroring the
IP fix already sitting right above it in the same file), and simplifying `isUserLoggedin()`'s call site to
match (`remoteip`/`useragent` params on `getUserByToken()` kept, but now optional/unused, so existing call
shapes elsewhere don't need to change). `delete_user_token()` in the same file still filters by both --
confirmed dead code first (`grep`, zero call sites anywhere in zeusfw/zpms/zgeotrack, fully superseded by
`delete_by_selector()` per `logout()`'s own comment) -- left untouched rather than "fixed" code nothing
calls.

**A second, independent bug found in the same function while fixing the first**: the successful
cookie-restore path called `$kernel->loginUser($us->getuname(), $us->getroles())` -- the legacy
`users.roles` column directly, *not* `zeusfw_app_resolve_user_roles()`, the RBAC-aware hook `login_post()`
itself already uses for a fresh password login (`core/lib/UserLogin.php`). Any RBAC-only app (roles/
permissions/user_roles tables, no per-account `users.roles` value ever populated -- zgeotrack among them)
would have had a remember-me restore establish a session with the *account* correctly identified but with
empty/wrong roles, so every `rbacClass::require()` check downstream would then fail -- from the visitor's
side this reads as "remember me doesn't really work" just as much as an outright failed restore does, just
one layer further in. Fixed to call the same `function_exists('zeusfw_app_resolve_user_roles')` resolution
`login_post()` already uses, so a cookie-restored session ends up with identical roles to a fresh password
login for the same account.

**Verified against zgeotrack, against a real MariaDB-backed instance, reproducing the actual failure
first**: checked out the pre-fix commit into a disposable local test checkout, logged in as the real
`administrator` account with "remember me" checked from one User-Agent, then made a *second*, completely
separate request -- fresh cookie jar containing only the remember-me cookie (no session cookie at all,
simulating a genuinely new visit) -- to `/devices` (an `operator`/`administrator`-gated page) using a
*different* User-Agent string. Confirmed the bug reproduces exactly as diagnosed: `302 -> /login`, silent
failure, no error shown. Re-ran the identical test against the fixed code: `200 OK`, the real Devices page
with real data (not a disguised error page -- grepped the response for the actual device's own name).
Also confirmed token rotation (a documented, deliberate, separate feature -- see `isUserLoggedin()`'s own
comment on why the validator half of the cookie changes on every successful use) still works correctly
across this: the second request's `Set-Cookie` carried a freshly rotated validator, distinct from the one
issued at login. **Files**: `core/ClassExFW.php`, `core/kernel/Kernel.php`.

