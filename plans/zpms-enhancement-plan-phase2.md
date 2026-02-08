# ZPMS Enhancement Plan - Phase 2: Artifacts Integration

**Date:** February 7, 2026
**Plan Type:** Continuation of translation-plan-final.md
**Status:** Planning - Awaiting Review
**Target System:** ZPMS (Zeus Patient Management System) on Zeus Framework

---

## Context

This plan **continues** from the completed translation system Phase 1-4 and **integrates selected CMS artifacts** to enhance ZPMS functionality. Translation Phases 5-8 are **on hold** per user directive.

### Completed (Translation Plan Phases 1-4)

✅ **Phase 1:** Enhanced Language Detection with URL support
✅ **Phase 2:** File-Based Translation System (YAML)
✅ **Phase 3:** ZETEM Template Integration (Filters)
✅ **Phase 4:** Enhanced Dictionary System

**Files Created:**
- `fw/core/lib/LanguageDetector.php`
- `fw/core/lib/MultilingualManager.php`
- `fw/core/filters/TranslationFilters.php`
- `config/translations/en.yaml`, `el.yaml`
- `docs/URL_LANGUAGE_DETECTION.md`
- `web/templates/examples/translation-filters-examples.zetem`

### On Hold (Translation Plan Phases 5-8)

🔲 **Phase 5:** Enhanced Language Switcher (deferred)
🔲 **Phase 6:** Translation Management Interface (deferred)
🔲 **Phase 7:** SEO & Metadata (deferred)
🔲 **Phase 8:** Content Translation (deferred)

### New Phases (Artifacts Integration)

This plan adds:

🎯 **Phase 5 (NEW):** URLParser Integration - Simplify and standardize URL handling
🎯 **Phase 6 (NEW):** File Management System - Advanced file operations with stream wrappers
🎯 **Phase 7 (NEW):** Design System Enhancement - Healthcare-themed UI modernization

### Deferred Artifacts (For Future Plans)

- FormAPI (form-system)
- ChangeTracker (change-tracking)
- CLI Framework (cli-framework)

---

## Artifact Source Files

All artifacts remain in their original location for reference:

**Base Path:** `/home/evrokas/Downloads/pms/claude2/consolidated files/cms-artifacts/`

**Selected for Integration:**
- `multilingual/URLParser.php` (233 lines)
- `file-management/FileManager.php` (10,450 bytes)
- `design-system/design-system.css` (357 lines)
- `design-system/layout.css` (417 lines)
- `design-system/components.css` (255 lines)

---

## Phase 5: URLParser Integration

### Objective
Integrate artifacts URLParser as a **simplified alternative** to the existing LanguageDetector/MultilingualManager system, providing **helper functions** for URL generation and language-aware routing.

### Why This Integration?

**Current State:**
- ZPMS has comprehensive LanguageDetector + MultilingualManager (27KB)
- Advanced features: YAML translations, pluralization, context, database fallback
- Complex but powerful

**URLParser Benefits:**
- Simpler implementation (6KB, single class)
- Focused on URL handling specifically
- Provides convenient helper functions: `rel_url()`, `lang_url()`, `absolute_url()`
- Could complement existing system for URL-specific tasks

**Integration Strategy:**
- **Keep** existing LanguageDetector/MultilingualManager for translations
- **Add** URLParser as URL generation utility layer
- **Use** URLParser helpers in templates for cleaner code

### Implementation Steps

#### 1. Copy URLParser to Framework

```bash
cp /home/evrokas/Downloads/pms/claude2/consolidated\ files/cms-artifacts/multilingual/URLParser.php \
   /var/www/html/apps/zeusfw/core/lib/URLParser.php
```

#### 2. Adapt URLParser for Zeus Framework

**File:** `fw/core/lib/URLParser.php`

**Modifications:**

