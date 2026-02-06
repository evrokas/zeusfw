❯ add hooks in render engine to allow themes to modify styling according to specific theme. how would you implement this? do not emit code yet.                 

Excellent question! Let me design a comprehensive **Theme Hook System** for the render array engine. Here's my proposed architecture:

---

## 🎨 Theme Hook System - Design Proposal

### **1. Core Concepts**

#### **Theme Structure**
```
web/themes/
├── default/
│   ├── theme.info.yaml          # Theme metadata
│   ├── theme.php                # Theme hooks and functions
│   ├── css/
│   │   ├── base.css
│   │   └── components.css
│   └── templates/
│       └── overrides/
├── medical-blue/
│   ├── theme.info.yaml
│   ├── theme.php
│   └── ...
└── patient-portal/
    └── ...
```

#### **Theme Definition (theme.info.yaml)**
```yaml
name: 'Medical Blue Theme'
description: 'Professional medical theme with blue accents'
version: '1.0'
base_theme: 'default'  # Theme inheritance

# CSS libraries
libraries:
  - theme-base
  - theme-components
  
# Element type class mappings
element_classes:
  table: ['table', 'theme-table', 'table-bordered']
  container: ['theme-container']
  link: ['theme-link']
  ajax_button: ['btn', 'theme-btn']

# Wrapper templates
wrappers:
  table: 'wrappers/table-wrapper.zetem'
  container: 'wrappers/container-wrapper.zetem'

# Settings
settings:
  sticky_headers: true
  responsive_tables: true
  icon_set: 'boxicons'
  color_scheme: 'blue'
```

---

### **2. Hook Architecture**

#### **Hook Points in Render Process**

```
┌─────────────────────────────────────────────────┐
│  Render Array Rendering Flow with Hooks        │
└─────────────────────────────────────────────────┘

1. render($element)
   ↓
2. ✨ hook_render_array_alter($element)          [THEME HOOK]
   - Modify entire render array structure
   - Add/remove children
   - Change types
   ↓
3. checkAccess($element)
   ↓
4. ✨ hook_element_TYPE_preprocess($element)     [THEME HOOK]
   - Preprocess specific element types
   - Add variables, modify context
   ↓
5. executePreRenderCallbacks($element)
   ↓
6. sortByWeight(children)
   ↓
7. ✨ hook_element_TYPE_attributes($element)     [THEME HOOK]
   - Modify CSS classes and attributes
   - Add theme-specific classes
   ↓
8. renderChildren(children)
   ↓
9. renderElement($element)
   ↓
10. ✨ hook_element_TYPE_wrapper($html, $element) [THEME HOOK]
    - Wrap output in theme containers
    - Add decorative elements
    ↓
11. applyPrefix/Suffix($element)
    ↓
12. executePostRenderCallbacks($element)
    ↓
13. ✨ hook_render_output_alter($html, $element)  [THEME HOOK]
    - Final HTML modifications
    - Add theme markers
    ↓
14. return $html
```

---

### **3. Hook Types**

#### **A. General Hooks**

**`hook_render_array_alter(&$element, $context)`**
- Modify render array before any processing
- Global theme alterations
- Add theme-specific properties

**`hook_render_output_alter(&$html, $element, $context)`**
- Modify final HTML output
- Add theme wrappers or markers
- Last chance modifications

#### **B. Element Type Hooks**

**`hook_element_TYPE_preprocess(&$element, &$variables)`**
- Preprocess variables for specific element types
- Add theme-specific data
- Examples: `hook_element_table_preprocess()`, `hook_element_container_preprocess()`

**`hook_element_TYPE_attributes(&$attributes, $element)`**
- Modify CSS classes and HTML attributes
- Add theme classes automatically
- Example: Add `theme-table` class to all tables

**`hook_element_TYPE_wrapper($html, $element)`**
- Wrap rendered output
- Add decorative containers
- Example: Wrap tables in card containers

#### **C. Property Hooks**

**`hook_theme_classes($type, $element)`**
- Return array of theme classes for element type
- Cascading from base theme to active theme

