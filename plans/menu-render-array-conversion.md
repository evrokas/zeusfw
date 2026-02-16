# Convert show_menu Macro to Render Array System

## Context

The current menu rendering system uses a ZETEM macro (`show_menu`) in `show_menu.zetem` that recursively renders menu items with their attributes. This approach works but is tightly coupled to the template layer and doesn't leverage the new Render Array System that has been implemented in the Zeus Framework.

The Render Array System (documented in `/var/www/html/apps/zeusfw/docs/ZPMS Render Array System - Specifications v1.0.md`) provides a Drupal-inspired hierarchical rendering approach with:
- Deferred rendering for performance
- Weight-based ordering
- Access control
- Template integration via `#theme` and `#context`
- Nested composability

This conversion will modernize the menu rendering to use render arrays, making it more maintainable, testable, and consistent with the rest of the framework.

## Current Architecture

### Files Involved
- `/var/www/html/apps/zeusfw/core/templates/menu/show_menu.zetem` - Recursive macro for rendering menu items
- `/var/www/html/apps/zeusfw/core/templates/menu/menu.zetem` - Entry point that calls show_menu
- `/var/www/html/apps/zeusfw/core/modules/mainnavigation/MenuBuilder.php` - Builds menu tree with metadata
- `/var/www/html/apps/zeusfw/core/modules/mainnavigation/MenuThemePreprocessor.php` - Adds presentation attributes
- `/var/www/html/apps/zeusfw/core/modules/mainnavigation/mainnavigation.php` - Menu module orchestrator

### Current Flow
1. MenuBuilder builds menu tree with business logic (titles, URLs, depth, weight, active states)
2. MenuThemePreprocessor adds presentation attributes (CSS classes, IDs, Attributes objects)
3. Module's render() method passes the tree to ZETEM template with `$menu` variable
4. menu.zetem includes show_menu.zetem and calls `{% show_menu($menu, 1) %}`
5. show_menu macro recursively renders each menu item with its attributes

### Current show_menu Macro Structure
The macro handles:
- Simple menu items (just a link)
- Parent items with children (toggle checkbox + label + submenu)
- Parent items with URLs (clickable label)
- Parent items without URLs (non-clickable label)
- Icons, badges, and text for each item
- Recursive rendering of children

## Proposed Solution

### Overview
Convert the menu rendering to use render arrays by:
1. Creating a `MenuRenderArrayBuilder` class that converts the preprocessed menu tree into a render array structure
2. Updating the menu module to build the render array and pass it to the template
3. Simplifying the ZETEM templates to just render the render array using `RenderArrayManager`

### New Architecture

#### 1. MenuRenderArrayBuilder (New Class)
**Location:** `/var/www/html/apps/zeusfw/core/modules/mainnavigation/MenuRenderArrayBuilder.php`

This class will:
- Take the preprocessed menu tree (with attributes from MenuThemePreprocessor)
- Convert it to a hierarchical render array structure
- Handle both sidebar and topbar variants
- Support icons, badges, toggle elements, and submenus

**Key Methods:**
- `buildMenuRenderArray($menuTree, $variant)` - Main entry point
- `buildMenuItem($item, $level)` - Build render array for a single menu item
- `buildSimpleMenuItem($item)` - Build render array for items without children
- `buildParentMenuItem($item, $level)` - Build render array for items with children
- `buildIconElement($item)` - Build render array for icon
- `buildBadgeElement($item)` - Build render array for badge
- `buildSubmenu($item, $level)` - Build render array for submenu container

#### 2. Update menuModule
**Location:** `/var/www/html/apps/zeusfw/core/modules/mainnavigation/mainnavigation.php`

- Add `MenuRenderArrayBuilder` initialization
- In `render()` method, build the render array instead of just passing the tree
- Pass the render array to the template

#### 3. Update Templates

**show_menu.zetem** - Convert to render array rendering:
- Remove the macro logic
- Add a simple render array output: `{{ renderArray($region.header) }}`

**menu.zetem** - Simplify to use render arrays:
- Remove the macro call
- Just pass the render array from the module to the template context

## Implementation Steps

### Step 1: Create MenuRenderArrayBuilder Class

Create `/var/www/html/apps/zeusfw/core/modules/mainnavigation/MenuRenderArrayBuilder.php` with:

