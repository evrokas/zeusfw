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
