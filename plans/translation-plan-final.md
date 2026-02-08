# ZPMS Enhanced Multilingual System - Implementation Plan

**Date:** February 6, 2026
**Last Updated:** February 8, 2026
**Target System:** ZPMS (Zeus Patient Management System) on Zeus Framework
**Status:** ✅ Phases 1-4 Completed (33% of comprehensive plan)
**Language Code:** Greek changed from 'gr' to 'el' (ISO 639-1 standard)
**URL Detection:** ✅ Implemented - Supports `/en/page` and `/el/page` URL patterns

---

## ⚠️ IMPORTANT: This Plan Has Been Superseded

This translation-only plan has been integrated into a comprehensive upgrade plan:

**📄 See: `/var/www/html/apps/zeusfw/plans/zpms-upgrade-plan.md`**

The new plan includes:
- **Phases 1-4:** Translation System Foundation (✅ Complete) - This document
- **Phases 5-8:** Design System Migration (🔲 Pending) - NEW
- **Phases 9-12:** Translation System Enhancements (🔲 Pending) - Former Phases 5-8

**For current implementation status and next steps, refer to the comprehensive plan.**

---

---

## Implementation Status

| Phase | Status | Description |
|-------|--------|-------------|
| Phase 1 | ✅ Complete | Enhanced Language Detection (including URL-based) |
| Phase 2 | ✅ Complete | File-Based Translation System (YAML) |
| Phase 3 | ✅ Complete | ZETEM Template Integration (Filters) |
| Phase 4 | ✅ Complete | Enhanced Dictionary System |
| Phase 5 | 🔲 Pending | Enhanced Language Switcher |
| Phase 6 | 🔲 Pending | Translation Management Interface |
| Phase 7 | 🔲 Pending | SEO & Metadata |
| Phase 8 | 🔲 Pending | Content Translation (Future) |

---

## Context

ZPMS currently has a **basic multilingual system** that supports Greek (el) and English (en):
- Language stored in `$_SESSION['CURRENT_LANGUAGE']`
- Configuration flag `multilingual: true` in `config/settings.info.yaml`
- Routes and menus use language-keyed arrays: `{el: "Αρχική", en: "Homepage"}`
- `getLangText($tok)` helper function for array-based translations in ZETEM templates
- Database-driven dictionary system via `dictionaryClassEx::translateToken()`
- Language selector module with AJAX switching

**Implementation Highlights:**

This plan **adapts the best ideas** from the generic multilingual plan and **enhances the existing ZPMS system** to create a robust, Zeus-compatible multilingual implementation:

1. ✅ **URL-based detection** - NOW IMPLEMENTED: Supports `/en/page` and `/el/page` patterns
2. ✅ **YAML-based translations** - File-based translation system for static strings
3. ✅ **ZETEM integration** - Template filters for easy translation in views
4. ✅ **Hybrid approach** - Combines file-based (YAML) and database translations
5. ✅ **Backward compatible** - All existing code continues to work unchanged
6. ✅ **Configuration-driven** - Follows Zeus Framework architecture

---

## Critical Files

### Core Framework Files
- `fw/core/kernel/Kernel.php` - Language management methods (lines 725-732)
- `fw/core/kernel/utils.php` - `getLangText()` helper (lines 317-330)
- `fw/core/dictionaryClassEx.php` - Database translation system (lines 35-85)
- `fw/core/templates/ZETEMTemplate.php` - Template engine
- `fw/core/modules/language_selector/` - Language switcher module

### Application Files
- `web/index.php` - Language initialization (lines 45-49)
- `config/settings.info.yaml` - Multilingual config (lines 47-52)
- `web/templates/` - ZETEM templates using `getLangText()`

### New Files Created (Phases 1-3)
- ✅ `fw/core/lib/MultilingualManager.php` - Enhanced translation manager
- ✅ `fw/core/lib/LanguageDetector.php` - Advanced language detection with URL support
- ✅ `fw/core/filters/TranslationFilters.php` - ZETEM translation filters
- ✅ `config/translations/en.yaml` - English translations
- ✅ `config/translations/el.yaml` - Greek translations
- ✅ `docs/URL_LANGUAGE_DETECTION.md` - URL-based detection documentation
- ✅ `web/templates/examples/translation-filters-examples.zetem` - Usage examples
- ✅ `web/test/test_translation_filters.php` - Filter tests

