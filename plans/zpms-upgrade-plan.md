# ZPMS Comprehensive Upgrade Plan
# Translation System + Design System Modernization

**Date:** February 8, 2026
**Target System:** ZPMS (Zeus Patient Management System) on Zeus Framework
**Overall Status:** 42% Complete (5 of 12 phases)

---

## Executive Summary

This plan consolidates two major upgrade initiatives:
1. **Enhanced Multilingual System** (Phases 1-4, 9-12)
2. **Design System Modernization** (Phases 5-8)

### Translation System
- **Status:** Foundation complete (Phases 1-4), enhancements pending
- **Approach:** Hybrid file-based + database translations with URL language detection
- **Impact:** Better internationalization, admin translation management, SEO optimization

### Design System
- **Status:** Not started
- **Approach:** Selective integration of CMS artifacts design system
- **Impact:** Healthcare-themed UI, comprehensive component library, better responsive design
- **Key Decision:** Use medical teal color scheme, keep system fonts (no custom web fonts)

---

## Implementation Status

| Phase | Category | Status | Description |
|-------|----------|--------|-------------|
| **Phase 1** | Translation | ✅ Complete | Enhanced Language Detection (URL-based) |
| **Phase 2** | Translation | ✅ Complete | File-Based Translation System (YAML) |
| **Phase 3** | Translation | ✅ Complete | ZETEM Template Integration (Filters) |
| **Phase 4** | Translation | ✅ Complete | Enhanced Dictionary System |
| **Phase 5** | Design System | ✅ Complete | Token System Enhancement |
| **Phase 6** | Design System | 🔲 Pending | Core Component Library |
| **Phase 7** | Design System | 🔲 Pending | Layout & Responsive |
| **Phase 8** | Design System | 🔲 Pending | Medical Components & Integration |
| **Phase 9** | Translation | 🔲 Pending | Enhanced Language Switcher |
| **Phase 10** | Translation | 🔲 Pending | Translation Management Interface |
| **Phase 11** | Translation | 🔲 Pending | SEO & Metadata |
| **Phase 12** | Translation | 🔲 Pending | Content Translation (Future) |

---

## Phase 1: Enhanced Language Detection ✅ COMPLETED

**Category:** Translation System
**Status:** ✅ Fully implemented and tested
**Estimated Time:** 4-6 hours

### Objective
Add intelligent language detection with configurable priority, including URL path prefix support.

### Implementation Details

**Files Created:**
- ✅ `fw/core/lib/LanguageDetector.php` - Advanced language detection class
- ✅ `docs/URL_LANGUAGE_DETECTION.md` - Documentation

**Files Modified:**
- ✅ `fw/core/kernel/Kernel.php` - Enhanced language methods (lines 725-732, 812)
- ✅ `config/settings.info.yaml` - Added language detection config

**Features Implemented:**
- ✅ URL path prefix detection (`/en/page`, `/el/page`) - Highest priority
- ✅ Session-based detection (`$_SESSION['CURRENT_LANGUAGE']`)
- ✅ Cookie-based persistence (1-year expiry)
- ✅ User profile detection (database-stored preference)
- ✅ Query parameter detection (`?lang=en`)
- ✅ Browser header detection (`HTTP_ACCEPT_LANGUAGE`)
- ✅ Configurable detection priority
- ✅ URL helper methods for path manipulation

**Configuration Added:**
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

language_detection:
  default: el
  priority:
    - session
    - cookie
    - user
    - query
    - browser
    - default
```

---

## Phase 2: File-Based Translation System ✅ COMPLETED

**Category:** Translation System
**Status:** ✅ Fully implemented and tested
**Estimated Time:** 6-8 hours

### Objective
Add YAML-based translation files as an alternative to database dictionary.

### Implementation Details

**Files Created:**
- ✅ `fw/core/lib/MultilingualManager.php` - Translation manager class
- ✅ `config/translations/en.yaml` - English translations (comprehensive)
- ✅ `config/translations/el.yaml` - Greek translations (comprehensive)

**Features Implemented:**
- ✅ YAML translation file loading and caching
- ✅ Nested key access with dot notation: `t('dashboard.welcome.message')`
- ✅ Parameter replacement: `t('msg.hello', ['name' => 'John'])`
- ✅ Pluralization support: `t_plural('item', $count)`
- ✅ Context support: `t_context('bank', 'financial')`
- ✅ Fallback chain: file → default language → database → key
- ✅ Hybrid file + database approach

**Translation File Structure:**
```yaml
nav:
  home: Home
  patients: Patients
  appointments: Appointments

dashboard:
  welcome: "Welcome back, {name}!"
  total_patients: Total Patients

messages:
  save_success: Changes saved successfully
  delete_confirm: Are you sure you want to delete {item}?