**`hook_theme_libraries($element)`**
- Attach theme-specific CSS/JS libraries
- Conditional loading based on element type

---

### **4. Theme Registration & Loading**

#### **Theme Discovery**
```php
// In bootstrap or kernel initialization
ThemeManager::init([
    'themes_path' => 'web/themes',
    'active_theme' => 'medical-blue',
    'default_theme' => 'default'
]);

// Automatically discovers and loads:
// 1. Base theme (if specified)
// 2. Active theme
// 3. Registers all hooks from theme.php files
```

#### **Hook Registration**
```php
// In theme.php
function medical_blue_register_hooks() {
    // Register hooks with RenderArrayManager
    RenderArrayManager::registerThemeHook('element_table_attributes', 'medical_blue_table_attributes');
    RenderArrayManager::registerThemeHook('element_container_wrapper', 'medical_blue_container_wrapper');
}

// Hook implementations
function medical_blue_table_attributes(&$attributes, $element) {
    $attributes['class'][] = 'theme-table';
    $attributes['class'][] = 'table-hover';
    
    if (isset($element['#theme_variant'])) {
        $attributes['class'][] = 'table-' . $element['#theme_variant'];
    }
}

function medical_blue_container_wrapper($html, $element) {
    if (isset($element['#theme_card']) && $element['#theme_card']) {
        return '<div class="card theme-card"><div class="card-body">' . 
               $html . 
               '</div></div>';
    }
    return $html;
}
```

---

### **5. Theme Inheritance & Cascading**

#### **Multi-Level Theme System**

```
Base Theme (default)
    ↓ inherits
Sub-Theme (medical)
    ↓ inherits
Active Theme (medical-blue)
```

**Hook Execution Order:**
1. Base theme hooks execute first
2. Parent theme hooks execute next
3. Active theme hooks execute last (can override)

**Class Merging:**
```php
// Base theme adds: ['table']
// Parent theme adds: ['table-striped']
// Active theme adds: ['theme-table', 'table-hover']
// Final: ['table', 'table-striped', 'theme-table', 'table-hover']
```

---

### **6. Theme-Specific Render Array Properties**

#### **New Optional Properties**

```php
[
    '#type' => 'table',
    '#header' => [...],
    '#rows' => [...],
    
    // Theme-specific properties (prefixed with #theme_)
    '#theme_variant' => 'primary',        // Theme variant (primary, secondary, success, etc.)
    '#theme_card' => true,                // Wrap in theme card
    '#theme_classes' => ['custom-class'], // Additional theme classes
    '#theme_icon' => 'bx-user',           // Icon for headers/labels
    '#theme_responsive' => true,          // Use theme's responsive strategy
    '#theme_override' => [                // Override theme defaults
        'sticky_header' => false,
        'striped' => false
    ]
]
```

---

### **7. Implementation Strategy**

#### **Phase 1: Core Theme Infrastructure**

1. **ThemeManager class**
   - Theme discovery and loading
   - Hook registration
   - Theme inheritance resolution
   - Configuration management

2. **Hook Integration in RenderArrayManager**
   - Add hook invocation points in render flow
   - Maintain hook registry
   - Execute hooks in correct order

3. **Theme Configuration Loading**
   - Parse theme.info.yaml
   - Load theme.php
   - Register hooks automatically

#### **Phase 2: Hook Implementation**

4. **Element Type Attribute Hooks**
   - Add hook calls before attribute building
   - Merge theme classes with element classes
   - Preserve manual overrides

5. **Wrapper Hooks**
   - Execute after element rendering
   - Support template-based wrappers
   - Support function-based wrappers

6. **Preprocessing Hooks**
   - Pass element reference for modification
   - Allow adding computed variables

#### **Phase 3: Advanced Features**

7. **Theme Variants**
   - Support `#theme_variant` property
   - Contextual theming (e.g., alert variants: success, warning, danger)

8. **Conditional Theming**
   - Theme hooks can check context
   - Different styling based on route, user role, etc.

9. **Theme Suggestions**
   - Similar to template suggestions
   - Try theme-specific variants first