### Pending Files (Future Phases)
- 🔲 `web/modules/translation_admin/` - Translation management interface (Phase 6)

---

## Implementation Plan

### Phase 1: Enhanced Language Detection ✅ COMPLETED

**Objective:** Add intelligent language detection with configurable priority.

**Status:** ✅ Fully implemented and tested

**Tasks:**

1. ✅ **Create LanguageDetector class** (`fw/core/lib/LanguageDetector.php`)
   - Implement detection methods (ALL in PHP, NO .htaccess):
     - ✅ **URL path prefix** (`/en/page` or `/el/page`) - **NEW** Highest priority
     - ✅ Session (`$_SESSION['CURRENT_LANGUAGE']`) - Primary method
     - ✅ Cookie (`user_lang` cookie) - Persistent preference
     - ✅ User profile (database-stored preference) - Authenticated users
     - ✅ Query parameter (`?lang=en`) - Manual override for testing/bookmarks
     - ✅ Browser (`HTTP_ACCEPT_LANGUAGE` header) - First-time visitors
     - ✅ Default fallback - Configured default language
   - ✅ Configurable detection priority in `settings.info.yaml`
   - ✅ URL helper methods: `extractLanguageFromPath()`, `addLanguageToPath()`, `removeLanguageFromPath()`
   - 🔲 Geographic detection (optional, via IP lookup) - Not implemented

2. ✅ **Update Kernel language methods** (`fw/core/kernel/Kernel.php`)
   - ✅ Enhance `setCurrentLanguage($lang)`:
     - ✅ Validate against supported languages list
     - ✅ Set cookie for persistence (1 year)
     - ✅ Trigger language change hook
   - ✅ Enhance `getCurrentLanguage()`:
     - ✅ Use LanguageDetector if no session value
   - ✅ Add `getSupportedLanguages()` - return languages from config
   - ✅ Add `getDefaultLanguage()` - return default from config
   - ✅ Add `getLanguageName($code)` - return native language name
   - ✅ Add `getLanguageDetector()` - return LanguageDetector instance

3. ✅ **Add language configuration** (`config/settings.info.yaml`)
   ```yaml
   multilingual: true

   languages:
     en:
       name: English
       native_name: English
       direction: ltr
       locale: en_US
       enabled: true
     el:
       name: Greek
       native_name: Ελληνικά
       direction: ltr
       locale: el_GR
       enabled: true

   language_detection:
     default: el
     priority:
       - session  # Check session (primary)
       - cookie   # Check cookie (persistent)
       - user     # Check user profile (authenticated)
       - query    # Check ?lang= parameter (manual override)
       - browser  # Check browser header (first-time)
       - default  # Fallback (configured default)
   ```

### Phase 2: File-Based Translation System ✅ COMPLETED

**Objective:** Add YAML-based translation files as an alternative to the database dictionary.

**Status:** ✅ Fully implemented and tested

**Tasks:**

1. ✅ **Create MultilingualManager class** (`fw/core/lib/MultilingualManager.php`)
   - ✅ Load translation files from `config/translations/{lang}.yaml`
   - ✅ Cache compiled translations in memory
   - ✅ Fallback chain: specific file → default language → database dictionary → key itself
   - ✅ Support nested keys with dot notation: `t('dashboard.welcome.message')`
   - ✅ Parameter replacement: `t('msg.hello', ['name' => 'John'])`
   - ✅ Pluralization support: `t_plural('item', $count)`
   - ✅ Context support: `t_context('bank', 'financial')` vs `t_context('bank', 'river')`

2. ✅ **Create translation YAML files** (`config/translations/`)
   - ✅ `en.yaml` - English translations (comprehensive)
   - ✅ `el.yaml` - Greek translations (comprehensive)
   - Structure:
     ```yaml
     nav:
       home: Home
       patients: Patients
       appointments: Appointments

     dashboard:
       welcome: "Welcome back, {name}!"
       total_patients: Total Patients
       recent_activity: Recent Activity

     messages:
       save_success: Changes saved successfully
       delete_confirm: Are you sure you want to delete {item}?

     forms:
       required_field: This field is required
       invalid_email: Please enter a valid email
     ```