```php
<?php
/**
 * URLParser - Simplified URL handling and language-aware routing
 * Complements MultilingualManager by focusing on URL generation
 */

class URLParser {
    protected $supported_languages = [];
    protected $default_language = 'el';
    protected $base_url;
    protected $kernel;

    public function __construct($kernel = null, $supported_languages = [], $default_language = 'el') {
        global $kernel as $globalKernel;
        $this->kernel = $kernel ?? $globalKernel;

        // Get languages from kernel if not provided
        if (empty($supported_languages) && $this->kernel) {
            $this->supported_languages = $this->kernel->getSupportedLanguages();
        } else {
            $this->supported_languages = $supported_languages ?: ['el', 'en'];
        }

        $this->default_language = $default_language;
        $this->base_url = $this->getBaseUrl();
    }

    // ... (keep existing methods from artifact)

    /**
     * Integration with existing LanguageDetector
     */
    public function detectLanguage() {
        // Delegate to existing LanguageDetector if available
        if ($this->kernel && method_exists($this->kernel, 'getCurrentLanguage')) {
            return $this->kernel->getCurrentLanguage();
        }

        // Fallback to original logic
        // ... (original detectLanguage code)
    }
}
```

#### 3. Register Helper Functions in utils.php

**File:** `fw/core/kernel/utils.php`

**Add at end of file:**

```php
/**
 * URL Helper Functions (from URLParser)
 * These complement existing translation functions
 */

/**
 * Generate asset URL (language-independent)
 * Example: rel_url('/css/styles.css') → '/css/styles.css'
 */
function rel_url($path) {
    global $url_parser;
    if (!isset($url_parser)) {
        initURLParser();
    }
    return $url_parser->generateAssetUrl($path);
}

/**
 * Generate language-prefixed URL
 * Example: lang_url('/patients') → '/en/patients' or '/el/patients'
 */
function lang_url($path, $language = null) {
    global $url_parser;
    if (!isset($url_parser)) {
        initURLParser();
    }
    return $url_parser->generateLangUrl($path, $language);
}

/**
 * Generate absolute URL (no language prefix)
 * Example: absolute_url('/api/data') → 'https://example.com/api/data'
 */
function absolute_url($path) {
    global $url_parser;
    if (!isset($url_parser)) {
        initURLParser();
    }
    return $url_parser->generateAbsoluteUrl($path);
}

/**
 * Generate SEO-friendly absolute URL with language
 * Example: absolute_lang_url('/patients') → 'https://example.com/en/patients'
 */
function absolute_lang_url($path, $language = null) {
    global $url_parser;
    if (!isset($url_parser)) {
        initURLParser();
    }
    return $url_parser->generateSeoUrl($path, $language);
}

/**
 * Initialize URLParser singleton
 */
function initURLParser() {
    global $url_parser, $kernel;
    if (!isset($url_parser)) {
        require_once __DIR__ . '/../lib/URLParser.php';
        $url_parser = new URLParser($kernel);
    }
}
```

#### 4. Bootstrap URLParser in index.php

**File:** `web/index.php`

**Add after Kernel initialization (around line 50):**

```php
// Initialize URLParser for helper functions
initURLParser();
```

#### 5. Update Templates to Use URL Helpers

**Example conversions:**

**Before:**
```zetem
<link rel="stylesheet" href="/css/styles.css">
<a href="/en/patients">Patients</a>
```

**After:**
```zetem
<link rel="stylesheet" href="{{ rel_url('/css/styles.css') }}">
<a href="{{ lang_url('/patients') }}">Patients</a>
```

#### 6. Create Documentation

**File:** `docs/URL_HELPERS.md`

```markdown
# URL Helper Functions

ZPMS provides URL generation helpers that automatically handle language prefixes and base paths.

## Available Functions

### rel_url($path)
Generate asset URLs (CSS, JS, images) without language prefix.

### lang_url($path, $language = null)
Generate page URLs with language prefix.

### absolute_url($path)
Generate absolute URLs for emails, API responses.

### absolute_lang_url($path, $language = null)
Generate absolute URLs with language prefix for SEO.

## Examples

See: web/templates/examples/url-helpers-examples.zetem
```

### Testing Steps

1. ✅ Test `rel_url()` for assets
2. ✅ Test `lang_url()` for page links
3. ✅ Test language switching preserves correct URLs
4. ✅ Test absolute URLs for email templates
5. ✅ Verify no conflicts with existing LanguageDetector

### Estimated Effort

**Time:** 2-3 hours

**Breakdown:**
- Copy and adapt URLParser: 30 min
- Add helper functions to utils.php: 30 min
- Bootstrap in index.php: 10 min
- Update sample templates: 30 min
- Create documentation: 30 min
- Testing: 30 min

---

## Phase 6: File Management System Integration

### Objective
Integrate the comprehensive file management system from artifacts, providing **stream wrapper support**, **reference counting**, **temporary file cleanup**, and **cache management**.

### Why This Integration?

