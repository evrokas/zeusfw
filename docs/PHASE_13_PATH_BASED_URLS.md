# Phase 13: Path-Based Language URLs

**Status:** ✅ Complete
**Date:** February 2026
**Dependencies:** Phase 10 (Language Switcher), Phase 12 (SEO & Metadata)

## Overview

This phase implements a transition from query parameter-based language URLs (`?lang=en`) to path-based language URLs (`/en/`, `/el/`). This provides better SEO, cleaner URLs, and consistency with modern web frameworks.

## Problem Statement

The system had a **hybrid approach** for language handling:
- **Incoming requests**: Already supported path-based detection (`/en/page`, `/el/page`)
- **Outgoing links**: Generated query parameter URLs (`/page?lang=en`)

This created inconsistency where users could navigate to `/en/patients` but all generated links used `?lang=` parameters.

## Implementation

### 1. Updated URL Generation Helpers (`fw/core/kernel/utils.php`)

#### `get_current_url_with_lang(string $lang): string`

**Before:**
```php
function get_current_url_with_lang(string $lang): string {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    parse_str($queryString, $queryParams);
    $queryParams['lang'] = $lang;
    $newQueryString = http_build_query($queryParams);

    return $path . ($newQueryString ? '?' . $newQueryString : '');
}
```

**After:**
```php
function get_current_url_with_lang(string $lang): string {
    global $kernel;
    $languageDetector = $kernel->getLanguageDetector();

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    // Add language prefix to path
    $pathWithLang = $languageDetector->addLanguageToPath($path, $lang);

    return $pathWithLang . ($queryString ? '?' . $queryString : '');
}
```

#### `get_current_url_without_lang(): string`

**Before:**
```php
function get_current_url_without_lang(): string {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    parse_str($queryString, $queryParams);
    unset($queryParams['lang']);
    $newQueryString = http_build_query($queryParams);

    return $path . ($newQueryString ? '?' . $newQueryString : '');
}
```

**After:**
```php
function get_current_url_without_lang(): string {
    global $kernel;
    $languageDetector = $kernel->getLanguageDetector();

    $originalUri = $_SERVER['ORIGINAL_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($originalUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    // Remove language prefix from path
    $pathWithoutLang = $languageDetector->removeLanguageFromPath($path);

    // Remove lang query parameter (backward compatibility)
    parse_str($queryString, $queryParams);
    unset($queryParams['lang']);
    $cleanQuery = http_build_query($queryParams);

    return $pathWithoutLang . ($cleanQuery ? '?' . $cleanQuery : '');
}
```

### 2. Store Original REQUEST_URI (`web/index.php`)

Before rewriting the `REQUEST_URI` to strip the language prefix, we now store the original:

```php
// Store original REQUEST_URI before rewriting
$_SERVER['ORIGINAL_REQUEST_URI'] = $_SERVER['REQUEST_URI'];

// Extract language from URL path prefix
$languageDetector = $kernel->getLanguageDetector();
if ($languageDetector) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $query = parse_url($requestUri, PHP_URL_QUERY);

    $extracted = $languageDetector->extractLanguageFromPath($path);

    if ($extracted) {
        $kernel->setCurrentLanguage($extracted['lang']);

        // Rewrite REQUEST_URI without language prefix
        $_SERVER['REQUEST_URI'] = $extracted['path'];
        if ($query) {
            $_SERVER['REQUEST_URI'] .= '?' . $query;
        }
    }
}
```

### 3. Updated SEOHelper (`fw/core/lib/SEOHelper.php`)

#### `getCurrentPath()` method

**Before:**
```php
private function getCurrentPath() {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    parse_str($queryString, $queryParams);
    unset($queryParams['lang']);
    $cleanQueryString = http_build_query($queryParams);

    return $path . ($cleanQueryString ? '?' . $cleanQueryString : '');
}
```

**After:**
```php
private function getCurrentPath() {
    // REQUEST_URI has already been rewritten without language prefix
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    // Remove lang query parameter if present (backward compatibility)
    parse_str($queryString, $queryParams);
    unset($queryParams['lang']);

    $cleanQueryString = http_build_query($queryParams);
    return $path . ($cleanQueryString ? '?' . $cleanQueryString : '');
}
```

#### `getUrlForLanguage()` method

**Before:**
```php
public function getUrlForLanguage($lang, $path = null) {
    if ($path === null) {
        $path = $this->getCurrentPath();
    }

    $parts = parse_url($path);
    $pathOnly = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    parse_str($queryString, $queryParams);
    $queryParams['lang'] = $lang;
    $newQueryString = http_build_query($queryParams);
    $fullPath = $pathOnly . '?' . $newQueryString;

    return $this->baseUrl . $fullPath;
}
```