- Class structure with constructor accepting variant
- `buildMenuRenderArray()` method that:
  - Creates wrapper render array for the `<ul>` with menu_attributes
  - Iterates through menu items and builds child render arrays
  - Returns complete render array structure

- `buildMenuItem()` method that:
  - Determines if item has children
  - Delegates to `buildSimpleMenuItem()` or `buildParentMenuItem()`
  - Returns render array for `<li>` element with proper attributes

- `buildSimpleMenuItem()` method that:
  - Creates render array for `<a>` element
  - Includes icon and badge if present
  - Uses `#type => 'html_tag'` with `#tag => 'a'`
  - Sets `#attributes` from `link_attributes`

- `buildParentMenuItem()` method that:
  - Creates render array for items with children
  - Includes toggle checkbox input
  - Includes label with icon/badge
  - Handles both clickable and non-clickable parent items
  - Recursively builds submenu render array

- Helper methods for icons, badges, and submenu containers

### Step 2: Integrate with menuModule

Update `/var/www/html/apps/zeusfw/core/modules/mainnavigation/mainnavigation.php`:

- Add `require_once 'MenuRenderArrayBuilder.php'` at top
- Initialize `MenuRenderArrayBuilder` in constructor
- Modify `render()` method to:
  - Build render array using `MenuRenderArrayBuilder`
  - Pass render array to template context as `$menu_render_array`
  - Keep backward compatibility by also passing `$menu` tree

### Step 3: Update Templates

Update `/var/www/html/apps/zeusfw/core/templates/menu/show_menu.zetem`:
- Keep the macro for backward compatibility (for now)
- Add new function/helper that uses RenderArrayManager to render the menu

Update `/var/www/html/apps/zeusfw/core/templates/menu/menu.zetem`:
- Add logic to check if `$menu_render_array` exists
- If exists, use `RenderArrayManager::renderPlain($menu_render_array)`
- Otherwise, fall back to old macro approach for backward compatibility

### Step 4: Handle $region.header Integration

The user mentioned `$region.header` which suggests the menu might be rendered as part of a page region. To support this:

- Ensure the render array can be assigned to `$region['header']` in route handlers or modules
- The render array should be compatible with the region rendering system
- May need to adjust how the menu module outputs its render array

### Step 5: Testing

Test the conversion with:
- Simple menu items (no children)
- Parent items with children
- Multiple nesting levels
- Icons and badges
- Active states and trails
- Both sidebar and topbar variants
- Different access permissions
- Menu items with and without URLs

## Critical Files to Modify

1. `/var/www/html/apps/zeusfw/core/modules/mainnavigation/MenuRenderArrayBuilder.php` (new)
2. `/var/www/html/apps/zeusfw/core/modules/mainnavigation/mainnavigation.php` (modify)
3. `/var/www/html/apps/zeusfw/core/templates/menu/menu.zetem` (modify)
4. `/var/www/html/apps/zeusfw/core/templates/menu/show_menu.zetem` (optional modification for backward compatibility)

## Verification Plan

1. **Visual Testing:**
   - Load a page with the main navigation menu
   - Verify menu items render correctly
   - Check icons, badges, and styling are intact
   - Test dropdown/submenu functionality
   - Verify active states and trails display correctly

2. **Variant Testing:**
   - Test sidebar variant
   - Test topbar variant (if enabled)
   - Ensure variant-specific classes are applied

3. **Functionality Testing:**
   - Click on menu links, verify navigation works
   - Test toggle checkboxes for submenus
   - Verify permissions are respected (items hidden if no access)

4. **Code Review:**
   - Check that render array structure follows specifications
   - Verify proper use of #type, #attributes, #weight
   - Ensure backward compatibility is maintained
   - Review for code quality and consistency

## Benefits of This Conversion

1. **Consistency:** Aligns menu rendering with the framework's render array system
2. **Maintainability:** Clearer separation between data structure (render array) and rendering logic
3. **Testability:** Render arrays can be tested independently of templates
4. **Flexibility:** Easier to alter menu structure via render array alteration hooks
5. **Performance:** Can leverage render array caching in the future
6. **Reusability:** Menu render arrays can be reused in different contexts (e.g., $region.header)