```

---

## Phase 3: ZETEM Template Integration ✅ COMPLETED

**Category:** Translation System
**Status:** ✅ Fully implemented and tested
**Estimated Time:** 2-3 hours

### Objective
Add translation filters and syntax to ZETEM templates.

### Implementation Details

**Files Created:**
- ✅ `fw/core/filters/TranslationFilters.php` - ZETEM filter definitions
- ✅ `web/templates/examples/translation-filters-examples.zetem` - Usage examples
- ✅ `web/test/test_translation_filters.php` - Filter tests

**Filters Implemented:**
- ✅ `t` - Basic translation: `{{ 'nav.home' | t }}`
- ✅ `translate` - Verbose alias: `{{ 'messages.welcome' | translate }}`
- ✅ `lang_text` - Array-based (backward compatibility): `{{ $item['title'] | lang_text }}`
- ✅ `t_params` - With parameters: `{{ 'msg.hello' | t_params({'name': $user}) }}`
- ✅ `t_plural` - Pluralization: `{{ 'item' | t_plural($count) }}`
- ✅ `t_context` - Context-aware: `{{ 'bank' | t_context('financial') }}`

**Template Examples:**
```zetem
{# Old syntax - still works #}
{{ getLangText($item['title']) }}

{# New syntax - recommended #}
{{ 'nav.home' | t }}
{{ 'dashboard.welcome' | t_params({'name': $userName}) }}
{{ 'patient' | t_plural($patientCount) }}
```

---

## Phase 4: Enhanced Dictionary System ✅ COMPLETED

**Category:** Translation System
**Status:** ✅ Fully implemented and tested
**Estimated Time:** 4-5 hours

### Objective
Improve the database-driven translation system with import/export and statistics.

### Implementation Details

**Files Modified:**
- ✅ `fw/core/dictionaryClassEx.php` - Enhanced with new methods

**Methods Added:**
- ✅ `getAllTokens()` - Retrieve all dictionary entries
- ✅ `updateTranslation($token, $lang, $translation)` - Update specific translation
- ✅ `deleteToken($token)` - Remove dictionary entry
- ✅ `getUntranslated($lang)` - Find missing translations
- ✅ `exportToYAML($lang)` - Export dictionary to YAML file
- ✅ `importFromYAML($lang, $file)` - Import YAML file to dictionary
- ✅ `getTranslationStats()` - Count translated vs untranslated per language
- ✅ `getRecentTokens($limit)` - Recently added tokens

**Configuration Added:**
```yaml
dictionary:
  auto_register: true        # Auto-add new tokens
  fallback_to_file: true     # Check YAML files if not in DB
  prefer_database: false     # Prefer file-based over DB
```

**Integration Strategy:**
- File-based for static UI strings (faster, version-controlled)
- Database for dynamic content (user-generated, admin-editable)
- Configurable preference order

---

## Phase 5: Design System - Token Enhancement ✅ COMPLETED

**Category:** Design System
**Status:** ✅ Complete — February 8, 2026
**Estimated Time:** 2-3 hours
**Actual Time:** ~2.5 hours
**Source:** CMS Artifacts `/design-system/design-system.css`

### Objective
Replace existing design tokens with comprehensive healthcare-themed token system from CMS artifacts.

### Tasks

#### 1. Replace Color Palette (Medical Teal Theme)

**File:** `web/css/design/design-system.css`

**Replace Primary Colors:**
```css
/* OLD - Blue theme */
--primary-500: #2196f3;
--primary-600: #1976d2;

/* NEW - Medical Teal theme */
--primary-50: #f0fdfa;
--primary-100: #ccfbf1;
--primary-200: #99f6e4;
--primary-300: #5eead4;
--primary-400: #2dd4bf;
--primary-500: #14b8a6;
--primary-600: #0d9488;
--primary-700: #0f766e;
--primary-800: #115e59;
--primary-900: #134e4a;
```

**Replace Gray with Slate:**
```css
/* OLD - Gray scale */
--gray-50: #f9fafb;
/* ... */
--gray-900: #111827;

/* NEW - Slate scale (healthcare neutral) */
--slate-50: #f8fafc;
--slate-100: #f1f5f9;
--slate-200: #e2e8f0;
--slate-300: #cbd5e1;
--slate-400: #94a3b8;
--slate-500: #64748b;
--slate-600: #475569;
--slate-700: #334155;
--slate-800: #1e293b;
--slate-900: #0f172a;
```

**Add Enhanced Semantic Colors:**
```css
/* Success (green) - 5 shades */
--success-50: #f0fdf4;
--success-100: #dcfce7;
--success-500: #22c55e;
--success-600: #16a34a;
--success-700: #15803d;

/* Warning (amber) - 4 shades */
--warning-50: #fffbeb;
--warning-100: #fef3c7;
--warning-500: #f59e0b;
--warning-600: #d97706;

/* Danger (red) - 4 shades */
--danger-50: #fef2f2;
--danger-100: #fee2e2;
--danger-500: #ef4444;
--danger-600: #dc2626;

/* Info (blue) - 4 shades */
--info-50: #eff6ff;
--info-100: #dbeafe;
--info-500: #3b82f6;
--info-600: #2563eb;
```

#### 2. Enhance Spacing System

**Replace with Granular 8px Base System:**
```css
/* OLD - 5 values */
--space-xs: 0.25rem;  /* 4px */
--space-sm: 0.5rem;   /* 8px */
--space-md: 1rem;     /* 16px */
--space-lg: 1.5rem;   /* 24px */
--space-xl: 2rem;     /* 32px */

/* NEW - 10 values (8px base) */
--space-1: 0.25rem;   /* 4px */
--space-2: 0.5rem;    /* 8px */
--space-3: 0.75rem;   /* 12px */
--space-4: 1rem;      /* 16px */
--space-5: 1.25rem;   /* 20px */
--space-6: 1.5rem;    /* 24px */
--space-8: 2rem;      /* 32px */
--space-10: 2.5rem;   /* 40px */
--space-12: 3rem;     /* 48px */
--space-16: 4rem;     /* 64px */
```

#### 3. Enhance Shadow System

**Add Additional Shadow Values:**
```css
/* OLD - 3 values */
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

/* NEW - 6 values + focus */
--shadow-xs: 0 1px 2px 0 rgba(0,0,0,.05);
--shadow-sm: 0 1px 3px 0 rgba(0,0,0,.1), 0 1px 2px -1px rgba(0,0,0,.1);
--shadow-md: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
--shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
--shadow-xl: 0 20px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.1);
--shadow-2xl: 0 25px 50px -12px rgba(0,0,0,.25);
--shadow-focus: 0 0 0 3px rgba(13,148,136,.18);  /* Accessibility */
```

#### 4. Enhance Border Radius

**Add Additional Radius Values:**
```css
/* OLD - 4 values */
--radius-sm: 0.25rem;
--radius-md: 0.375rem;
--radius-lg: 0.5rem;
--radius-full: 9999px;

/* NEW - 6 values */
--radius-sm: 0.25rem;   /* 4px */
--radius-md: 0.375rem;  /* 6px */
--radius-lg: 0.5rem;    /* 8px */
--radius-xl: 0.75rem;   /* 12px */
--radius-2xl: 1rem;     /* 16px */
--radius-full: 9999px;
```

#### 5. Enhance Transition System

**Add Variable Speeds:**
```css
/* OLD - 1 value */
--transition: all 0.2s ease;

/* NEW - 3 speeds */
--transition-fast: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-base: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-slow: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
```

#### 6. Keep System Fonts (DO NOT Change)

**IMPORTANT:** Do NOT adopt custom web fonts from artifacts. Keep existing system fonts:

```css
/* KEEP THIS - Do not change */
--font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-mono: "SF Mono", "Monaco", "Inconsolata", "Fira Code", monospace;
```

**DO NOT ADD:**
- ❌ Plus Jakarta Sans
- ❌ DM Sans
- ❌ Google Fonts imports
- ❌ External font loading

**Reason:** Avoid external dependencies, performance impact, and GDPR concerns.

#### 7. Add Layout Variables

**New Variables for App Structure:**
```css
/* Layout structure */
--sidebar-width: 280px;
--topbar-height: 64px;
--content-max-width: 1400px;
```

### Testing Checklist

**Visual Regression:**
- [ ] Dashboard renders correctly with new colors
- [ ] Buttons use medical teal instead of blue
- [ ] Spacing is consistent across pages
- [ ] Shadows render properly
- [ ] No visual glitches or layout shifts

**Token Usage:**
- [ ] Old `--gray-*` references updated to `--slate-*`
- [ ] Old `--primary-*` references work with new teal values
- [ ] Old `--space-*` references mapped to new values
- [ ] Semantic colors (`--success-*`, etc.) used where appropriate

**Performance:**
- [ ] CSS file size is acceptable
- [ ] No FOUC (Flash of Unstyled Content)
- [ ] Page load time unchanged

### Migration Notes

**Backward Compatibility:**
- Keep old variable names as aliases during migration
- Add new variables alongside old ones initially
- Gradual replacement over multiple commits

**Example Alias Strategy:**
```css
/* Aliases for backward compatibility */
--gray-50: var(--slate-50);
--gray-100: var(--slate-100);
/* ... etc */

--space-xs: var(--space-1);
--space-sm: var(--space-2);
--space-md: var(--space-4);
--space-lg: var(--space-6);
--space-xl: var(--space-8);
```

---

## Phase 6: Design System - Core Component Library 🔲 PENDING

**Category:** Design System
**Status:** 🔲 Not started
**Estimated Time:** 4-5 hours
**Source:** CMS Artifacts `/design-system/design-system.css` + `/components.css`

### Objective
Add comprehensive component library from CMS artifacts with medical-specific UI patterns.

### Tasks

#### 1. Enhanced Button System

**File:** `web/css/design/components.css`

**Add Button Variants:**
```css
/* Primary button (medical teal) */
.btn-primary {
  background: var(--primary-600);
  color: white;
  border: 1px solid transparent;
}
.btn-primary:hover {
  background: var(--primary-700);
}

/* Outline button */
.btn-outline {
  background: transparent;
  color: var(--primary-600);
  border: 1px solid var(--primary-600);
}
.btn-outline:hover {
  background: var(--primary-50);
}

/* Ghost button */
.btn-ghost {
  background: transparent;
  color: var(--slate-700);
  border: 1px solid transparent;
}
.btn-ghost:hover {
  background: var(--slate-100);
}

/* Danger button */
.btn-danger {
  background: var(--danger-600);
  color: white;
  border: 1px solid transparent;
}
.btn-danger:hover {
  background: var(--danger-700);
}

/* Success button */
.btn-success {
  background: var(--success-600);
  color: white;
  border: 1px solid transparent;
}
.btn-success:hover {
  background: var(--success-700);
}

/* Button sizes */
.btn-sm {
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-sm);
}
.btn-base {
  padding: var(--space-3) var(--space-4);
  font-size: var(--text-base);
}
.btn-lg {
  padding: var(--space-4) var(--space-6);
  font-size: var(--text-lg);
}