3. ✅ **Update utils.php** (`fw/core/kernel/utils.php`)
   - ✅ Keep existing `getLangText($tok)` for backward compatibility
   - ✅ Enhanced existing `t()` function to integrate with MultilingualManager
   - ✅ Added `t_plural()` function for pluralization
   - ✅ Added `t_context()` function for context-aware translation

4. ✅ **Integrate with Kernel** (`fw/core/kernel/Kernel.php`)
   - ✅ Initialize MultilingualManager in constructor
   - ✅ Add `getMultilingualManager()` method
   - ✅ Automatic translation loading on language detection

### Phase 3: ZETEM Template Integration ✅ COMPLETED

**Objective:** Add translation filters and syntax to ZETEM templates.

**Status:** ✅ Fully implemented and tested

**Tasks:**

1. ✅ **Create TranslationFilters** (`fw/core/filters/TranslationFilters.php`)
   - ✅ Register filters with Renderer:
     - ✅ `t` - Translate key: `{{ 'nav.home' | t }}`
     - `translate` - Verbose alias: `{{ 'messages.welcome' | translate }}`
     - ✅ `translate` - Verbose alias: `{{ 'messages.welcome' | translate }}`
     - ✅ `lang_text` - Array-based (existing): `{{ $item['title'] | lang_text }}`
     - ✅ `t_params` - With parameters: `{{ 'msg.hello' | t_params({'name': $user}) }}`
     - ✅ `t_plural` - Pluralization: `{{ 'item' | t_plural($count) }}`
     - ✅ `t_context` - Context-aware: `{{ 'bank' | t_context('financial') }}`

2. ✅ **Register filters on bootstrap** (`web/index.php`)
   - ✅ Filters automatically registered in bootstrap
   - ✅ Available globally in all ZETEM templates

3. ✅ **Create template examples** (documentation)
   - ✅ Created `web/templates/examples/translation-filters-examples.zetem`
   - ✅ Created `web/test/test_translation_filters.php`
   - ✅ Examples show old vs new syntax, pluralization, parameters
   ```zetem
   {# Old syntax - still works #}
   {{ getLangText($item['title']) }}

   {# New syntax - recommended #}
   {{ 'nav.home' | t }}
   {{ 'dashboard.welcome' | t_params({'name': $userName}) }}
   {{ $item['title'] | lang_text }}

   {# Pluralization #}
   {{ 'patient' | t_plural($patientCount) }}
   ```

### Phase 4: Enhanced Dictionary System ✅ COMPLETED

**Objective:** Improve the database-driven translation system.

**Status:** ✅ Fully implemented and tested

**Tasks:**

1. ✅ **Enhance dictionaryClassEx** (`fw/core/dictionaryClassEx.php`)
   - ✅ Add `getAllTokens()` - Retrieve all dictionary entries
   - ✅ Add `updateTranslation($token, $lang, $translation)` - Update specific translation
   - ✅ Add `deleteToken($token)` - Remove dictionary entry
   - ✅ Add `getUntranslated($lang)` - Find missing translations
   - ✅ Add `exportToYAML($lang)` - Export dictionary to YAML file
   - ✅ Add `importFromYAML($lang, $file)` - Import YAML file to dictionary
   - ✅ Add statistics methods:
     - ✅ `getTranslationStats()` - Count translated vs untranslated per language
     - ✅ `getRecentTokens($limit)` - Recently added tokens

2. ✅ **Add dictionary configuration** (`config/settings.info.yaml`)
   ```yaml
   dictionary:
     auto_register: true        # Auto-add new tokens
     fallback_to_file: true     # Check YAML files if not in DB
     prefer_database: false     # Prefer file-based over DB
   ```

3. ✅ **Integration strategy**
   - File-based for static UI strings (faster, version-controlled)
   - Database for dynamic content (user-generated, admin-editable)
   - MultilingualManager checks both sources based on config
   - Translation resolution order configurable via `prefer_database` flag

### Phase 5: Enhanced Language Switcher

**Objective:** Improve language switching with query parameter support and page state preservation.

**Tasks:**

1. **Add query parameter detection** (`fw/core/lib/LanguageDetector.php`)
   - Detect `?lang=en` parameter in URL
   - Validate language code against supported languages
   - Set session and cookie when query parameter is present
   - Example usage: `/patients?lang=en` switches to English