**Current State:**
- ZPMS likely has basic file upload handling
- Direct filesystem operations
- Manual file path management

**File Management System Benefits:**
- **Stream Wrappers:** Use `public://`, `private://`, `temp://`, `cache://` URIs
- **Reference Counting:** Prevent deletion of in-use files
- **Automatic Cleanup:** TTL-based temporary file removal
- **Integrity Verification:** SHA-256 hashing
- **YAML Persistence:** Store metadata without database
- **Separation of Concerns:** Managed vs temporary vs cache files

### Implementation Steps

#### 1. Copy File Manager to Framework

```bash
cp /home/evrokas/Downloads/pms/claude2/consolidated\ files/cms-artifacts/file-management/FileManager.php \
   /var/www/html/apps/zeusfw/core/lib/FileManager.php
```

#### 2. Analyze FileManager Structure

**Read the full FileManager.php to understand:**
- Class structure (ManagedFileManager, TemporaryFileManager, CacheFileManager)
- Stream wrapper implementation
- YAML storage schema
- Public API methods

#### 3. Create File Storage Directories

```bash
# Create directory structure
mkdir -p /var/www/html/apps/zpms/files/public
mkdir -p /var/www/html/apps/zpms/files/private
mkdir -p /var/www/html/apps/zpms/files/temp
mkdir -p /var/www/html/apps/zpms/files/cache
mkdir -p /var/www/html/apps/zpms/files/metadata

# Set permissions
chmod 755 /var/www/html/apps/zpms/files/public
chmod 700 /var/www/html/apps/zpms/files/private
chmod 755 /var/www/html/apps/zpms/files/temp
chmod 755 /var/www/html/apps/zpms/files/cache
chmod 755 /var/www/html/apps/zpms/files/metadata

# Set ownership
chown -R evrokas:www-data /var/www/html/apps/zpms/files
```

#### 4. Configure File Paths in settings.info.yaml

**File:** `config/settings.info.yaml`

**Add section:**

```yaml
file_system:
  # Base path for all file operations
  base_path: '../files'

  # Stream wrapper mappings
  streams:
    public:
      path: 'public'
      web_accessible: true
      url_prefix: '/files'
    private:
      path: 'private'
      web_accessible: false
    temp:
      path: 'temp'
      web_accessible: false
      ttl: 86400  # 24 hours
    cache:
      path: 'cache'
      web_accessible: false
      ttl: 604800  # 7 days

  # Metadata storage
  metadata:
    path: 'metadata'
    format: 'yaml'  # or 'json'

  # Automatic cleanup
  cleanup:
    enabled: true
    temp_files_ttl: 86400  # 24 hours
    cache_files_ttl: 604800  # 7 days
    run_on_cron: true
```

#### 5. Bootstrap File Manager in Kernel

**File:** `fw/core/kernel/Kernel.php`

**Add property:**

```php
protected $fileManager;
```

**Add to constructor (after MultilingualManager init):**

```php
// Initialize File Manager
if (file_exists(__DIR__ . '/../lib/FileManager.php')) {
    require_once __DIR__ . '/../lib/FileManager.php';
    $this->fileManager = new ManagedFileManager($this);
}
```

**Add getter method:**

```php
/**
 * Get File Manager instance
 */
public function getFileManager() {
    return $this->fileManager;
}
```

#### 6. Create File Upload Helper Functions

**File:** `fw/core/kernel/utils.php`

**Add:**

```php
/**
 * File Management Helper Functions
 */

/**
 * Save uploaded file to managed storage
 *
 * @param array $file $_FILES array element
 * @param string $destination Stream wrapper URI (e.g., 'public://documents/file.pdf')
 * @param string $entity_type Entity type using this file
 * @param string $entity_id Entity ID
 * @return array File metadata
 */
function file_save_upload($file, $destination, $entity_type = null, $entity_id = null) {
    global $kernel;
    $manager = $kernel->getFileManager();

    if (!$manager) {
        throw new Exception('File Manager not initialized');
    }

    return $manager->create($file['tmp_name'], $destination, $entity_type, $entity_id);
}

/**
 * Get file usage count
 *
 * @param string $uri Stream wrapper URI
 * @return int Usage count
 */
function file_usage_count($uri) {
    global $kernel;
    $manager = $kernel->getFileManager();
    return $manager ? $manager->getUsageCount($uri) : 0;
}

/**
 * Delete file if not in use
 *
 * @param string $uri Stream wrapper URI
 * @return bool Success
 */
function file_delete_if_unused($uri) {
    global $kernel;
    $manager = $kernel->getFileManager();

    if (!$manager) {
        return false;
    }

    if ($manager->getUsageCount($uri) === 0) {
        return $manager->delete($uri);
    }

    return false;
}

/**
 * Convert stream wrapper URI to web-accessible URL
 *
 * @param string $uri Stream wrapper URI (e.g., 'public://image.jpg')
 * @return string Web URL (e.g., '/files/image.jpg')
 */
function file_create_url($uri) {
    // Parse stream wrapper
    if (preg_match('#^(public|private|temp|cache)://(.+)$#', $uri, $matches)) {
        $scheme = $matches[1];
        $path = $matches[2];

        if ($scheme === 'public') {
            return '/files/' . $path;
        }
    }

    return $uri;
}
```