/* Icon-only buttons */
.btn-icon {
  padding: var(--space-2);
  width: 2.5rem;
  height: 2.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
```

#### 2. Status Indicators

**Add Status Dots and Badges:**
```css
/* Status indicator with dot + label */
.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
}

/* Status dot (8px) */
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot.active { background: var(--success-500); }
.status-dot.pending { background: var(--warning-500); }
.status-dot.cancelled { background: var(--danger-500); }
.status-dot.completed { background: var(--info-500); }
.status-dot.inactive { background: var(--slate-400); }

/* Status badge */
.badge {
  display: inline-flex;
  align-items: center;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 500;
  line-height: 1;
}
.badge.success {
  background: var(--success-100);
  color: var(--success-700);
}
.badge.warning {
  background: var(--warning-100);
  color: var(--warning-700);
}
.badge.danger {
  background: var(--danger-100);
  color: var(--danger-700);
}
.badge.info {
  background: var(--info-100);
  color: var(--info-700);
}
```

#### 3. Icon Box System

**Add Icon Containers for KPIs:**
```css
/* Icon box (for dashboard KPIs) */
.icon-box {
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-lg);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

/* Color variants */
.icon-box.primary {
  background: var(--primary-100);
  color: var(--primary-600);
}
.icon-box.success {
  background: var(--success-100);
  color: var(--success-600);
}
.icon-box.warning {
  background: var(--warning-100);
  color: var(--warning-600);
}
.icon-box.danger {
  background: var(--danger-100);
  color: var(--danger-600);
}
.icon-box.info {
  background: var(--info-100);
  color: var(--info-600);
}
```

#### 4. Empty States

**Add Empty State Component:**
```css
/* Empty state (for empty lists/tables) */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--space-12) var(--space-6);
  text-align: center;
}

.empty-state-icon {
  font-size: 3rem;
  color: var(--slate-400);
  margin-bottom: var(--space-4);
}

.empty-state-title {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--slate-900);
  margin-bottom: var(--space-2);
}

.empty-state-description {
  font-size: var(--text-base);
  color: var(--slate-600);
  max-width: 28rem;
  margin-bottom: var(--space-6);
}

.empty-state-action {
  /* Style for CTA button */
}
```

#### 5. Filter Chips

**Add Filter Chip Component:**
```css
/* Filter chip (for search filters) */
.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  background: var(--slate-100);
  border: 1px solid var(--slate-300);
  border-radius: var(--radius-full);
  font-size: var(--text-sm);
  color: var(--slate-700);
  cursor: pointer;
  transition: var(--transition-fast);
}

.filter-chip:hover {
  background: var(--slate-200);
  border-color: var(--slate-400);
}

.filter-chip.active {
  background: var(--primary-100);
  border-color: var(--primary-600);
  color: var(--primary-700);
}