2. **Update language selector module** (`fw/core/modules/language_selector/`)
   - Current AJAX-based switching (session only)
   - Add query parameter mode for bookmarkable URLs
   - Preserve current page path on language switch
   - Configuration to choose mode: AJAX (immediate) or Query Parameter (reload with ?lang=)

3. **Add helper functions** (`fw/core/kernel/utils.php`)
   ```php
   // Get current page URL with language query parameter
   function get_current_url_with_lang($lang) {
       global $kernel;
       $q = $_GET['q'] ?? '';
       $url = '/' . ltrim($q, '/');

       // Add or update lang parameter
       $separator = strpos($url, '?') !== false ? '&' : '?';
       return $url . $separator . 'lang=' . urlencode($lang);
   }

   // Check if language is being set via query parameter
   function is_lang_query_present() {
       return isset($_GET['lang']);
   }
   ```

4. **Add configuration** (`config/settings.info.yaml`)
   ```yaml
   language_switcher:
     mode: ajax              # ajax or query_param
     preserve_page: true     # Maintain current page on switch
     show_in_header: true    # Display in header region
   ```

### Phase 6: Translation Management Interface

**Objective:** Create admin module for managing translations.

**Tasks:**

1. **Create translation_admin module** (`web/modules/translation_admin/`)
   - `translation_admin.info.yaml` - Module metadata
   - `translation_admin.php` - Module class with CRUD operations
   - `translation_admin.zetem` - Admin UI template
   - `translation_admin.css` - Styling

2. **Features to implement**
   - List all dictionary entries with search/filter
   - Inline editing of translations
   - Bulk import/export (YAML, CSV)
   - Translation statistics dashboard
   - Missing translation reports
   - Auto-translate integration (optional, via API)
   - Translation history/versioning

3. **Add routes** (`config/settings.info.yaml`)
   ```yaml
   routes:
     translations_list:
       title: Translations
       url: /admin/translations
       handler: translation_admin_list
       access: administer_translations

     translation_edit:
       title: Edit Translation
       url: /admin/translations/edit/{token}
       handler: translation_admin_edit
       access: administer_translations
   ```

4. **Add permissions** (`config/settings.info.yaml`)
   ```yaml
   permissions:
     administer_translations:
       title: Administer Translations
       roles:
         - administrator
   ```

### Phase 7: SEO & Metadata

**Objective:** Add proper SEO tags for multilingual content.

**Tasks:**

1. **Create SEO helper functions** (`fw/core/lib/SEOHelper.php`)
   - `generateHreflangTags()` - Alternate language links
   - `generateCanonicalTag()` - Canonical URL for current page
   - `getLanguageMetadata()` - Language-specific meta tags

2. **Update page rendering** (`fw/core/kernel/Kernel.php`)
   - Inject hreflang tags in page head
   - Add canonical URL
   - Add Open Graph locale tags

3. **Template variables** (available in ZETEM)
   ```zetem
   <html lang="{{ $current_language }}">
   <head>
       {# Canonical #}
       <link rel="canonical" href="{{ $canonical_url }}">

       {# Alternate languages #}
       {% for $lang in $alternate_languages %}
       <link rel="alternate" hreflang="{{ $lang['code'] }}" href="{{ $lang['url'] }}">
       {% endfor %}

       {# Open Graph #}
       <meta property="og:locale" content="{{ $og_locale }}">
   </head>
   ```

### Phase 8: Content Translation (Future Enhancement)

**Objective:** Support language-specific content variants (not just UI translations).

**Tasks:**

1. **Add translation tables** (SQL schema)
   ```sql
   CREATE TABLE content_translations (
       id INT PRIMARY KEY AUTO_INCREMENT,
       entity_type VARCHAR(50),
       entity_id INT,
       language VARCHAR(10),
       field_name VARCHAR(100),
       field_value TEXT,
       created_at TIMESTAMP,
       updated_at TIMESTAMP,
       UNIQUE KEY unique_translation (entity_type, entity_id, language, field_name)
   );
   ```

2. **Extend entity classes** (`web/ClassesEx.php`)
   - Add `getTranslation($lang)` method to entity classes
   - Automatically load translated fields for current language
   - Fallback to default language if translation missing

3. **Admin interface enhancements**
   - Add translation tabs to entity edit forms
   - Show translation status indicators
   - Quick translate buttons

---

## Testing & Verification

### Manual Testing Checklist