#### 7. Update File Upload Forms

**Example: Patient Document Upload**

**Before:**
```php
// Old approach - manual file handling
if ($_FILES['document']) {
    $upload_dir = '/var/www/html/apps/zpms/uploads/';
    $filename = basename($_FILES['document']['name']);
    move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $filename);
}
```

**After:**
```php
// New approach - managed file system
if ($_FILES['document']) {
    $file_metadata = file_save_upload(
        $_FILES['document'],
        'public://patient-documents/' . $patient_id . '/' . $_FILES['document']['name'],
        'patient',
        $patient_id
    );

    // Store file URI in database
    $document->setFileUri($file_metadata['uri']);
    $document->setFilename($file_metadata['filename']);
}
```

#### 8. Create Cleanup Cron Job

**File:** `cron/cleanup-temp-files.php`

```php
<?php
/**
 * Cleanup temporary and cache files
 * Run daily via cron: 0 2 * * * /usr/bin/php /path/to/cron/cleanup-temp-files.php
 */

require_once __DIR__ . '/../web/index.php';

$temp_manager = new TemporaryFileManager($kernel);
$cache_manager = new CacheFileManager($kernel);

// Cleanup expired temporary files
$temp_cleaned = $temp_manager->cleanup();
echo "Cleaned $temp_cleaned temporary files\n";

// Cleanup expired cache files
$cache_cleaned = $cache_manager->cleanup();
echo "Cleaned $cache_cleaned cache files\n";
```

#### 9. Add Web-Accessible File Serving

**File:** `.htaccess` (or create `files/.htaccess`)

```apache
# Serve public files
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Serve public files directly
  RewriteCond %{REQUEST_URI} ^/files/(.+)$
  RewriteCond %{DOCUMENT_ROOT}/../files/public/%1 -f
  RewriteRule ^files/(.+)$ ../files/public/$1 [L]

  # Block access to private, temp, cache directories
  RewriteRule ^files/(private|temp|cache)/ - [F,L]
</IfModule>
```

#### 10. Create Documentation

**File:** `docs/FILE_MANAGEMENT.md`

```markdown
# File Management System

ZPMS uses a managed file system with stream wrappers for organized file storage.

## Stream Wrappers

- `public://` - Web-accessible files (uploaded documents, images)
- `private://` - Protected files (medical records, invoices)
- `temp://` - Temporary files (auto-deleted after TTL)
- `cache://` - Cache files (auto-deleted after TTL)

## Helper Functions

- `file_save_upload()` - Save uploaded file
- `file_usage_count()` - Check file references
- `file_delete_if_unused()` - Safe file deletion
- `file_create_url()` - Convert URI to web URL

## Examples

### Upload Patient Document
```php
$file = file_save_upload(
    $_FILES['document'],
    'private://patients/' . $patient_id . '/report.pdf',
    'patient',
    $patient_id
);
```

### Temporary File Upload
```php
$temp_file = file_save_upload(
    $_FILES['import'],
    'temp://import-' . uniqid() . '.csv',
    null,
    null
);
// Auto-deleted after 24 hours
```

### Cache File Storage
```php
$cache_manager = new CacheFileManager($kernel);
$cache_manager->set('report_' . $id, $data, 3600); // 1 hour TTL
```
```

### Testing Steps

1. ✅ Test file upload with `public://` stream wrapper
2. ✅ Test file download via web URL
3. ✅ Test reference counting (prevent deletion of in-use files)
4. ✅ Test temporary file auto-cleanup
5. ✅ Test cache file storage and retrieval
6. ✅ Test private file access blocking
7. ✅ Test YAML metadata persistence

