# Zeus Render Array System

A Drupal-inspired render array system for the Zeus Framework, providing hierarchical, array-based content rendering with support for access control, weight-based ordering, and template integration.

## Files

- **RenderArrayManager.php** - Core render engine and orchestration
- **RenderElementTypes.php** - Built-in element type renderers (markup, container, link, html_tag, template, module)
- **RenderAccess.php** - Access control helper for permission checking
- **RenderHelpers.php** - Convenience helper functions for building render arrays

## Quick Start

### Basic Usage

```php
// Simple markup
$render = [
    '#type' => 'markup',
    '#markup' => '<p>Hello World</p>',
];
echo RenderArrayManager::renderPlain($render);

// Container with children
$render = [
    '#type' => 'container',
    '#attributes' => ['class' => ['patient-card']],
    'name' => ['#markup' => '<h3>John Doe</h3>'],
    'age' => ['#markup' => '<p>Age: 45</p>'],
];
echo RenderArrayManager::renderPlain($render);

// Using helper functions
$render = render_link('View Patient', '/patients/123', [
    'class' => ['btn', 'btn-primary']
]);
echo RenderArrayManager::renderPlain($render);
```

### Integration with Route Handlers

```php
function patients_list($params) {
    SecurityClass::require('patients-view-list');

    $patients = patientsClassEx::search();

    $render = [
        '#theme' => 'patients-list.zetem',
        '#context' => ['patients' => $patients],
    ];

    return RenderArrayManager::renderPlain($render);
}
```

## Core Properties

| Property | Type | Description |
|----------|------|-------------|
| `#type` | string | Element type (markup, container, link, html_tag, template, module) |
| `#markup` | string | Raw HTML markup |
| `#theme` | string | Template name to use |
| `#context` | array | Template context variables |
| `#attributes` | array | HTML attributes |
| `#access` | bool\|string\|callable | Access control |
| `#weight` | int | Sort order (-100 to 100) |
| `#prefix` | string | HTML to prepend |
| `#suffix` | string | HTML to append |
| `#printed` | bool | Mark as already rendered |

## Element Types

### markup
```php
['#type' => 'markup', '#markup' => '<p>Text</p>']
```

### container
```php
[
    '#type' => 'container',
    '#attributes' => ['class' => ['wrapper']],
    'child1' => ['#markup' => 'Content'],
]
```

### link
```php
[
    '#type' => 'link',
    '#title' => 'Click here',
    '#url' => '/path',
    '#attributes' => ['class' => ['btn']],
]
```

### html_tag
```php
[
    '#type' => 'html_tag',
    '#tag' => 'h1',
    '#value' => 'Title',
    '#attributes' => ['class' => ['heading']],
]
```

### template
```php
[
    '#type' => 'template',
    '#theme' => 'patient-card.zetem',
    '#context' => ['patient' => $patient],
]
```

### table
```php
[
    '#type' => 'table',
    '#header' => ['Name', 'Age', 'Status'],
    '#rows' => [
        ['John Doe', '45', 'Active'],
        ['Jane Smith', '32', 'Active'],
    ],
    '#footer' => ['Total', '2 patients', ''],
    '#caption' => 'Patient List',
    '#empty' => 'No patients found',
    '#responsive' => true,
    '#attributes' => ['class' => ['table', 'table-striped']],
]
```

### ajax_link
```php
[
    '#type' => 'ajax_link',
    '#title' => 'Load More',
    '#url' => '/api/patients/load-more',
    '#method' => 'GET',
    '#target' => '#patient-list',
    '#callback' => 'handleResponse',
    '#confirm' => 'Are you sure?',
]
```

### ajax_button
```php
[
    '#type' => 'ajax_button',
    '#value' => 'Save',
    '#url' => '/api/save',
    '#method' => 'POST',
    '#data' => ['field' => 'value'],
    '#callback' => 'handleSave',
    '#button_type' => 'submit',
]
```

## Helper Functions

```php
render_container($children, $attributes, $tag)
render_markup($html, $prefix, $suffix)
render_link($title, $url, $attributes)
render_html_tag($tag, $value, $attributes)
render_template($theme, $context, $suggestions)
render_module($moduleName, $params)

render_table($header, $rows, $options)
render_ajax_link($title, $url, $options)
render_ajax_button($value, $url, $options)

render_add_class(&$element, $class)
render_set_attribute(&$element, $attribute, $value)
render_set_weight(&$element, $weight)
render_set_access(&$element, $access)
render_attach_library(&$element, $library)
```

## Configuration

Configuration is set in `fw/core/config/zeusfw.info.yaml` and can be overridden in `config/settings.info.yaml`:

```yaml
render_system:
  cache_enabled: false
  cache_path: 'web/cache/render/'
  default_cache_max_age: 3600
  type_templates:
    container: 'render/container.zetem'
    link: 'render/link.zetem'
    html_tag: 'render/html-tag.zetem'
```

## Testing

Run the test suite:
```bash
cd fw/testsuite
php run-tests.php --suite=render-array
```

All 21 tests passing ✓

## Demo

View live examples:
```
http://your-site/test/render-array-demo.php
```

## Documentation

See `ZPMS Render Array System - Specifications v1.0.md` for complete specifications.

## Version

Phase 1 MVP - v1.0 (February 2026)

## Backward Compatibility

This system is completely opt-in and backward-compatible. Existing code using `Renderer::render()` continues to work without modification. You can gradually adopt render arrays in new code.