.filter-chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  background: var(--slate-400);
  color: white;
  font-size: 0.75rem;
  margin-left: var(--space-1);
}

.filter-chip.active .filter-chip-remove {
  background: var(--primary-600);
}
```

#### 6. Alert Component

**Add Alert Component:**
```css
/* Alert component */
.alert {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  border: 1px solid;
  margin-bottom: var(--space-4);
}

.alert-icon {
  flex-shrink: 0;
  font-size: 1.25rem;
}

.alert-content {
  flex: 1;
}

.alert-title {
  font-weight: 600;
  margin-bottom: var(--space-1);
}

.alert-description {
  font-size: var(--text-sm);
}

/* Alert variants */
.alert.success {
  background: var(--success-50);
  border-color: var(--success-200);
  color: var(--success-800);
}
.alert.success .alert-icon { color: var(--success-600); }

.alert.warning {
  background: var(--warning-50);
  border-color: var(--warning-200);
  color: var(--warning-800);
}
.alert.warning .alert-icon { color: var(--warning-600); }

.alert.danger {
  background: var(--danger-50);
  border-color: var(--danger-200);
  color: var(--danger-800);
}
.alert.danger .alert-icon { color: var(--danger-600); }

.alert.info {
  background: var(--info-50);
  border-color: var(--info-200);
  color: var(--info-800);
}
.alert.info .alert-icon { color: var(--info-600); }
```

### Template Usage Examples

**ZETEM Template Examples:**
```zetem
{# Status Indicator #}
<span class="status-indicator">
  <span class="status-dot active"></span>
  {{ 'status.active' | t }}
</span>

{# Badge #}
<span class="badge success">{{ 'status.confirmed' | t }}</span>

{# Icon Box (Dashboard KPI) #}
<div class="icon-box primary">
  <i class='bx bx-user'></i>
</div>

{# Empty State #}
<div class="empty-state">
  <div class="empty-state-icon">
    <i class='bx bx-folder-open'></i>
  </div>
  <h3 class="empty-state-title">{{ 'patients.no_results' | t }}</h3>
  <p class="empty-state-description">{{ 'patients.no_results_desc' | t }}</p>
  <a href="/patients/add" class="btn btn-primary">{{ 'patients.add_new' | t }}</a>
</div>

{# Filter Chip #}
<div class="filter-chip active">
  {{ 'filters.active_only' | t }}
  <span class="filter-chip-remove">&times;</span>
</div>

{# Alert #}
<div class="alert success">
  <div class="alert-icon"><i class='bx bx-check-circle'></i></div>
  <div class="alert-content">
    <div class="alert-title">{{ 'messages.success' | t }}</div>
    <div class="alert-description">{{ 'messages.save_success' | t }}</div>
  </div>
</div>
```

### Testing Checklist

**Component Rendering:**
- [ ] All button variants render correctly
- [ ] Status indicators display with correct colors
- [ ] Icon boxes align properly
- [ ] Empty states center correctly
- [ ] Filter chips respond to hover/active states
- [ ] Alerts display with correct semantic colors

**Responsiveness:**
- [ ] Components scale properly on mobile
- [ ] Text remains readable at all sizes
- [ ] Icons remain aligned

**Accessibility:**
- [ ] Sufficient color contrast ratios
- [ ] Focus states visible
- [ ] Screen reader compatibility

---

## Phase 7: Design System - Layout & Responsive 🔲 PENDING

**Category:** Design System
**Status:** 🔲 Not started
**Estimated Time:** 4-5 hours
**Source:** CMS Artifacts `/design-system/layout.css`

### Objective
Enhance responsive layout system with mobile-first approach and app shell improvements.

### Tasks

#### 1. App Shell Enhancement

**File:** `web/css/design/layout.css`

**Update App Container:**
```css
/* App shell container */
.app-container {
  display: flex;
  min-height: 100vh;
  background: var(--slate-50);
}

/* Main content area */
.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0; /* Prevent flex overflow */
}

/* Content wrapper */
.app-content {
  flex: 1;
  padding: var(--space-6);
  max-width: var(--content-max-width);
  margin: 0 auto;
  width: 100%;
}
```

#### 2. Responsive Sidebar Navigation

**Add Mobile-Responsive Sidebar:**
```css
/* Sidebar */
.app-sidebar {
  width: var(--sidebar-width);
  background: white;
  border-right: 1px solid var(--slate-200);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 1000;
  overflow-y: auto;
  transition: transform var(--transition-base);
}

/* Sidebar header */
.sidebar-header {
  padding: var(--space-6) var(--space-4);
  border-bottom: 1px solid var(--slate-200);
}

/* Sidebar content */
.sidebar-content {
  flex: 1;
  padding: var(--space-4) 0;
  overflow-y: auto;
}

/* Mobile: Hidden by default */
@media (max-width: 768px) {
  .app-sidebar {
    transform: translateX(-100%);
  }

  .app-sidebar.is-open {
    transform: translateX(0);
  }

  .app-main {
    margin-left: 0;
  }
}

/* Desktop: Always visible */
@media (min-width: 769px) {
  .app-sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
  }

  .app-main {
    margin-left: var(--sidebar-width);
  }
}
```

#### 3. Hamburger Menu Toggle

**Add Mobile Menu Toggle:**
```css
/* Hamburger button (mobile only) */
.hamburger-toggle {
  display: none;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 0.25rem;
}

.hamburger-toggle span {
  display: block;
  width: 1.5rem;
  height: 2px;
  background: var(--slate-700);
  transition: var(--transition-fast);
}

/* Mobile: Show hamburger */
@media (max-width: 768px) {
  .hamburger-toggle {
    display: flex;
  }

  /* Animate to X when open */
  .hamburger-toggle.is-open span:nth-child(1) {
    transform: rotate(45deg) translateY(7px);
  }
  .hamburger-toggle.is-open span:nth-child(2) {
    opacity: 0;
  }
  .hamburger-toggle.is-open span:nth-child(3) {
    transform: rotate(-45deg) translateY(-7px);
  }
}
```

#### 4. Overlay Backdrop

**Add Sidebar Backdrop:**
```css
/* Backdrop overlay (mobile only) */
.sidebar-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
  opacity: 0;
  transition: opacity var(--transition-base);
}

@media (max-width: 768px) {
  .sidebar-backdrop.is-visible {
    display: block;
    opacity: 1;
  }
}
```

#### 5. Topbar Enhancement

**Add Responsive Topbar:**
```css
/* Topbar */
.app-topbar {
  height: var(--topbar-height);
  background: white;
  border-bottom: 1px solid var(--slate-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-6);
  position: sticky;
  top: 0;
  z-index: 100;
}

/* Topbar left section */
.topbar-left {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

/* Topbar right section */
.topbar-right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

/* Mobile adjustments */
@media (max-width: 768px) {
  .app-topbar {
    padding: 0 var(--space-4);
  }

  .topbar-search {
    display: none; /* Hide search on mobile, show in sidebar */
  }
}
```

#### 6. Responsive Grid System

**Add Flexible Grid:**
```css
/* Grid container */
.grid {
  display: grid;
  gap: var(--space-6);
}

/* Column variations */
.grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

/* Responsive breakpoints */
@media (max-width: 768px) {
  .grid-cols-2,
  .grid-cols-3,
  .grid-cols-4 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .grid-cols-3,
  .grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1025px) {
  .grid-cols-4 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
```

### JavaScript for Mobile Menu

**File:** `web/js/mobile-menu.js`

```javascript
// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.querySelector('.hamburger-toggle');
  const sidebar = document.querySelector('.app-sidebar');
  const backdrop = document.querySelector('.sidebar-backdrop');

  if (!hamburger || !sidebar || !backdrop) return;

  // Toggle sidebar
  hamburger.addEventListener('click', function() {
    sidebar.classList.toggle('is-open');
    backdrop.classList.toggle('is-visible');
    hamburger.classList.toggle('is-open');
    document.body.classList.toggle('sidebar-open');
  });

  // Close on backdrop click
  backdrop.addEventListener('click', function() {
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-visible');
    hamburger.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
  });

  // Close on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
      sidebar.classList.remove('is-open');
      backdrop.classList.remove('is-visible');
      hamburger.classList.remove('is-open');
      document.body.classList.remove('sidebar-open');
    }
  });
});
```

### Template Updates

**Update Page Template:**
```zetem
<div class="app-container">
  {# Sidebar #}
  <aside class="app-sidebar">
    <div class="sidebar-header">
      <h2>{{ $site_name }}</h2>
    </div>
    <nav class="sidebar-content">
      {# Navigation menu #}
    </nav>
  </aside>

  {# Sidebar backdrop (mobile) #}
  <div class="sidebar-backdrop"></div>

  {# Main content #}
  <div class="app-main">
    {# Topbar #}
    <header class="app-topbar">
      <div class="topbar-left">
        <button class="hamburger-toggle" aria-label="Toggle menu">
          <span></span>
          <span></span>
          <span></span>
        </button>
        <div class="topbar-search">
          {# Search component #}
        </div>
      </div>
      <div class="topbar-right">
        {# User menu, notifications, etc. #}
      </div>
    </header>

    {# Content area #}
    <main class="app-content">
      {% include $content_template %}
    </main>
  </div>
</div>
```

### Configuration Updates

**Add to `config/settings.info.yaml`:**
```yaml
libraries:
  design-mobile:
    css:
      - web/css/design/layout.css
    js:
      - web/js/mobile-menu.js
```

### Testing Checklist

**Mobile (< 768px):**
- [ ] Sidebar hidden by default
- [ ] Hamburger menu visible and functional
- [ ] Sidebar slides in when toggled
- [ ] Backdrop appears when sidebar open
- [ ] Clicking backdrop closes sidebar
- [ ] ESC key closes sidebar
- [ ] Content not obscured by sidebar

**Tablet (768px - 1024px):**
- [ ] Sidebar visible
- [ ] Hamburger hidden
- [ ] Grid adjusts to 2 columns
- [ ] Content spacing appropriate

**Desktop (> 1024px):**
- [ ] Sidebar sticky and always visible
- [ ] Full grid columns display
- [ ] Content max-width enforced
- [ ] Topbar sticky at top

**Accessibility:**
- [ ] Keyboard navigation works
- [ ] Focus trap in open mobile sidebar
- [ ] ARIA labels present
- [ ] Skip navigation links functional

---

## Phase 8: Design System - Medical Components & Integration 🔲 PENDING

**Category:** Design System
**Status:** 🔲 Not started
**Estimated Time:** 5-6 hours
**Source:** CMS Artifacts `/design-system/components.css`

### Objective
Add medical-specific UI components optimized for healthcare workflows.

### Tasks

#### 1. Patient List Component

**File:** `web/css/design/components.css`

**Add Patient List Item:**
```css
/* Patient list item */
.patient-item {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-4);
  background: white;
  border: 1px solid var(--slate-200);
  border-radius: var(--radius-lg);
  transition: var(--transition-fast);
  cursor: pointer;
}

.patient-item:hover {
  border-color: var(--primary-300);
  box-shadow: var(--shadow-sm);
}

/* Patient avatar */
.patient-avatar {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  background: var(--slate-200);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: var(--slate-700);
  flex-shrink: 0;
}

/* Patient info */
.patient-info {
  flex: 1;
  min-width: 0; /* Prevent text overflow */
}

.patient-name {
  font-weight: 600;
  color: var(--slate-900);
  margin-bottom: var(--space-1);
}

.patient-meta {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--text-sm);
  color: var(--slate-600);
}

.patient-meta-item {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

/* Patient actions */
.patient-actions {
  display: flex;
  gap: var(--space-2);
  opacity: 0;
  transition: opacity var(--transition-fast);
}

.patient-item:hover .patient-actions {
  opacity: 1;
}
```

#### 2. Appointment Component

**Add Appointment List Item:**
```css
/* Appointment item */
.appointment-item {
  display: flex;
  gap: var(--space-4);
  padding: var(--space-4);
  background: white;
  border: 1px solid var(--slate-200);
  border-left: 4px solid;
  border-radius: var(--radius-lg);
  transition: var(--transition-fast);
}

.appointment-item:hover {
  box-shadow: var(--shadow-md);
}

/* Color coding by type */
.appointment-item.consultation { border-left-color: var(--primary-500); }
.appointment-item.operation { border-left-color: var(--danger-500); }
.appointment-item.follow-up { border-left-color: var(--success-500); }
.appointment-item.test { border-left-color: var(--info-500); }

/* Appointment time */
.appointment-time {
  flex-shrink: 0;
  text-align: center;
  min-width: 4rem;
}

.appointment-time-hour {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--slate-900);
}

.appointment-time-period {
  font-size: var(--text-xs);
  color: var(--slate-600);
  text-transform: uppercase;
}

/* Appointment content */
.appointment-content {
  flex: 1;
}

.appointment-title {
  font-weight: 600;
  color: var(--slate-900);
  margin-bottom: var(--space-1);
}

.appointment-patient {
  font-size: var(--text-sm);
  color: var(--slate-600);
  margin-bottom: var(--space-2);
}

/* Appointment status */
.appointment-status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
}
```

#### 3. KPI Card Component

**Add Dashboard KPI Card:**
```css
/* KPI card */
.kpi-card {
  background: white;
  border: 1px solid var(--slate-200);
  border-radius: var(--radius-xl);
  padding: var(--space-6);
  transition: var(--transition-fast);
}

.kpi-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

/* KPI header */
.kpi-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}

.kpi-title {
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--slate-600);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kpi-icon {
  /* Icon box from Phase 6 */
}

/* KPI value */
.kpi-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--slate-900);
  margin-bottom: var(--space-2);
}

/* KPI trend */
.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: var(--text-sm);
  font-weight: 500;
}

.kpi-trend.up {
  color: var(--success-600);
}

.kpi-trend.down {
  color: var(--danger-600);
}

.kpi-trend-icon {
  font-size: 1rem;
}

.kpi-trend-label {
  color: var(--slate-600);
  font-weight: 400;
  margin-left: var(--space-1);
}
```

#### 4. Calendar Event Pills

**Add Event Type Pills:**
```css
/* Event pill (for calendar) */
.event-pill {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  font-weight: 500;
  line-height: 1;
  cursor: pointer;
  transition: var(--transition-fast);
}

/* Event type colors */
.event-pill.consultation {
  background: var(--primary-100);
  color: var(--primary-700);
  border-left: 3px solid var(--primary-500);
}

.event-pill.operation {
  background: var(--danger-100);
  color: var(--danger-700);
  border-left: 3px solid var(--danger-500);
}

.event-pill.follow-up {
  background: var(--success-100);
  color: var(--success-700);
  border-left: 3px solid var(--success-500);
}

.event-pill.test {
  background: var(--info-100);
  color: var(--info-700);
  border-left: 3px solid var(--info-500);
}

.event-pill:hover {
  filter: brightness(0.95);
}
```

#### 5. Quick Action Tiles

**Add Dashboard Action Tiles:**
```css
/* Quick action tile */
.action-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-6);
  background: white;
  border: 1px solid var(--slate-200);
  border-radius: var(--radius-xl);
  text-align: center;
  cursor: pointer;
  transition: var(--transition-base);
  text-decoration: none;
  color: inherit;
}