### Estimated Effort

**Time:** 6-8 hours

**Breakdown:**
- Read and understand FileManager.php: 1 hour
- Copy and adapt for Zeus: 1 hour
- Create directory structure: 30 min
- Configure settings.info.yaml: 30 min
- Bootstrap in Kernel: 30 min
- Create helper functions: 1 hour
- Update file upload forms: 1.5 hours
- Create cleanup cron: 30 min
- Configure .htaccess: 30 min
- Documentation: 1 hour
- Testing: 1 hour

---

## Phase 7: Design System Enhancement

### Objective
Modernize ZPMS UI by **selectively integrating** high-value components from the artifacts design system while maintaining backward compatibility.

### Strategy: Selective Cherry-Picking

**Approach:** Add enhancements **without replacing** existing design system.

**Integration Method:**
1. Keep existing `design/design-system.css` (260 lines)
2. Keep existing `design/layout.css` (226 lines)
3. Keep existing `design/components.css` (338 lines)
4. Create **new supplementary files** with artifact enhancements
5. Gradually adopt new components in templates

### Implementation Steps

#### 1. Create Supplementary Token File

**File:** `web/css/design/tokens-extended.css` (NEW)

**Copy from artifacts and adapt:**

```css
/* ===== EXTENDED DESIGN TOKENS ===== */
/* Supplements existing design-system.css */
/* Source: CMS Artifacts (Medical Teal Palette) */

:root {
    /* Medical Teal Alternative Palette */
    /* Use alongside existing blue primary */
    --primary-teal-50:  #e6f7f7;
    --primary-teal-100: #ccefef;
    --primary-teal-200: #99dfdf;
    --primary-teal-500: #0d9488;
    --primary-teal-600: #0b7a70;
    --primary-teal-700: #096159;
    --primary-teal-800: #064741;
    --primary-teal-900: #042e2a;

    /* Enhanced Slate (alongside existing gray) */
    --slate-50:  #f8fafc;
    --slate-100: #f1f5f9;
    --slate-200: #e2e8f0;
    --slate-300: #cbd5e1;
    --slate-400: #94a3b8;
    --slate-500: #64748b;
    --slate-600: #475569;
    --slate-700: #334155;
    --slate-800: #1e293b;
    --slate-900: #0f172a;

    /* Enhanced Semantic Colors (add missing shades) */
    --success-600: #059669;
    --warning-600: #d97706;
    --danger-600: #dc2626;
    --info-600: #2563eb;

    /* Granular Spacing (8px base system) */
    --space-1:  0.25rem;   /* 4px */
    --space-2:  0.5rem;    /* 8px */
    --space-3:  0.75rem;   /* 12px */
    --space-4:  1rem;      /* 16px */
    --space-5:  1.25rem;   /* 20px */
    --space-6:  1.5rem;    /* 24px */
    --space-8:  2rem;      /* 32px */
    --space-10: 2.5rem;    /* 40px */
    --space-12: 3rem;      /* 48px */

    /* Additional Shadows */
    --shadow-xs:    0 1px 2px rgba(15,23,42,.04);
    --shadow-focus: 0 0 0 3px rgba(13,148,136,.18);

    /* Additional Transitions */
    --transition-fast: 150ms ease;
    --transition-slow: 300ms ease;
}
```

#### 2. Create Medical Components File

**File:** `web/css/design/components-medical.css` (NEW)

**Copy selected components from artifacts:**