**After:**
```php
public function getUrlForLanguage($lang, $path = null) {
    if ($path === null) {
        $path = $this->getCurrentPath();
    }

    $parts = parse_url($path);
    $pathOnly = $parts['path'] ?? '/';
    $queryString = $parts['query'] ?? '';

    // Get language detector from kernel
    $languageDetector = $this->kernel->getLanguageDetector();

    // Add language prefix to path
    $pathWithLang = $languageDetector->addLanguageToPath($pathOnly, $lang);

    // Combine with query string
    $fullPath = $pathWithLang . ($queryString ? '?' . $queryString : '');

    return $this->baseUrl . $fullPath;
}
```

### 4. Updated Language Selector Module (`fw/core/modules/language_selector/language_selector.php`)

**Before:**
```php
// Generate URL for query_param mode
if ($mode === 'query_param' && $preservePage) {
    $languageUrls[$lang] = get_current_url_with_lang($lang);
} else {
    $languageUrls[$lang] = '?lang=' . $lang;
}
```

**After:**
```php
// Generate language-specific URL
if ($preservePage) {
    // Use helper function which now generates path-based URLs by default
    $languageUrls[$lang] = get_current_url_with_lang($lang);
} else {
    // Just link to language home page
    $languageUrls[$lang] = '/' . $lang . '/';
}
```

Also changed default mode from `'ajax'` to `'path_prefix'`:
```php
$mode = $switcherConfig['mode'] ?? 'path_prefix';
```

### 5. Updated Configuration (`config/settings.info.yaml`)

```yaml
# Language switcher configuration (Phase 10)
language_switcher:
  mode: path_prefix          # Mode: 'path_prefix' (uses /en/, /el/ URLs)
  preserve_page: true        # Preserve current page path when switching languages
  show_in_header: true       # Show language selector in header region
```

## URL Examples

### Before (Query Parameters)

| User Action | Generated URL |
|-------------|---------------|
| Visit patients page in English | `/patients?lang=en` |
| Visit admin page in Greek | `/admin?lang=el` |
| Search with language | `/patients?search=test&lang=en` |
| Canonical URL | `http://localhost/patients?lang=en` |
| Hreflang link | `<link rel="alternate" hreflang="en" href="/patients?lang=en">` |

### After (Path Prefixes)

| User Action | Generated URL |
|-------------|---------------|
| Visit patients page in English | `/en/patients` |
| Visit admin page in Greek | `/el/admin` |
| Search with language | `/en/patients?search=test` |
| Canonical URL | `http://localhost/en/patients` |
| Hreflang link | `<link rel="alternate" hreflang="en" href="/en/patients">` |

## Request Flow

### Incoming Request: `/en/patients`

1. **Browser sends:** `GET /en/patients HTTP/1.1`
2. **index.php receives:** `$_SERVER['REQUEST_URI'] = '/en/patients'`
3. **Store original:** `$_SERVER['ORIGINAL_REQUEST_URI'] = '/en/patients'`
4. **LanguageDetector extracts:** `['lang' => 'en', 'path' => '/patients']`
5. **Kernel sets language:** `$kernel->setCurrentLanguage('en')`
6. **REQUEST_URI rewritten:** `$_SERVER['REQUEST_URI'] = '/patients'`
7. **Router matches:** Route handler for `/patients`
8. **Handler runs:** Displays page in English
9. **URL generation:** All links include `/en/` prefix

### Outgoing Links

```php
// Language switcher flag for Greek
$url = get_current_url_with_lang('el');
// Returns: /el/patients

// SEO hreflang tag
$url = $seoHelper->getUrlForLanguage('en');
// Returns: http://localhost/en/patients

// Canonical URL
$canonical = $seoHelper->generateCanonicalTag();
// Returns: <link rel="canonical" href="http://localhost/en/patients">
```

## Backward Compatibility

The system maintains backward compatibility with query parameter URLs:

1. **Query parameter detection still works:**
   - `/patients?lang=en` still sets language to English
   - Detection priority in `settings.info.yaml` includes `query` method

2. **`get_current_url_without_lang()` strips both:**
   - Path-based prefix: `/en/patients` → `/patients`
   - Query parameter: `/patients?lang=en` → `/patients`

3. **Old bookmarks still function:**
   - Users with bookmarked `?lang=` URLs can still access pages
   - The language will be detected and persisted to session/cookie

## Testing

A comprehensive test page is available at:
```
/test/test_path_urls.php
```

### Test Coverage

1. **URL Helper Functions:**
   - Simple paths: `/patients` → `/en/patients`
   - Paths with query params: `/patients?search=test` → `/en/patients?search=test`
   - Root path: `/` → `/en/`
   - Removing language prefix