1. **Language Detection**
   - [ ] Session-based switching works
   - [ ] Cookie persistence works (survives browser restart)
   - [ ] Browser language detection works
   - [ ] Default language fallback works
   - [ ] Invalid language codes are rejected

2. **File-Based Translations**
   - [ ] YAML files load correctly
   - [ ] Nested key access works: `t('dashboard.welcome.message')`
   - [ ] Parameter replacement works: `t('msg.hello', ['name' => 'John'])`
   - [ ] Fallback to default language works
   - [ ] Missing keys return the key itself

3. **Database Dictionary**
   - [ ] Auto-registration of new tokens works
   - [ ] Translations save and load correctly
   - [ ] Multiple languages work simultaneously
   - [ ] Export to YAML works
   - [ ] Import from YAML works

4. **ZETEM Templates**
   - [ ] `{{ 'nav.home' | t }}` filter works
   - [ ] `{{ $title | lang_text }}` works (backward compatibility)
   - [ ] Parameter filters work
   - [ ] Translations display correctly in all templates

5. **Language Switcher**
   - [ ] Switcher displays all enabled languages
   - [ ] Current language is highlighted
   - [ ] AJAX mode: Switching updates session immediately
   - [ ] Query param mode: Switching reloads with ?lang= parameter
   - [ ] Current page is maintained on switch

6. **Admin Interface**
   - [ ] Translation list loads
   - [ ] Search and filters work
   - [ ] Inline editing saves correctly
   - [ ] Import/export functions work
   - [ ] Statistics display correctly

7. **SEO**
   - [ ] Hreflang tags appear in page source
   - [ ] Canonical URLs are correct
   - [ ] Open Graph locale tags present
   - [ ] HTML lang attribute matches current language

### Automated Testing

Create test files in `web/test/`:
- `test_language_detection.php` - Test all detection methods
- `test_translations.php` - Test file and database translations
- `test_zetem_filters.php` - Test ZETEM translation filters

---

## Key Improvements Over Generic Plan

1. **Zeus Framework Integration**
   - Works with Kernel configuration system
   - Uses ZETEM template engine (not plain PHP)
   - Follows Zeus module pattern
   - Configuration-driven design

2. **Hybrid Translation System**
   - File-based (YAML) for static UI strings
   - Database for dynamic content
   - Configurable preference order
   - Best of both worlds

3. **Backward Compatibility**
   - Keeps existing `getLangText()` function
   - Existing YAML config structures still work
   - No breaking changes to current implementation
   - Gradual migration path

4. **Enhanced Features**
   - Advanced language detection with priority
   - Translation management admin interface
   - SEO optimization out of the box
   - Pluralization and context support
   - Import/export functionality

5. **Pure PHP Language Detection**
   - NO .htaccess involvement - all detection in PHP
   - Session-based primary method (fast, server-side)
   - Cookie for persistence across sessions
   - Query parameter (?lang=en) for testing and bookmarks
   - Browser detection for first-time visitors

---

## Migration Path

For existing ZPMS installations:

1. **Phase 1** (Non-breaking)
   - Install new files (MultilingualManager, LanguageDetector, filters)
   - Add translation YAML files
   - Register new filters
   - No changes to existing code

2. **Phase 2** (Gradual adoption)
   - Start using `{{ 'key' | t }}` in new templates
   - Keep `getLangText()` in existing templates
   - Both work simultaneously

3. **Phase 3** (Optional migration)
   - Export database dictionary to YAML files
   - Review and organize translations
   - Switch to file-based for static strings
   - Keep database for dynamic content

4. **Phase 4** (Optional enhancements)
   - Enable query parameter mode if desired
   - Configure language switcher behavior
   - Test with ?lang= parameter in URLs
   - Verify session and cookie persistence

---

## Configuration Reference

### Minimal Configuration (Existing)
```yaml
multilingual: true
languages:
  - en
  - el
```

### Enhanced Configuration (Recommended)
```yaml
multilingual: true

languages:
  en:
    name: English
    native_name: English
    direction: ltr
    locale: en_US
    enabled: true
  el:
    name: Greek
    native_name: Ελληνικά
    direction: ltr
    locale: el_GR
    enabled: true

language_detection:
  default: el
  priority:
    - session
    - cookie
    - browser
    - default

dictionary:
  auto_register: true
  fallback_to_file: true
  prefer_database: false

language_switcher:
  mode: ajax              # ajax or query_param
  preserve_page: true     # Maintain current page on switch
  show_in_header: true    # Display in header region
```