```css
/* ===== MEDICAL-SPECIFIC COMPONENTS ===== */
/* Source: CMS Artifacts */
/* Supplements existing components.css */

/* ─── STATUS INDICATORS ─── */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--font-size-sm);
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-dot.active { background: var(--success-500); }
.status-dot.pending { background: var(--warning-500); }
.status-dot.inactive { background: var(--gray-400); }
.status-dot.urgent { background: var(--danger-500); }

/* ─── ICON BOXES ─── */
.icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-lg);
}
.icon-box-sm { width: 32px; height: 32px; }
.icon-box-md { width: 40px; height: 40px; }
.icon-box-lg { width: 48px; height: 48px; }

.icon-box-primary { background-color: var(--primary-100); color: var(--primary-600); }
.icon-box-success { background-color: var(--success-50); color: var(--success-600); }
.icon-box-warning { background-color: var(--warning-50); color: var(--warning-600); }
.icon-box-danger { background-color: var(--danger-50); color: var(--danger-600); }

/* ─── FILTER CHIPS ─── */
.filter-bar {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    flex-wrap: wrap;
    margin-bottom: var(--space-lg);
}
.filter-chips { display: flex; gap: var(--space-sm); flex-wrap: wrap; }
.filter-chip {
    padding: var(--space-xs) var(--space-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    border: 1px solid var(--border);
    border-radius: var(--radius-full);
    background: var(--background);
    cursor: pointer;
    color: var(--text-secondary);
    transition: all var(--transition-base);
}
.filter-chip:hover {
    border-color: var(--primary-500);
    color: var(--primary-600);
    background: var(--primary-50);
}
.filter-chip.active {
    background: var(--primary-600);
    color: #fff;
    border-color: var(--primary-600);
}

/* ─── EMPTY STATES ─── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--space-xl) var(--space-md);
    text-align: center;
}
.empty-state-icon { margin-bottom: var(--space-md); color: var(--text-secondary); }
.empty-state-title {
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
}
.empty-state-desc {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    margin-top: var(--space-sm);
    max-width: 320px;
}

/* ─── KPI CARDS ─── */
.kpi-card .card-body { padding: var(--space-lg); }
.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--space-md);
}
.kpi-label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--text-secondary);
}
.kpi-value {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    line-height: 1.2;
    margin-bottom: var(--space-sm);
}
.kpi-trend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}
.kpi-trend .trend-up { color: var(--success-600); }
.kpi-trend .trend-down { color: var(--danger-600); }
.kpi-trend .trend-label { color: var(--text-secondary); }

/* ─── PATIENT LIST ITEMS ─── */
.patient-list { display: flex; flex-direction: column; gap: var(--space-sm); }
.patient-item {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-md);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--background);
    transition: background var(--transition-base), border-color var(--transition-base);
    cursor: pointer;
}
.patient-item:hover { background: var(--gray-50); border-color: var(--gray-300); }

.patient-avatar {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-full);
    background: var(--primary-100);
    color: var(--primary-700);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-bold);
    font-size: var(--font-size-sm);
    flex-shrink: 0;
}
.patient-info { flex: 1; min-width: 0; }
.patient-name {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.patient-meta {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-top: 2px;
}

/* ─── APPOINTMENT LIST ITEMS ─── */
.appointment-list { display: flex; flex-direction: column; gap: var(--space-sm); }
.appointment-item {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-md);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--background);
    transition: background var(--transition-base), border-color var(--transition-base);
    cursor: pointer;
}
.appointment-item:hover { background: var(--gray-50); border-color: var(--primary-200); }

.appointment-time {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--primary-600);
    min-width: 68px;
    flex-shrink: 0;
}
.appointment-details { flex: 1; min-width: 0; }
.appointment-patient {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.appointment-type {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-top: 2px;
}
```

#### 3. Update settings.info.yaml Library Definitions

**File:** `config/settings.info.yaml`

**Add new CSS libraries:**

```yaml
libraries:
  # Existing libraries
  base:
    css:
      - web/css/normalize.css
      - web/css/design/design-system.css
      - web/css/design/layout.css
      - web/css/design/components.css

  # NEW: Extended tokens
  tokens-extended:
    css:
      - web/css/design/tokens-extended.css

  # NEW: Medical components
  components-medical:
    css:
      - web/css/design/components-medical.css
    dependencies:
      - tokens-extended

  # Complete design system (includes all)
  design-complete:
    dependencies:
      - base
      - tokens-extended
      - components-medical
```

#### 4. Create Example Template with New Components

**File:** `web/templates/examples/medical-components-demo.zetem`

