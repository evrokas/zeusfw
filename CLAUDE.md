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