.action-tile:hover {
  border-color: var(--primary-300);
  box-shadow: var(--shadow-lg);
  transform: translateY(-4px);
}

.action-tile-icon {
  width: 4rem;
  height: 4rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--primary-100);
  color: var(--primary-600);
  border-radius: var(--radius-xl);
  font-size: 2rem;
}

.action-tile-title {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--slate-900);
}

.action-tile-description {
  font-size: var(--text-sm);
  color: var(--slate-600);
}
```

#### 6. Medical Table Enhancements

**Add Table Action Buttons:**
```css
/* Table wrapper */
.table-wrapper {
  background: white;
  border: 1px solid var(--slate-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

/* Table header with actions */
.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4);
  border-bottom: 1px solid var(--slate-200);
}

.table-title {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--slate-900);
}

.table-actions {
  display: flex;
  gap: var(--space-2);
}

/* Table content */
.table-content {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: var(--slate-50);
  border-bottom: 2px solid var(--slate-200);
}

th {
  padding: var(--space-3) var(--space-4);
  text-align: left;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--slate-700);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

td {
  padding: var(--space-4);
  border-bottom: 1px solid var(--slate-100);
  font-size: var(--text-sm);
  color: var(--slate-900);
}

tbody tr:hover {
  background: var(--slate-50);
}