```zetem
{# Medical Components Demo #}
{% attach_library('design-complete') %}

<div class="content-wrapper">
    <h1>Medical Components Demo</h1>

    {# Status Indicators #}
    <section class="mb-6">
        <h2>Status Indicators</h2>
        <div class="flex gap-4">
            <span class="status-indicator">
                <span class="status-dot active"></span>
                Active
            </span>
            <span class="status-indicator">
                <span class="status-dot pending"></span>
                Pending
            </span>
            <span class="status-indicator">
                <span class="status-dot inactive"></span>
                Inactive
            </span>
            <span class="status-indicator">
                <span class="status-dot urgent"></span>
                Urgent
            </span>
        </div>
    </section>

    {# Filter Chips #}
    <section class="mb-6">
        <h2>Filter Chips</h2>
        <div class="filter-bar">
            <span class="filter-chips">
                <button class="filter-chip active">All</button>
                <button class="filter-chip">Active</button>
                <button class="filter-chip">Pending</button>
                <button class="filter-chip">Completed</button>
            </span>
        </div>
    </section>

    {# Empty State #}
    <section class="mb-6">
        <h2>Empty State</h2>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bx bx-file" style="font-size: 48px;"></i>
                    </div>
                    <div class="empty-state-title">No patients found</div>
                    <div class="empty-state-desc">
                        There are no patients matching your search criteria.
                        Try adjusting your filters or create a new patient.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {# KPI Card #}
    <section class="mb-6">
        <h2>KPI Card</h2>
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-header">
                    <span class="kpi-label">Total Patients</span>
                    <span class="icon-box icon-box-md icon-box-primary">
                        <i class="bx bx-user"></i>
                    </span>
                </div>
                <div class="kpi-value">1,234</div>
                <div class="kpi-trend">
                    <span class="trend-up">↑ 12%</span>
                    <span class="trend-label">vs last month</span>
                </div>
            </div>
        </div>
    </section>

    {# Patient List #}
    <section class="mb-6">
        <h2>Patient List</h2>
        <div class="patient-list">
            <div class="patient-item">
                <div class="patient-avatar">JD</div>
                <div class="patient-info">
                    <div class="patient-name">John Doe</div>
                    <div class="patient-meta">ID: P-12345 • Last visit: Feb 5, 2026</div>
                </div>
                <span class="status-indicator">
                    <span class="status-dot active"></span>
                    Active
                </span>
            </div>
            <div class="patient-item">
                <div class="patient-avatar">JS</div>
                <div class="patient-info">
                    <div class="patient-name">Jane Smith</div>
                    <div class="patient-meta">ID: P-12346 • Last visit: Feb 3, 2026</div>
                </div>
                <span class="status-indicator">
                    <span class="status-dot pending"></span>
                    Pending
                </span>
            </div>
        </div>
    </section>
</div>
```

#### 5. Update Key Templates Gradually

**Priority Pages for Component Adoption:**

1. **Dashboard** (`web/templates/content/dashboard.zetem`)
   - Add KPI cards
   - Add status indicators
   - Use icon boxes

2. **Patient List** (`web/templates/content/patients_list.zetem`)
   - Use patient list items
   - Add filter chips
   - Add empty state for no results

3. **Appointment Calendar** (`web/templates/content/appointments.zetem`)
   - Use appointment list items
   - Add filter chips for date ranges

#### 6. Create Migration Guide

**File:** `docs/DESIGN_SYSTEM_MIGRATION.md`

```markdown
# Design System Migration Guide

## Current Status

ZPMS now has **two design system layers**:

1. **Base Design System** (existing)
   - `design/design-system.css` - Blue palette, basic tokens
   - `design/layout.css` - Layout structure
   - `design/components.css` - Core components

2. **Extended Design System** (new from artifacts)
   - `design/tokens-extended.css` - Medical teal palette, granular spacing
   - `design/components-medical.css` - Healthcare-specific components

## Using New Components

### Load Complete Design System

In templates:
```zetem
{% attach_library('design-complete') %}
```

### Available New Components

- Status indicators with colored dots
- Icon boxes with semantic colors
- Filter chips with active states
- Empty states for empty lists
- KPI cards with trend indicators
- Patient/appointment list items

## Migration Strategy

1. **Keep existing styles working** - No breaking changes
2. **Adopt new components gradually** - Update templates one by one
3. **Test thoroughly** - Verify visual consistency

## Color Palette Options

You can now use both palettes:

- **Blue (existing):** `--primary-500` → `#2196f3`
- **Medical Teal (new):** `--primary-teal-500` → `#0d9488`

