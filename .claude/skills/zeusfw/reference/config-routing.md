# Configuration, routing, boot

## Config files and merge order

1. `core/config/zeusfw.info.yaml` (framework routes, `libraries:`, default `config_files:`)
2. every entry of the merged `config_files:` list, in order — default `site.info.yaml` (optional) then
   `settings.info.yaml` (`required: true`). A loaded file may append more `config_files:` (each loaded once).

```yaml
config_files:
  - site.info.yaml                    # relative to config/, skipped if missing
  - file: settings.info.yaml
    required: true
  - "@app/config/extra.yaml"          # root macros: @app/ (app root), @core/ (fw core), @mod/ (web/modules, then core/modules)
```

Merging is `array_merge_recursive()`: lists concatenate, **a scalar in two files becomes an array** — keep each
scalar in one file. Read config with `$kernel->getConfig('key')`.

Convention: `site.info.yaml` = per-install values (from `site.info.yaml.in`, often gitignored);
`settings.info.yaml` = the app's structure (routes, menu, regions, modules, roles).

Root macros also work in `templates:`, `modules: path:` and `css:`/`head_script:`/`foot_script:` `src:`
(`@core/css/x.css` -> `/core/css/x.css`). Inside a *module's own* yaml, `@core`/`@app` mean that module's folders.

Useful keys: `title`, `tz`, `languages: [gr, en]`, `multilingual`, `templates:`, `css:`, `head_script:`,
`foot_script:`, `libraries:` (named css/js bundles, `attach_library()`), `roles:` (the valid role names),
`disabled_packages: []`, `analytics: {enabled: true}` (off by default), `remember_me_days`,
`session_idle_minutes` / `session_max_hours` / `session_warn_seconds` / `session_passive_routes` /
`session_name` / `session_save_path` (session policy is off until `session_idle_minutes` is set).

## Routes

```yaml
routes:
  patients_list:
    title: { gr: "Λίστα ασθενών", en: "Patients" }   # or a plain string
    url: "/patients"                                  # or a list of urls; {name} segments -> $params['name']
    handler: patients_list                            # global function in web/index.php (or a module)
    method: get                                       # optional; get|post
    access: doctor secretary                          # optional; role names, space-separated
```

Handler: `function patients_list($params) { global $kernel, $Request; ...; return $html; }` — returns the markup
for the `main_content` region (`Renderer::render('x.zetem', [...])`, `formsClass::renderForm(...)`,
`error_404()`, `error_401()`, `error_403()`, `error_500()`), or redirects with `header('Location: ' . rel_url('/x')); exit;`.
Permission check first line: `if (($e = rbacClass::require(MY_PERM))) return $e;`.

A failed route-level `access:` renders 401, or redirects to `/login` if `login_redirect` was given to `boot()`.

Query strings: `$Request->getParam('page', 1)`, `$Request->getParams()`. A `&`/`?` percent-encoded inside a path
segment survives. The `php -S` test router must append `&<query>` to `QUERY_STRING` like Apache's `[QSA]`.

Framework routes always present (from `zeusfw.info.yaml`): `/admin/{entity}[...]` (admin CRUD),
`/login/ernsauth/{start,poll,exchange}`, `/session/status` (GET), `/session/ping` (POST, CSRF header).
Apps define their own `login`/`login_post`/`logout` routes pointing at the core handlers.

## web/index.php and Kernel::boot()

```php
include_once(__DIR__ . "/../config/db.php");       // before new Kernel(): defines the DB constants
require_once(__FWDIR__ . '/bootstrap.php');
// app requires, then hooks:
function zeusfw_app_boot() { /* runs after session/remember-me/language, before modules */ }
$kernel = new Kernel($_SERVER, "../config");
$kernel->boot([
  'csrf_login' => true, 'csrf_webforms' => true, 'login_lockout' => true,   // defaults true
  'login_redirect' => '/login',                                             // opt-in
  'default_language' => 'gr',                                               // default 'en'
]);
// route handler functions below (top-level functions, so they exist before boot() dispatches)
```

`boot()` sets the globals `$Request`, `$router`, `$content_response` that core modules read. Optional crash page:
`zeusfw_register_error_handlers($cssPath)` after `Renderer::init()` (erweb does its own bootstrap for this).

## Page composition

```yaml
regions:                              # rendered in order into main.zetem/page.zetem
  - header:
      access: authenticated           # region-level access: (role names)
  - main_navigation
  - notification
  - main_content
structure:                            # region -> blocks (modules) and nested sections
  header: [ ... ]
modules:
  path: [ "@core/modules/", "@app/web/modules/" ]   # first path holding a module wins
  modules: [ admin, mainnavigation, breadcrumbs, userblock, content, session_timer, ... ]
modconf:
  breadcrumbs:
    access: authenticated             # module-level access:
  message:
    hide: [login]                     # route names
    display:
      userprofile: { arguments: { type: "Announcements" } }
      __all__: { ... }
```

A region/section whose modules all render `''` drops its wrapper div.

## Menu

```yaml
menu:
  main:
    - patients:                       # item key; its other keys sit at the same indent
      text: { gr: "Ασθενείς", en: "Patients" }
      access: doctor secretary        # role names
      submenu:
        - patients_list:              # key = route name, for highlighting/breadcrumbs
          text: { gr: "Λίστα", en: "List" }
          url: /patients
          permission: patients-view-list   # RBAC slug; if both are set, access: and permission: must pass
          breadcrumb_aliases: [patient_edit, patient_view]   # routes not in the menu get this trail
```

Other item keys: `icon: {class: ...}`, `submenu-class:`.

Breadcrumb segment links are decorative (`#`).