/* Action column */
.table-row-actions {
  display: flex;
  gap: var(--space-2);
  opacity: 0;
  transition: opacity var(--transition-fast);
}

tbody tr:hover .table-row-actions {
  opacity: 1;
}

.table-action-btn {
  padding: var(--space-2);
  background: transparent;
  border: 1px solid var(--slate-300);
  border-radius: var(--radius-md);
  color: var(--slate-600);
  cursor: pointer;
  transition: var(--transition-fast);
}

.table-action-btn:hover {
  background: var(--slate-100);
  border-color: var(--slate-400);
  color: var(--slate-900);
}
```

### Template Usage Examples

**Patient List:**
```zetem
<div class="patient-item">
  <div class="patient-avatar">{{ $patient->getInitials() }}</div>
  <div class="patient-info">
    <div class="patient-name">{{ $patient->getFullName() }}</div>
    <div class="patient-meta">
      <span class="patient-meta-item">
        <i class='bx bx-calendar'></i>
        {{ $patient->getAge() }} {{ 'years' | t }}
      </span>
      <span class="patient-meta-item">
        <i class='bx bx-phone'></i>
        {{ $patient->getPhone() }}
      </span>
      <span class="status-indicator">
        <span class="status-dot active"></span>
        {{ 'status.active' | t }}
      </span>
    </div>
  </div>
  <div class="patient-actions">
    <button class="btn-icon btn-ghost" title="{{ 'actions.view' | t }}">
      <i class='bx bx-show'></i>
    </button>
    <button class="btn-icon btn-ghost" title="{{ 'actions.edit' | t }}">
      <i class='bx bx-edit'></i>
    </button>
  </div>