Choose based on page context or gradually switch to teal for medical theme.
```

### Testing Steps

1. ✅ Test new CSS files load without conflicts
2. ✅ Test status indicators display correctly
3. ✅ Test filter chips toggle active state
4. ✅ Test empty state centers properly
5. ✅ Test KPI cards display metrics
6. ✅ Test patient/appointment list items
7. ✅ Test responsive behavior on mobile
8. ✅ Verify no visual regressions on existing pages

### Estimated Effort

**Time:** 4-5 hours

**Breakdown:**
- Create tokens-extended.css: 1 hour
- Create components-medical.css: 1.5 hours
- Update settings.info.yaml: 15 min
- Create demo template: 30 min
- Update dashboard template: 1 hour
- Create migration guide: 30 min
- Testing: 30 min

---

## Summary & Timeline

### Total Estimated Effort

| Phase | Time | Priority |
|-------|------|----------|
| Phase 5: URLParser Integration | 2-3 hours | Medium |
| Phase 6: File Management System | 6-8 hours | High |
| Phase 7: Design System Enhancement | 4-5 hours | High |
| **Total** | **12-16 hours** | |

### Implementation Order (Recommended)

**Week 1:**
1. ✅ Phase 7: Design System Enhancement (4-5 hours)
   - Immediate visual improvements
   - Low risk, high impact
   - Can be done independently

**Week 2:**
2. ✅ Phase 6: File Management System (6-8 hours)
   - Core infrastructure improvement
   - Foundational for future features
   - Requires careful testing

**Week 3:**
3. ✅ Phase 5: URLParser Integration (2-3 hours)
   - Nice-to-have helper functions
   - Complements existing system
   - Optional enhancement

### Critical Files Modified

**New Files:**
- `fw/core/lib/URLParser.php`
- `fw/core/lib/FileManager.php`
- `web/css/design/tokens-extended.css`
- `web/css/design/components-medical.css`
- `cron/cleanup-temp-files.php`
- `docs/URL_HELPERS.md`
- `docs/FILE_MANAGEMENT.md`
- `docs/DESIGN_SYSTEM_MIGRATION.md`
- `web/templates/examples/url-helpers-examples.zetem`
- `web/templates/examples/medical-components-demo.zetem`

**Modified Files:**
- `fw/core/kernel/utils.php` (add helper functions)
- `fw/core/kernel/Kernel.php` (bootstrap FileManager)
- `web/index.php` (initialize URLParser)
- `config/settings.info.yaml` (add file_system config, library definitions)
- `.htaccess` (add file serving rules)
- `web/templates/content/dashboard.zetem` (use new components)
- `web/templates/content/patients_list.zetem` (use new components)

**Directories Created:**
- `/var/www/html/apps/zpms/files/public`
- `/var/www/html/apps/zpms/files/private`
- `/var/www/html/apps/zpms/files/temp`
- `/var/www/html/apps/zpms/files/cache`
- `/var/www/html/apps/zpms/files/metadata`

### Deferred for Future Plans

**Translation System (on hold):**
- Phase 5: Enhanced Language Switcher
- Phase 6: Translation Management Interface
- Phase 7: SEO & Metadata
- Phase 8: Content Translation

**Artifacts (on hold):**
- FormAPI (form-system)
- ChangeTracker (change-tracking)
- CLI Framework (cli-framework)

### Verification Checklist

**After Phase 5 (URLParser):**
- [ ] `rel_url()` generates correct asset paths
- [ ] `lang_url()` includes language prefix
- [ ] Language switching preserves URLs
- [ ] No conflicts with existing LanguageDetector

**After Phase 6 (File Management):**
- [ ] File upload with stream wrappers works
- [ ] Public files accessible via web
- [ ] Private files blocked from direct access
- [ ] Reference counting prevents premature deletion
- [ ] Temporary files auto-cleanup after TTL
- [ ] YAML metadata persists correctly

**After Phase 7 (Design System):**
- [ ] New CSS files load without conflicts
- [ ] Status indicators display correctly
- [ ] Filter chips toggle active state
- [ ] Empty states render properly
- [ ] KPI cards show metrics and trends
- [ ] Patient/appointment lists styled correctly
- [ ] Mobile responsive behavior works
- [ ] No visual regressions on existing pages

---

## Next Steps

1. **Review this plan** - Ensure all phases align with requirements
2. **Approve for implementation** - Confirm scope and timeline
3. **Begin Phase 7** - Start with Design System (lowest risk, highest visual impact)
4. **Test thoroughly** - Verify each phase before moving to next
5. **Document changes** - Update user documentation as features are added

**Artifact files remain in their original location for future reference:**
- `/home/evrokas/Downloads/pms/claude2/consolidated files/cms-artifacts/`

---

**Plan Status:** ✅ Ready for Review
**Awaiting:** User approval to begin implementation