---

### **8. Configuration Integration**

#### **In config/settings.info.yaml**
```yaml
theme:
  active: 'medical-blue'
  default: 'default'
  
  # Per-route theming
  route_themes:
    '/admin/*': 'admin-theme'
    '/patient-portal/*': 'patient-portal'
  
  # Override specific element defaults
  element_defaults:
    table:
      responsive: true
      striped: true
    container:
      card_style: true
```

---

### **9. Example Use Cases**

#### **Use Case 1: Automatic Table Styling**

**Without Theme System:**
```php
$render = render_table($header, $rows, [
    'attributes' => ['class' => ['table', 'table-striped', 'table-hover', 'theme-table']]
]);
```

**With Theme System:**
```php
// Theme automatically adds classes
$render = render_table($header, $rows);

// Or use theme variants
$render = render_table($header, $rows, [
    '#theme_variant' => 'primary'  // Adds theme-specific primary variant
]);
```

#### **Use Case 2: Branded Containers**

**Theme Hook:**
```php
function medical_blue_container_wrapper($html, $element) {
    if ($element['#type'] === 'container') {
        return '<div class="theme-section">
                  <div class="section-inner">' . $html . '</div>
                </div>';
    }
    return $html;
}
```

**Usage:**
```php
// Automatically wrapped by theme
$render = [
    '#type' => 'container',
    'content' => ['#markup' => 'Patient info']
];
// Output: <div class="theme-section"><div class="section-inner"><div>Patient info</div></div></div>
```

#### **Use Case 3: Icon Integration**

**Theme Hook:**
```php
function medical_blue_element_table_preprocess(&$element, &$variables) {
    // Add icons to header cells
    if (isset($element['#theme_icon'])) {
        foreach ($element['#header'] as &$header) {
            if (is_string($header)) {
                $header = '<i class="bx ' . $element['#theme_icon'] . '"></i> ' . $header;
            }
        }
    }
}
```

---

### **10. Benefits**

✅ **Separation of Concerns**
- Business logic stays in render arrays
- Presentation logic in themes
- Easy to switch themes

✅ **No Hardcoded Styling**
- Developers don't specify theme classes
- Themes control all styling
- Consistent appearance across app

✅ **Flexibility**
- Multiple themes for different sections
- User-selectable themes
- White-label customization

✅ **Maintainability**
- Theme updates don't require code changes
- Centralized styling logic
- Override inheritance

✅ **Backward Compatibility**
- Existing render arrays work unchanged
- Themes are optional
- Gradual adoption

---

### **11. Alternative Approaches**

#### **Approach A: Simple Class Mapping (Lightweight)**
- Just map element types to CSS classes
- No hooks, just configuration
- Easiest to implement, least flexible

#### **Approach B: Full Theme System (Comprehensive)**
- Everything described above
- Maximum flexibility
- More complex implementation

#### **Approach C: Hybrid (Recommended)**
- Start with class mapping and attribute hooks
- Add wrapper hooks when needed
- Defer complex preprocessing until required

---

### **12. Recommended Implementation Order**

1. **ThemeManager basics** - discovery, loading, config
2. **Attribute hooks** - most common use case
3. **Class mapping from config** - simple default styling
4. **Wrapper hooks** - for container theming
5. **Preprocessing hooks** - for advanced cases
6. **Theme inheritance** - cascading themes
7. **Route-based theming** - different themes per section

---

## 🤔 Questions for You

Before I implement this, I'd like your input:

1. **Scope**: Do you want the full system (Approach B) or start simple (Approach C)?

2. **Use Cases**: What specific theming needs do you have?
   - Just CSS classes?
   - Wrapper elements?
   - Icon integration?
   - Multiple themes for different sections?

3. **Priority**: Which hook types are most important?
   - Attribute/class hooks?
   - Wrapper hooks?
   - Preprocessing hooks?

4. **Existing Themes**: Do you already have theme assets/CSS that need to integrate?

5. **Complexity vs Features**: Prefer simpler with less features, or comprehensive from the start?

Let me know your preferences and I'll implement accordingly!