</div>
```

**KPI Card:**
```zetem
<div class="kpi-card">
  <div class="kpi-header">
    <span class="kpi-title">{{ 'dashboard.total_patients' | t }}</span>
    <div class="icon-box primary">
      <i class='bx bx-user'></i>
    </div>
  </div>
  <div class="kpi-value">{{ $totalPatients }}</div>
  <div class="kpi-trend up">
    <i class='bx bx-trending-up kpi-trend-icon'></i>
    <span>+12%</span>
    <span class="kpi-trend-label">{{ 'dashboard.vs_last_month' | t }}</span>
  </div>
</div>
```

**Appointment Item:**
```zetem
<div class="appointment-item consultation">
  <div class="appointment-time">
    <div class="appointment-time-hour">{{ $appointment->getTime() }}</div>
    <div class="appointment-time-period">{{ $appointment->getPeriod() }}</div>
  </div>
  <div class="appointment-content">
    <div class="appointment-title">{{ $appointment->getType() | t }}</div>
    <div class="appointment-patient">{{ $appointment->getPatientName() }}</div>
    <div class="appointment-status">
      <span class="status-dot active"></span>
      {{ $appointment->getStatus() | t }}
    </div>
  </div>
</div>
```

### Testing & Integration Checklist

**Component Testing:**
- [ ] Patient list items render correctly
- [ ] Appointment items display with correct type colors
- [ ] KPI cards show trends accurately
- [ ] Event pills display in calendar
- [ ] Quick action tiles are clickable
- [ ] Table actions appear on hover

**Integration Testing:**
- [ ] Components work with existing ZPMS data
- [ ] Templates compile without errors
- [ ] ZETEM filters work in components
- [ ] Boxicons icons display correctly
- [ ] Hover states work on all interactive elements

**Cross-Browser Testing:**
- [ ] Chrome - all components
- [ ] Firefox - all components
- [ ] Safari - all components
- [ ] Edge - all components

**Performance:**
- [ ] CSS file size acceptable
- [ ] No layout shifts
- [ ] Smooth transitions
- [ ] Fast initial render

**Final Integration:**
- [ ] All design system phases complete (5-8)
- [ ] Visual consistency across all pages
- [ ] Medical teal theme applied throughout
- [ ] System fonts retained (no custom fonts)
- [ ] Responsive design functional on all breakpoints
- [ ] Accessibility standards met

---

## Phase 9: Translation - Enhanced Language Switcher 🔲 PENDING

**Category:** Translation System
**Status:** 🔲 Not started
**Estimated Time:** 2-3 hours

### Objective
Improve language switching with query parameter support and page state preservation.

### Tasks

**1. Add Query Parameter Detection**

Update `fw/core/lib/LanguageDetector.php` to detect `?lang=en` parameter and set session/cookie.

**2. Update Language Selector Module**

Update `fw/core/modules/language_selector/`:
- Add query parameter mode
- Preserve current page path on switch
- Configuration for AJAX vs reload mode

**3. Add Helper Functions**

Add to `fw/core/kernel/utils.php`:
```php
function get_current_url_with_lang($lang) {
    // Return current URL with ?lang= parameter
}
```

**4. Configuration**

Add to `config/settings.info.yaml`:
```yaml
language_switcher:
  mode: ajax              # ajax or query_param
  preserve_page: true
  show_in_header: true
```

---

## Phase 10: Translation - Management Interface 🔲 PENDING

**Category:** Translation System
**Status:** 🔲 Not started
**Estimated Time:** 8-10 hours

### Objective
Create admin module for managing translations via web interface.

### Tasks

**1. Create Module**

Create `web/modules/translation_admin/`:
- `translation_admin.info.yaml`
- `translation_admin.php`
- `translation_admin.zetem`
- `translation_admin.css`

**2. Features**

- List all dictionary entries with search/filter
- Inline editing of translations
- Bulk import/export (YAML, CSV)
- Translation statistics dashboard
- Missing translation reports
- Translation history/versioning

**3. Add Routes**

```yaml
routes:
  translations_list:
    title: Translations
    url: /admin/translations
    handler: translation_admin_list
    access: administer_translations
```

**4. Add Permissions**

```yaml
permissions:
  administer_translations:
    title: Administer Translations
    roles:
      - administrator
```

---

## Phase 11: Translation - SEO & Metadata 🔲 PENDING

**Category:** Translation System
**Status:** 🔲 Not started
**Estimated Time:** 2-3 hours

### Objective
Add proper SEO tags for multilingual content.

### Tasks

**1. Create SEO Helper**

Create `fw/core/lib/SEOHelper.php`:
- `generateHreflangTags()` - Alternate language links
- `generateCanonicalTag()` - Canonical URL
- `getLanguageMetadata()` - Language-specific meta tags

**2. Update Page Rendering**

Update `fw/core/kernel/Kernel.php`:
- Inject hreflang tags in page head
- Add canonical URL
- Add Open Graph locale tags

**3. Template Variables**

```zetem
<html lang="{{ $current_language }}">
<head>
  <link rel="canonical" href="{{ $canonical_url }}">
  {% for $lang in $alternate_languages %}
  <link rel="alternate" hreflang="{{ $lang['code'] }}" href="{{ $lang['url'] }}">
  {% endfor %}
</head>
```

---

## Phase 12: Translation - Content Translation 🔲 PENDING

**Category:** Translation System
**Status:** 🔲 Not started
**Estimated Time:** 10-12 hours
**Priority:** Low (Future Enhancement)

### Objective
Support language-specific content variants (not just UI translations).

### Tasks

**1. Add Translation Tables**

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

**2. Extend Entity Classes**

Add to `web/ClassesEx.php`:
- `getTranslation($lang)` method
- Automatically load translated fields
- Fallback to default language if missing

**3. Admin Interface**

- Add translation tabs to entity edit forms
- Show translation status indicators
- Quick translate buttons

---

## Estimated Implementation Time Summary

| Phase | Category | Time | Status |
|-------|----------|------|--------|
| Phase 1 | Translation | 4-6 hours | ✅ Complete |
| Phase 2 | Translation | 6-8 hours | ✅ Complete |
| Phase 3 | Translation | 2-3 hours | ✅ Complete |
| Phase 4 | Translation | 4-5 hours | ✅ Complete |
| **Phase 5** | **Design** | **~2.5 hours** | ✅ Complete |
| **Phase 6** | **Design** | **4-5 hours** | 🔲 Pending |
| **Phase 7** | **Design** | **4-5 hours** | 🔲 Pending |
| **Phase 8** | **Design** | **5-6 hours** | 🔲 Pending |
| Phase 9 | Translation | 2-3 hours | 🔲 Pending |
| Phase 10 | Translation | 8-10 hours | 🔲 Pending |
| Phase 11 | Translation | 2-3 hours | 🔲 Pending |
| Phase 12 | Translation | 10-12 hours | 🔲 Pending |

**Completed:** ~20 hours
**Design System (Next):** ~16 hours
**Translation Enhancements:** ~24 hours
**Total Project:** ~60 hours

---

## Key Design Decisions

### Design System

**✅ Use Medical Teal Color Scheme**
- Rationale: Healthcare-appropriate, professional
- Primary color: `#0d9488` (teal-600)
- Replaces existing blue (`#2196f3`)

