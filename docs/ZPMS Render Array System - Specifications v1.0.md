# ZPMS Render Array System - Specifications v1.0

## 1. Overview & Goals

A hierarchical, array-based content rendering system inspired by Drupal's render arrays, designed to work seamlessly with ZPMS's ZETEM template engine, module system, and existing architecture.

**Core Principles:**
- Configuration over code where possible
- Deferred rendering for performance and alterability
- Nested composability
- Type-safe element definitions
- Compatible with existing `Renderer::render()` workflow

---

## 2. Data Structure

### 2.1 Basic Structure
```php
$render_array = [
    '#type' => 'container',           // Element type
    '#markup' => '<div>content</div>', // Raw HTML (for simple cases)
    '#theme' => 'item_list',          // Theme function/template
    '#attributes' => [],               // HTML attributes
    '#access' => true,                 // Access control
    '#weight' => 0,                    // Sort order
    '#cache' => [],                    // Cache configuration
    '#attached' => [],                 // CSS/JS libraries
    '#prefix' => '<div>',              // HTML before
    '#suffix' => '</div>',             // HTML after
    '#pre_render' => [],               // Callbacks before render
    '#post_render' => [],              // Callbacks after render

    // Child elements (no # prefix)
    'child_1' => [...],
    'child_2' => [...],
];
```

### 2.2 Key Naming Convention
- **Keys starting with `#`**: System properties (metadata, configuration)
- **Keys without `#`**: Child render arrays or data properties
- **Keys starting with `_`**: Internal use, won't be rendered

---

## 3. Core Properties

| Property | Type | Description |
|----------|------|-------------|
| `#type` | string | Element type (container, markup, link, html_tag, template, etc.) |
| `#markup` | string | Raw HTML markup (used for simple content) |
| `#theme` | string | Template name to use (e.g., 'patient-card.zetem') |
| `#template` | string | Alias for #theme |
| `#attributes` | array | HTML attributes (class, id, data-*, etc.) |
| `#access` | bool\|callable | Access control (true/false or callback function) |
| `#weight` | int | Sort order (-100 to 100, default 0) |
| `#cache` | array | Cache configuration |
| `#attached` | array | CSS/JS libraries to attach |
| `#prefix` | string | HTML to prepend |
| `#suffix` | string | HTML to append |
| `#pre_render` | array | Callbacks before rendering |
| `#post_render` | array | Callbacks after rendering |
| `#printed` | bool | Mark as already rendered (skip) |
| `#context` | array | Additional context data for templates |

---

## 4. Element Types

### 4.1 Built-in Types

**`container`**
```php
[
    '#type' => 'container',
    '#attributes' => ['class' => ['patient-info']],
    'name' => ['#markup' => '<h3>John Doe</h3>'],
    'age' => ['#markup' => '<p>Age: 45</p>'],
]
```

**`markup`**
```php
[
    '#type' => 'markup',
    '#markup' => '<div class="alert">Message</div>',
]
```

**`html_tag`**
```php
[
    '#type' => 'html_tag',
    '#tag' => 'div',
    '#attributes' => ['class' => ['card']],
    '#value' => 'Card content',
]
```

**`link`**
```php
[
    '#type' => 'link',
    '#title' => 'View Patient',
    '#url' => '/patients/123',
    '#attributes' => ['class' => ['btn', 'btn-primary']],
]
```

**`template`**
```php
[
    '#type' => 'template',
    '#theme' => 'patient-card.zetem',
    '#context' => [
        'patient' => $patient_object,
        'show_actions' => true,
    ],
]
```

**`module`**
```php
[
    '#type' => 'module',
    '#module_name' => 'userprofile',
    '#module_params' => ['user_id' => 123],
]
```

### 4.2 Extensibility
Custom types can be registered via `RenderArrayManager::registerType($name, $callback)`

---

## 5. Access Control

```php
// Boolean
'#access' => SecurityClass::userLoggedIn(),

// Permission string
'#access' => 'patients-view-list',

// Callback
'#access' => function($element, $context) {
    return SecurityClass::hasPermission('patients-edit')
        && $context['patient']->isActive();
},

// Multiple conditions
'#access' => [
    'permission' => 'patients-view',
    'callback' => fn($el, $ctx) => $ctx['patient']->id > 0,
],
```

---

## 6. Caching

```php
'#cache' => [
    'keys' => ['patient', 123],           // Cache key components
    'contexts' => ['user', 'location'],   // Vary by context
    'tags' => ['patient:123'],            // Cache tags for invalidation
    'max_age' => 3600,                    // TTL in seconds (0 = permanent)
],
```