---

## Configuration Format Compatibility

### Important: Language Configuration Array Structure Change

**Breaking Change:** The configuration format for `languages` has changed from Phase 1 implementation:

**Old Format (Simple Array):**
```yaml
languages:
  - en
  - el
```
- Returns: `['en', 'el']` (indexed array)
- `array_keys()` returns: `[0, 1]` (numeric indices)

**New Format (Associative Array):**
```yaml
languages:
  en:
    name: English
    native_name: English
    direction: ltr
    locale: en_US
    enabled: true
  el:
    name: Greek
    native_name: Ελληνικά
    direction: ltr
    locale: el_GR
    enabled: true
```
- Returns: `['en' => [...], 'el' => [...]]` (associative array)
- `array_keys()` returns: `['en', 'el']` (language codes)

### Compatibility Strategy

All code accessing the `languages` configuration must handle both formats:

1. **Use `array_keys()` to extract language codes:**
   ```php
   $languages = $kernel->getConfig('languages');
   $codes = array_keys($languages);  // Works for both formats

   // Check if old format (numeric keys)
   if (is_numeric($codes[0])) {
       // Old format: use values directly
       $supportedLanguages = $languages;
   } else {
       // New format: use keys as language codes
       $supportedLanguages = $codes;
   }
   ```

2. **Filter by enabled status in new format:**
   ```php
   $supported = [];
   foreach ($languages as $code => $details) {
       if (is_array($details)) {
           // New format - check enabled flag
           if ($details['enabled'] ?? true) {
               $supported[] = $code;
           }
       } else {
           // Old format - include all
           $supported[] = $code;
       }
   }
   ```

3. **Updated files implementing this compatibility:**
   - `fw/core/lib/LanguageDetector.php` - `getSupportedLanguagesFromConfig()` method
   - `fw/core/kernel/Kernel.php` - `getSupportedLanguages()` method (line 812)

### Code Example: Kernel.php (Line 812)

```php
function getSupportedLanguages() {
    if ($this->languageDetector) {
        return $this->languageDetector->getSupportedLanguages();
    }

    // Fallback: get from config
    $languages = $this->getConfig('languages');
    if (is_array($languages)) {
        // Handle both old and new format using array_keys()
        return array_keys($languages);
    }

    return ['el', 'en'];
}
```

**Important:** The `array_keys()` approach is the simplest and most reliable way to maintain compatibility between old and new configuration formats.

---

## ZETEM Renderer Enhancement: Anonymous Function Support

### Problem Identified

The ZETEM template renderer was incorrectly translating anonymous function parameters to `$variable_context`, treating them as template variables instead of local function parameters.

**Example of the Issue:**
```php
// In template: fw/core/maker/templates/functions/insert.zetem
array_map(function($fn) {return ':'.$fn;}, $fieldsList)

// Was incorrectly compiled to:
array_map(function($variable_context['fn']) {return ':'.$variable_context['fn'];}, $variable_context['fieldsList'])
//                     ^^^^^^^^^^^^ WRONG!
```

### Solution Implemented ✅

Modified `fw/core/templates/ZETEMTemplate.php` to detect and protect anonymous function parameters:

**1. Added Anonymous Function Parameter Tracking:**
```php
static $anonFuncParams = [];  // Track anonymous function parameters
```

**2. Created `extractAnonFuncParams()` Method:**
- Detects both `function($param)` and `fn($param) =>` syntax
- Extracts all parameter names from function signatures
- Returns array of parameters to protect from variable conversion

**3. Enhanced `processExpression()` Method:**
```php
public static function processExpression($expr) {
    // Extract anonymous function parameters before processing
    $anonParams = self::extractAnonFuncParams($expr);

    // Temporarily mark as scoped variables
    foreach ($anonParams as $param) {
        self::$anonFuncParams[$param] = true;
    }

    // Process expression (scoped params won't be converted)
    $expr = self::convertDotNotation($expr);
    $expr = self::convertArrayNotation($expr);

    // Clean up
    foreach ($anonParams as $param) {
        unset(self::$anonFuncParams[$param]);
    }

    return $expr;
}
```

