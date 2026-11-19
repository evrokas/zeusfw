# ZETEM templates and modules

## ZETEM syntax (`core/templates/ZETEMTemplate.php`)

| Syntax | Compiles to |
|---|---|
| `{{ $x }}` | `echo $x` (**not escaped**) |
| `{{ $x \| e }}` | HTML-escaped (`htmlspecialchars`, ENT_QUOTES) |
| `{{{ $x }}}` | HTML-escaped echo |
| `{{ $x \| t }}`, `\| upper`, `\| date`, `\| asset`, `\| cache_asset` | filters (`Renderer::filterRegister()` adds more) |
| `{% php code %}` | `<?php code ?>` — `{% if ($a): %} ... {% endif %}`, `{% foreach ($rows as $r): %} ... {% endforeach %}` |
| `{% include 'file.zetem' %}` / `{% extends 'file.zetem' %}` | inlined at compile time |
| `{# comment #}` | removed |

- `|` inside `{{ }}` is always a filter separator: no `A | B` PHP expressions there (`ENT_QUOTES | ENT_HTML5`
  breaks compilation) — compute in PHP first.
- Escape every user/patient value: `| e` or `{{{ }}}`.
- Compiled templates are cached per top-level file in `web/cache/`; editing an *included* template needs
  `rm -rf web/cache/*` (update.sh does this).
- Template names are global: `Renderer::render('patients_list.zetem', $vars)` finds the file in any `templates:`
  path. Module templates live in `<templates>/modules/<module>/<module>.zetem` (core: `core/templates/modules/`).
- Translation: `t('token')` / `t(['gr' => '...', 'en' => '...'])`, placeholders `t('Hello @name', ['name' => $n])`.
  Dictionary in the `dictionary` table (`dictionaryClassEx`).
- Inside a template: `{{ module('recaptcha', ['siteKey' => $k]) }}` renders a registered module ad hoc.

## Writing a module

```
core/modules/<name>/                  (or <app>/web/modules/<name>/)
  <name>.info.yaml                    name=<name>  template=<name>.zetem  class=<name>Module   (key=value lines)
  <name>.yaml                         optional module config (libraries:, defaults)
  <name>.php                          class + register function
  css/, js/
core/templates/modules/<name>/<name>.zetem
```

```php
class fooModule extends moduleClass {
    function render($params = array()) {
        return $this->renderTemplate(['labels' => self::LABELS[$lang] ?? self::LABELS['en'], 'params' => $params]);
    }
}
function register_foo_module() {          // called by registerModules() when 'foo' is listed in modules:
    global $kernel;
    $kernel->registerModule(new fooModule(__DIR__, 'foo', 'foo.zetem'));
}
```

- Opt-in: an app lists the module in `modules: modules:`; nothing in `core/bootstrap.php` changes.
- A module placed in a `structure:` region can use `attach_library()` for its CSS/JS. A module rendered ad hoc
  from a template runs *after* the asset lists are built, so it must emit its own
  `<link>`/`<script src>` tags with `rel_url($kernel->resolveModuleDir(...) . '?' . time())`.
- Pass server values to JS via `data-*` attributes escaped with `| e`, read with `dataset`; no inline JSON islands.
- Framework CSS uses its own custom-property prefix (`--zfa-*`, `--zga-*`, `--zfl-*`); apps override those vars.
  Watch for `*/` inside CSS comments (`--zfa-*/--zga-*` closes the comment and kills the whole stylesheet).
- Bilingual `LABELS` (el/gr + en) with an `en` fallback; let callers override via `labels`.
- Gate disclosure-y modules with config *and* a permission; render `''` (not a 401) when not allowed.

Core modules: `accessibility`, `admin`, `backup` (zops status, `zops_backup_status_file`), `breadcrumbs`,
`content`, `ernsauth_sso`, `google_analytics` (consent-gated GA4), `htmltext`, `language_selector`, `live_edit`
(shows the content template path; `content-live-edit` permission), `mainnavigation`, `message`,
`notifications`, `recaptcha` (client half; server half `recaptchaClass::isHuman()`), `session_timer`, `userblock`.

## Assets and cache-busting

`css:`/`head_script:`/`foot_script:` entries get `?<time()>` appended automatically. Core assets are served
through `web/core -> ../fw/core`.