**Cache Integration:**
- Use existing framework or simple file-based cache
- Store in `web/cache/render/` directory
- MD5 hash of keys + contexts as filename

---

## 7. Attached Assets (CSS/JS)

```php
'#attached' => [
    'library' => ['patient-module', 'datatables'],
    'css' => [
        ['src' => 'css/custom.css', 'weight' => 10],
    ],
    'js' => [
        ['src' => 'js/app.js', 'defer' => true],
    ],
    'head_script' => [...],
    'foot_script' => [...],
],
```

Libraries are defined in `config/settings.info.yaml` and attached via `Kernel` configuration merge.

---

## 8. Weight & Ordering

Children are sorted by `#weight` before rendering:
- Default weight: 0
- Negative weights render first
- Equal weights maintain original order (stable sort)

```php
$render = [
    'footer' => ['#weight' => 100, '#markup' => 'Bottom'],
    'header' => ['#weight' => -100, '#markup' => 'Top'],
    'content' => ['#weight' => 0, '#markup' => 'Middle'],
];
// Renders: header → content → footer
```

---

## 9. Callbacks

### 9.1 Pre-render Callbacks
Execute before rendering, can modify the render array:

```php
'#pre_render' => [
    'processPatientData',
    function(&$element) {
        $element['#context']['processed'] = true;
    },
],
```

### 9.2 Post-render Callbacks
Execute after rendering, can modify the HTML string:

```php
'#post_render' => [
    function($html, $element) {
        return '<div class="wrapper">' . $html . '</div>';
    },
],
```

---

## 10. Alteration System

Allow modules to alter render arrays before rendering:

```php
// In module or ClassEx
function hook_render_array_alter(&$render_array, $context) {
    if ($render_array['#type'] === 'patient-card') {
        $render_array['actions'] = [
            '#type' => 'link',
            '#title' => 'Export PDF',
            '#url' => '/patients/' . $context['patient_id'] . '/pdf',
        ];
    }
}
```

Alteration hooks discovered via naming convention: `{module}_render_array_alter`

---

## 11. Template Integration

### 11.1 Automatic Variable Passing
All `#context` keys become template variables:

```php
[
    '#theme' => 'patient-card.zetem',
    '#context' => [
        'patient' => $patient,
        'editable' => true,
    ],
]
```

In `patient-card.zetem`:
```twig
<div class="patient-card">
    <h3>{{ $patient->getName() }}</h3>
    {% if $editable %}
        <a href="/patients/{{ $patient->getId() }}/edit">Edit</a>
    {% endif %}
</div>
```

### 11.2 Theme Suggestions
Support template suggestions based on context:

```php
[
    '#theme' => 'patient-card',
    '#theme_suggestions' => [
        'patient-card--full',
        'patient-card--' . $patient->getId(),
    ],
]
```

Uses existing `TemplateSuggestion` system.

---

## 12. Rendering Process

```
1. Check #access → skip if false
2. Check #printed → skip if true
3. Check cache → return cached if valid
4. Execute #pre_render callbacks
5. Sort children by #weight
6. Render children recursively
7. Determine renderer:
   - If #markup → use markup
   - If #theme → use template
   - If #type → use type-specific renderer
8. Apply #prefix and #suffix
9. Execute #post_render callbacks
10. Store in cache if configured
11. Process #attached (collect for Kernel)
12. Return HTML string
```

---

## 13. API Design

### 13.1 Core Class: `RenderArrayManager`

```php
class RenderArrayManager {
    // Render a render array to HTML
    public static function render(array &$element, array $context = []): string;

    // Render without output (returns HTML)
    public static function renderPlain(array $element, array $context = []): string;

    // Check if element has access
    public static function checkAccess(array $element, array $context = []): bool;

    // Register a custom element type
    public static function registerType(string $type, callable $renderer): void;

    // Register an alteration hook
    public static function registerAlter(string $module, callable $alter): void;

    // Helper: build render array
    public static function build(string $type, array $properties = []): array;
}
```

### 13.2 Helper Functions

```php
// Shorthand builders
function render_container($children = [], $attributes = []): array;
function render_markup($html, $prefix = '', $suffix = ''): array;
function render_link($title, $url, $attributes = []): array;
function render_template($theme, $context = []): array;
```

---

## 14. Integration Points

### 14.1 With Route Handlers
```php
function patients_list($params) {
    SecurityClass::require('patients-view-list');

    $patients = patientsClassEx::search();

    $render = [
        '#theme' => 'patients-list.zetem',
        '#context' => ['patients' => $patients],
        '#cache' => [
            'keys' => ['patients', 'list'],
            'contexts' => ['location'],
            'max_age' => 300,
        ],
    ];

    return RenderArrayManager::renderPlain($render);
}
```