2. **SEOHelper URL Generation:**
   - `getUrlForLanguage()` with different languages
   - Hreflang tag generation
   - Canonical URL generation

3. **LanguageDetector Path Manipulation:**
   - `extractLanguageFromPath()`
   - `addLanguageToPath()`
   - `removeLanguageFromPath()`

## Benefits

### 1. SEO Improvements
- Cleaner URLs preferred by search engines
- Better indexing and language targeting
- Consistent with major CMS platforms

### 2. User Experience
- More readable and shareable URLs
- Professional appearance
- Consistent format throughout application

### 3. Analytics
- Easier to segment traffic by language
- URL structure clearly shows language
- Better tracking in Google Analytics

### 4. Framework Standards
- Matches behavior of Django, Rails, Laravel
- Industry best practice
- Easier for developers to understand

## Configuration Options

### Language Switcher Mode

```yaml
language_switcher:
  mode: path_prefix          # New default
  preserve_page: true
  show_in_header: true
```

**Available modes:**
- `path_prefix` - Uses `/en/`, `/el/` URLs (recommended)
- `ajax` - AJAX-based switching without page reload
- `query_param` - Legacy mode using `?lang=` (deprecated)

### Language Detection Priority

```yaml
language_detection:
  default: el
  priority:
    - url       # First check URL path prefix
    - session   # Then check session
    - cookie    # Then check cookie
    - user      # Then check user profile
    - query     # Then check query parameter (backward compatibility)
    - browser   # Then check browser Accept-Language
    - default   # Finally use default language
```

## Related Documentation

- [Phase 10: Enhanced Language Switcher](PHASE_10_LANGUAGE_SWITCHER.md)
- [Phase 12: SEO & Metadata](PHASE_12_SEO_METADATA.md)
- [Multilingual System Architecture](MULTILINGUAL_SYSTEM.md)
- [Language Detection Priority](LANGUAGE_DETECTION.md)

## Files Modified

### Core Framework (`/var/www/html/apps/zeusfw/`)

```
core/kernel/utils.php
├── get_current_url_with_lang()      - Updated to add path prefix
└── get_current_url_without_lang()   - Updated to strip path prefix

core/lib/SEOHelper.php
├── getCurrentPath()                 - Updated path extraction
└── getUrlForLanguage()              - Updated to use path prefixes

core/modules/language_selector/
└── language_selector.php            - Updated URL generation logic
```

### Application (`/var/www/html/apps/zpms/`)

```
web/index.php                        - Store ORIGINAL_REQUEST_URI
config/settings.info.yaml            - Changed mode to 'path_prefix'
web/test/test_path_urls.php          - New test page
```

## Migration Notes

### For Developers

No code changes required in route handlers or templates. All URL generation is handled by:
- `get_current_url_with_lang()` helper
- `SEOHelper` methods
- Language selector module

### For Content Editors

URLs will automatically update to new format. Old bookmarks with `?lang=` will continue to work.

### For SEO/Marketing

1. Update sitemap.xml to use new URL format
2. Submit updated sitemap to Google Search Console
3. Monitor crawl errors for any issues
4. Update any hardcoded links in marketing materials

## Troubleshooting

### Issue: Links still show `?lang=` format

**Cause:** Using hardcoded URLs instead of helper functions

**Solution:** Replace:
```php
// Bad
$url = '/patients?lang=en';

// Good
$url = get_current_url_with_lang('en');
```

### Issue: Language not detected from URL

**Cause:** URL path detection not first in priority list

**Solution:** Ensure `url` is first in `language_detection.priority`:
```yaml
language_detection:
  priority:
    - url       # Must be first
    - session
    # ...
```

### Issue: 404 errors on `/en/` URLs

**Cause:** `.htaccess` rewrite rules may need update

**Solution:** Verify `.htaccess` has:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

## Performance Impact

- **Negligible:** Path manipulation is simple string operations
- **No database queries added**
- **Session/cookie behavior unchanged**
- **Server-side caching unaffected**

## Future Enhancements

1. **URL Redirects:** Add 301 redirects from old `?lang=` URLs to new path-based URLs
2. **Admin Interface:** GUI for managing language URL mappings
3. **Subdomain Support:** Option to use `en.example.com` instead of `/en/`
4. **Custom Language Prefixes:** Allow custom prefixes like `/english/` instead of `/en/`

## Conclusion

Phase 13 successfully transitions the ZPMS multilingual system from query parameter URLs to path-based URLs, providing:

✅ Better SEO and indexing
✅ Cleaner, more professional URLs
✅ Consistency with modern web frameworks
✅ Full backward compatibility
✅ No breaking changes for existing code

The system now handles both incoming path-based requests and generates path-based URLs for all outgoing links, creating a consistent multilingual URL structure throughout the application.