**✅ Use Comprehensive Token System**
- Rationale: Better scalability, consistency
- 10 spacing values (8px base)
- 50-900 semantic color scales
- 6 shadow values + focus shadow

**❌ DO NOT Use Custom Web Fonts**
- Rationale: External dependencies, performance, GDPR
- Keep system fonts (SF Pro, Segoe UI, Roboto)
- No Google Fonts imports

**✅ Use Medical-Specific Components**
- Patient list items
- Appointment components
- KPI cards with trend indicators
- Event pills for calendar
- Status indicators

### Translation System

**✅ Hybrid File + Database Approach**
- File-based for static UI strings
- Database for dynamic content
- Configurable preference order

**✅ URL-Based Language Detection**
- `/en/page` and `/el/page` support
- Pure PHP implementation (no .htaccess)
- Backward compatible with existing URLs

**✅ ZETEM Template Filters**
- Modern syntax: `{{ 'key' | t }}`
- Backward compatible: `{{ getLangText($tok) }}`
- Support for parameters, pluralization, context

---

## Testing Strategy

### Phase Completion Criteria

Each phase must pass:
1. **Unit Tests** - Core functionality works
2. **Integration Tests** - Works with existing system
3. **Visual Tests** - No regressions
4. **Performance Tests** - No degradation
5. **Accessibility Tests** - WCAG 2.1 compliance

### Cross-Browser Testing Matrix

| Browser | Desktop | Mobile |
|---------|---------|--------|
| Chrome | ✓ | ✓ |
| Firefox | ✓ | - |
| Safari | ✓ | ✓ |
| Edge | ✓ | - |

### Accessibility Requirements

- [ ] WCAG 2.1 Level AA compliance
- [ ] Keyboard navigation functional
- [ ] Screen reader compatible
- [ ] Sufficient color contrast (4.5:1 minimum)
- [ ] Focus indicators visible

---

## Migration Path

### For Existing ZPMS Installations

**Step 1: Design System (Phases 5-8)**
1. Backup current CSS files
2. Implement Phase 5 (tokens)
3. Test all pages for visual regressions
4. Implement Phase 6 (components)
5. Update templates to use new components
6. Implement Phase 7 (layout)
7. Test responsive design on all breakpoints
8. Implement Phase 8 (medical components)
9. Final integration testing

**Step 2: Translation Enhancements (Phases 9-12)**
1. Implement Phase 9 (language switcher)
2. Test language switching across all pages
3. Implement Phase 10 (admin interface)
4. Train users on translation management
5. Implement Phase 11 (SEO)
6. Verify hreflang tags in production
7. Phase 12 (content translation) - defer to future

---

## Configuration Reference

### Design System Configuration

**Add to `config/settings.info.yaml`:**

```yaml
design_system:
  theme: medical-teal
  use_custom_fonts: false  # Keep system fonts
  sidebar_width: 280px
  topbar_height: 64px
  content_max_width: 1400px

libraries:
  design-system:
    css:
      - web/css/design/design-system.css
      - web/css/design/layout.css
      - web/css/design/components.css
    js:
      - web/js/mobile-menu.js
```

### Translation Configuration

**Current in `config/settings.info.yaml`:**

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
    - user
    - query
    - browser
    - default

dictionary:
  auto_register: true
  fallback_to_file: true
  prefer_database: false

language_switcher:
  mode: ajax
  preserve_page: true
  show_in_header: true
```

---

## Next Steps

**Immediate Priority: Phase 5 (Design System Tokens)**

1. Read current `web/css/design/design-system.css`
2. Backup current file
3. Replace color palette with medical teal
4. Replace gray with slate
5. Add enhanced semantic colors
6. Replace spacing system with 10 values
7. Add enhanced shadows
8. Add enhanced radius values
9. Add transition speeds
10. Add layout variables
11. Test on dashboard page

**After Phase 5 completion, proceed sequentially through Phases 6-8.**

---

## Critical Files Reference

### Design System Files (To Create/Update)

**To Update:**
- `web/css/design/design-system.css` - Token system
- `web/css/design/layout.css` - Layout and responsive
- `web/css/design/components.css` - Component library

**To Create:**
- `web/js/mobile-menu.js` - Mobile navigation toggle

### Translation System Files (Already Created)

**Framework:**
- `fw/core/lib/LanguageDetector.php`
- `fw/core/lib/MultilingualManager.php`
- `fw/core/filters/TranslationFilters.php`
- `fw/core/dictionaryClassEx.php`

**Application:**
- `config/translations/en.yaml`
- `config/translations/el.yaml`
- `web/templates/examples/translation-filters-examples.zetem`

**Documentation:**
- `docs/URL_LANGUAGE_DETECTION.md`

---

## Support & Documentation

**For questions or issues:**
- Review `docs/URL_LANGUAGE_DETECTION.md` for translation system
- Check ZETEM template examples in `web/templates/examples/`
- Review CMS artifact source files in `/home/evrokas/Downloads/pms/claude2/consolidated files/cms-artifacts/`
- Consult CLAUDE.md for project architecture

**Version Control:**
- Commit after each phase completion
- Tag releases: `v1.0-phase-5`, `v1.0-phase-6`, etc.
- Document breaking changes in commit messages

---

**Plan Status:** Phase 5 Complete — Ready for Phase 6
**Next Action:** Begin Phase 6 - Core Component Library
**Estimated Completion:** Phase 8 by end of February 2026