**4. Updated `isScopedVariable()` Method:**
```php
static function isScopedVariable($varName) {
    return isset(self::$macroArgs[$varName]) ||
           isset(self::$loopVars[$varName]) ||
           isset(self::$anonFuncParams[$varName]) ||  // ← NEW
           strpos($varName, '_macro_') === 0 ||
           strpos($varName, '_loop_') === 0;
}
```

### Test Results ✅

```php
// Input:
implode(',', array_map(function($fn) {return ':'.$fn;}, $fieldsList))

// Correctly compiled to:
implode(',', array_map(function($fn) {return ':'.$fn;}, $variable_context['fieldsList']))
//                               ✓ $fn stays as-is (local parameter)
//                                                  ✓ $fieldsList correctly converted
```

### Benefits

- ✅ Anonymous functions now work correctly in ZETEM templates
- ✅ Consistent with macro parameter handling (same scoping logic)
- ✅ Supports both traditional and arrow function syntax
- ✅ Multiple parameters supported: `function($a, $b, $c)`
- ✅ Template cache cleared automatically on changes

**Files Modified:**
- `fw/core/templates/ZETEMTemplate.php` (Lines 56, 448, 522-568, 285)

---

## Development Priority

### High Priority (Core Enhancements)
1. MultilingualManager with YAML translation files
2. Translation filters for ZETEM templates
3. Enhanced Kernel language methods
4. LanguageDetector class

### Medium Priority (Admin & Tools)
5. Translation admin interface
6. Dictionary enhancements (import/export)
7. SEO helper functions

### Low Priority (Optional Features)
8. Geographic language detection (IP-based)
9. Content translation tables
10. Auto-translate API integration

---

## Estimated Implementation Time

- **Phase 1**: 4-6 hours (Language detection)
- **Phase 2**: 6-8 hours (File-based translations)
- **Phase 3**: 2-3 hours (ZETEM integration)
- **Phase 4**: 4-5 hours (Dictionary enhancements)
- **Phase 5**: 2-3 hours (Enhanced language switcher)
- **Phase 6**: 8-10 hours (Admin interface)
- **Phase 7**: 2-3 hours (SEO)
- **Phase 8**: 10-12 hours (Content translation - future)

**Total Core Implementation**: ~22-28 hours
**With All Optional Features**: ~38-46 hours

---

## Important Design Decision: Pure PHP Language Detection ✅ IMPLEMENTED

**NO .htaccess involvement**: All language detection and handling happens in PHP code. This provides:

### Advantages
1. **Simplicity**: No complex URL rewriting rules to debug
2. **Portability**: Works on any web server (Apache, Nginx, IIS)
3. **Flexibility**: Easy to modify detection logic without touching server config
4. **Debugging**: All detection code in one place (LanguageDetector class)
5. **Testing**: Can test language detection without server configuration

### Implementation Approach (Current)
- **✅ URL Path Prefix**: `/en/page` or `/el/page` (NEW - highest priority)
- **✅ Session**: Session-based (fast, persistent during session)
- **✅ Cookie**: Cookie-based (survives browser restart, 1-year expiry)
- **✅ Query Parameter**: `?lang=en` for testing and direct links
- **✅ Browser Detection**: Accept-Language header for first-time visitors
- **✅ User Preference**: Database-stored preference for authenticated users
- **✅ Default Fallback**: Configured default language (el)

### URL Examples (Current Implementation)
```
✅ /en/patients          → English, URL prefix detected
✅ /el/patients          → Greek, URL prefix detected
✅ /patients             → Language from session/cookie/browser
✅ /patients?lang=en     → English, query parameter detected
✅ /en/patient/123/edit  → English, prefix works with parameters
```

### URL Processing Flow
1. **Request arrives**: `/en/admin`
2. **Language extracted**: `en` detected from first path segment
3. **Language set**: Stored in session + cookie
4. **URL cleaned**: Prefix removed → `/admin`
5. **Router matches**: Normal route matching on `/admin`
6. **Content rendered**: In English

### Backward Compatibility
- ✅ All existing URLs without prefixes continue to work
- ✅ Query parameter method (`?lang=en`) still functional
- ✅ Session/cookie detection still works
- ✅ No .htaccess modifications required
- ✅ Zero breaking changes

This pure PHP approach provides maximum flexibility - supporting both clean language-specific URLs (`/en/page`) and language-agnostic URLs (`/page`) simultaneously, all without server configuration changes.