### 14.2 With Modules
```php
class header extends moduleClass {
    function render($params = []) {
        $render = [
            '#type' => 'template',
            '#theme' => 'header.zetem',
            '#context' => ['title' => $this->kernel->getConfig()['title']],
        ];

        return RenderArrayManager::renderPlain($render);
    }
}
```

### 14.3 With Kernel Regions
`renderPage()` can accept render arrays for regions and process attached assets.

---

## 15. File Structure

```
fw/core/render/
├── RenderArrayManager.php      # Main render engine
├── RenderElementTypes.php      # Built-in type renderers
├── RenderCache.php             # Cache handler
├── RenderAccess.php            # Access checking
└── RenderHelpers.php           # Helper functions

web/templates/render/
├── container.zetem
├── html-tag.zetem
├── link.zetem
└── ... (default templates for types)
```

---

## 16. Configuration

In `config/settings.info.yaml`:

```yaml
render_system:
  cache_enabled: true
  cache_path: 'web/cache/render/'
  default_cache_max_age: 3600

  # Type-to-template mapping
  type_templates:
    container: 'render/container.zetem'
    link: 'render/link.zetem'
    html_tag: 'render/html-tag.zetem'

  # Global alteration hooks (module functions)
  alter_hooks:
    - 'patients_render_array_alter'
    - 'billing_render_array_alter'
```

---

## 17. Phase 1 Implementation (MVP)

Start simple with core functionality:

1. **RenderArrayManager** with basic `render()` method
2. **Element types:** `markup`, `container`, `template`
3. **Properties:** `#type`, `#markup`, `#theme`, `#context`, `#attributes`, `#weight`, `#access`
4. **Children rendering** with weight-based sorting
5. **Template integration** with `#theme` and `#context`
6. **Basic access control** (boolean and permission string)

**Defer for Phase 2:**
- Caching system
- Pre/post render callbacks
- Alteration hooks
- Custom type registration
- Complex access callbacks
- Attached assets processing

---

## 18. Backward Compatibility

The render array system is **opt-in**:
- Existing `Renderer::render()` calls continue to work
- Route handlers can return strings (current) or render arrays (new)
- Modules can gradually adopt render arrays
- No breaking changes to ZETEM templates

---

## 19. Example Use Cases

### 19.1 Patient Card Component
```php
$patient_card = [
    '#type' => 'container',
    '#attributes' => ['class' => ['patient-card', 'card']],
    '#cache' => ['keys' => ['patient-card', $patient->getId()]],

    'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $patient->getName(),
        '#weight' => -10,
    ],

    'info' => [
        '#theme' => 'patient-info.zetem',
        '#context' => ['patient' => $patient],
    ],

    'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['actions']],
        '#weight' => 10,
        '#access' => 'patients-edit',

        'edit' => [
            '#type' => 'link',
            '#title' => 'Edit',
            '#url' => '/patients/' . $patient->getId() . '/edit',
        ],

        'delete' => [
            '#type' => 'link',
            '#title' => 'Delete',
            '#url' => '/patients/' . $patient->getId() . '/delete',
            '#attributes' => ['class' => ['btn-danger']],
            '#access' => 'patients-delete',
        ],
    ],
];

echo RenderArrayManager::renderPlain($patient_card);
```

### 19.2 Dynamic Menu
```php
$menu = [
    '#type' => 'html_tag',
    '#tag' => 'nav',
    '#attributes' => ['class' => ['main-menu']],
];

foreach ($menu_items as $weight => $item) {
    $menu['item_' . $item['id']] = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
        '#weight' => $weight,
        '#access' => $item['permission'] ?? true,
    ];
}

return RenderArrayManager::renderPlain($menu);
```

---

## 20. Testing Strategy

- **Unit tests** for RenderArrayManager core methods
- **Integration tests** with ZETEM templates
- **Performance benchmarks** (render array vs direct Renderer::render)
- **Security tests** for access control
- **Cache effectiveness** measurements

---

## Summary

This specification provides a **Drupal-inspired render array system** that:
- ✅ Maintains simplicity for Phase 1
- ✅ Compatible with existing ZETEM templates and modules
- ✅ Extensible for future features (caching, hooks, callbacks)
- ✅ No breaking changes to current architecture
- ✅ Follows PHP and ZPMS conventions
- ✅ Type-safe and well-documented structure

The system can be implemented incrementally, starting with core functionality and expanding based on project needs.
