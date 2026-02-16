# Zeus Patient Management System (ZPMS) — Comprehensive Documentation

**Version:** February 2026
**Stack:** PHP 8.x, MySQL/MariaDB, Zeus Framework (zeusfw), ZETEM templates, Boxicons
**Repository:** `/var/www/html/apps/zpms`
**Framework:** `/var/www/html/apps/zeusfw` (symlinked at `fw/`)

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Architecture](#2-architecture)
3. [Zeus Framework Deep Dive](#3-zeus-framework-deep-dive)
4. [ZPMS Application Layer](#4-zpms-application-layer)
5. [Database & Entity System](#5-database--entity-system)
6. [Routing & Request Flow](#6-routing--request-flow)
7. [Module System](#7-module-system)
8. [ZETEM Template Engine](#8-zetem-template-engine)
9. [Security & Authentication](#9-security--authentication)
10. [Multilingual System](#10-multilingual-system)
11. [File Management](#11-file-management)
12. [Design System & Frontend](#12-design-system--frontend)
13. [Application Features](#13-application-features)
14. [Configuration Reference](#14-configuration-reference)
15. [Developer Guide](#15-developer-guide)
16. [Codebase Statistics](#16-codebase-statistics)

---

## 1. System Overview

ZPMS is a patient management system for medical practices in Greece. It manages patients, appointments, surgical operations, billing, PDF document archives, and supports multi-language (Greek/English) interfaces. The application is built on the Zeus Framework — a custom PHP MVC-style CMS framework with no external PHP dependencies (no Composer).

### What ZPMS Does

- **Patient Records** — CRUD with search, sorting by name or last appointment
- **Appointments** — Booking, editing, soft-delete with recovery, linked to patients and locations
- **Operations** — Surgical procedure tracking with diagnoses, surgeons, clinics
- **Billing** — Invoice management, payment tracking, PDF storage
- **PDF Archive** — Upload, parse, index, and search PDF documents (invoices, reports)
- **Backup** — Database backup creation and management
- **QR Code Generator** — Generate QR codes from text input
- **Translation Management** — Admin dashboard for bilingual content management
- **TOTP Security** — Two-factor authentication setup
- **File Management** — Stream-based file storage with usage tracking

### Technology Choices

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend | PHP 8.x (vanilla) | No build system, direct execution |
| Database | MySQL/MariaDB (PDO) | Standard, reliable RDBMS |
| Templates | ZETEM (custom) | Twig-like syntax, compiled + cached |
| Frontend | Vanilla JS + CSS | No framework overhead |
| Icons | Boxicons | Lightweight, medical-friendly |
| Date Picker | Pikaday (CDN) | Lightweight, no jQuery dependency |
| Server | Apache + .htaccess | URL rewriting, PHP module |

---

## 2. Architecture

### Directory Structure

```
zpms/
├── config/
│   ├── db.php                    # Database credentials (from db.php.in)
│   ├── db.php.in                 # DB config template
│   ├── settings.info.yaml        # Master config (routes, roles, menus, modules, libraries)
│   └── site.info.yaml            # Site metadata (title, timezone, cache)
│
├── fw/ → /var/www/html/apps/zeusfw/   # Framework symlink
│   ├── bootstrap.php             # Framework bootstrap (defines __FWDIR__, loads core)
│   ├── core/
│   │   ├── kernel/
│   │   │   ├── Kernel.php        # Application kernel (34KB)
│   │   │   ├── utils.php         # Global helper functions (22KB)
│   │   │   └── maintenance.php   # Periodic maintenance tasks
│   │   ├── router/
│   │   │   ├── Router.php        # URL router (11.7KB)
│   │   │   ├── Request.php       # HTTP request parser
│   │   │   └── ErrorHandlers.php # Error response handlers
│   │   ├── db/
│   │   │   └── dbal.php          # Database abstraction (13.8KB)
│   │   │                         #   dbConnection, dbQuery, dbAbstractEntityClass
│   │   ├── templates/
│   │   │   ├── ZETEMTemplate.php # Template engine + compiler (33KB)
│   │   │   └── TemplateFilter.php# 50+ template filters
│   │   ├── lib/
│   │   │   ├── Security.php      # RBAC system (3.9KB)
│   │   │   ├── Modules.php       # Module loader (2.6KB)
│   │   │   ├── FileManager.php   # Stream-based file management (15KB)
│   │   │   ├── MultilingualManager.php  # Translation system (14.9KB)
│   │   │   ├── LanguageDetector.php     # Language detection (13.7KB)
│   │   │   ├── SEOHelper.php     # SEO tag generation (9.7KB)
│   │   │   ├── Feeder.php        # Data pipeline/feed system (18KB)
│   │   │   ├── Attributes.php    # HTML attribute builder (2.3KB)
│   │   │   ├── ContentPage.php   # Static content pages (3.1KB)
│   │   │   ├── WebForms.php      # Dynamic form system (8.2KB)
│   │   │   ├── UserLogin.php     # Authentication helpers (3.4KB)
│   │   │   ├── Menutrail.php     # Menu navigation tracking
│   │   │   ├── Routetrail.php    # Route history
│   │   │   ├── FormElement.php   # Form field definitions
│   │   │   └── Log.php           # Logging utilities
│   │   ├── render/
│   │   │   ├── RenderArrayManager.php   # Render array processor (12.6KB)
│   │   │   ├── RenderElementTypes.php   # Element type handlers (13.5KB)
│   │   │   ├── RenderHelpers.php        # Builder helpers (8KB)
│   │   │   └── RenderAccess.php         # Access control (4.2KB)
│   │   └── modules/              # Core modules (9 built-in)
│   │       ├── mainnavigation/
│   │       ├── notifications/
│   │       ├── header/
│   │       ├── breadcrumbs/
│   │       ├── userblock/
│   │       ├── htmltext/
│   │       ├── content/
│   │       ├── language_selector/
│   │       ├── message/
│   │       └── admin/
│   └── zeusfw.info.yaml          # Framework config
│
├── web/
│   ├── index.php                 # Entry point + all route handlers (1,177 lines)
│   ├── .htaccess                 # Apache URL rewriting
│   ├── ClassesEx.php             # Entity class extensions (192 lines)
│   ├── locationsClassEx.php      # Location entity extensions (110 lines)
│   ├── user_classes.php          # Bootstrap for auto-generated classes
│   │
│   ├── classes/
│   │   ├── yaml/                 # Entity schema definitions
│   │   │   ├── patients.yaml
│   │   │   ├── appointments.yaml
│   │   │   ├── operations.yaml
│   │   │   ├── billing.yaml
│   │   │   ├── locations.yaml
│   │   │   ├── clinics.yaml
│   │   │   ├── doctors.yaml
│   │   │   ├── files.yaml
│   │   │   ├── file_usage.yaml
│   │   │   ├── pdflib_files.yaml
│   │   │   ├── procedure_info.yaml
│   │   │   └── invoicestatus.yaml
│   │   ├── *.php                 # Auto-generated entity classes (13 files)
│   │   └── bootstrap_classes.php # Class autoloader
│   │
│   ├── modules/
│   │   ├── topbar/               # Top navigation bar
│   │   ├── location/             # Location/clinic selector
│   │   ├── datepicker/           # Date picker widget
│   │   ├── pdflib/               # PDF document archive
│   │   ├── backup/               # Database backup manager
│   │   ├── translation_admin/    # Translation admin module
│   │   └── userprofile/          # User profile module
│   │
│   ├── templates/
│   │   ├── page/
│   │   │   └── page.zetem        # Main page layout wrapper
│   │   ├── content/              # Page content templates (11 files)
│   │   ├── blocks/               # Reusable block templates (6 files)
│   │   ├── modules/              # Module templates (4 files)
│   │   ├── menu/                 # Menu templates
│   │   ├── nav/                  # Navigation templates
│   │   ├── apps/                 # Application-specific templates
│   │   └── webforms/             # Dynamic webform templates
│   │
│   ├── css/
│   │   ├── design/               # Design system (3 files)
│   │   │   ├── design-system.css # CSS variables & tokens
│   │   │   ├── layout.css        # Grid & layout utilities
│   │   │   └── components.css    # Reusable component styles
│   │   ├── normalize.css         # CSS reset
│   │   ├── styles.css            # Main application styles (25KB)
│   │   ├── navigation.css        # Navigation base
│   │   ├── color-palette.css     # Color definitions
│   │   ├── navigation-topbar-*.css    # Top nav styles
│   │   ├── navigation-sidebar-*.css   # Sidebar styles
│   │   ├── bx/                   # Boxicons icon library
│   │   └── *.css                 # Feature-specific styles
│   │
│   ├── js/
│   │   ├── scripts.js            # Main application JS (11KB)
│   │   ├── mobile-menu.js        # Mobile navigation
│   │   ├── loader.js             # Loading spinner
│   │   ├── pdf-preview.js        # PDF preview
│   │   ├── render-ajax-handler.js# AJAX form handling
│   │   ├── textarea-autoexapand.js# Auto-expanding textareas
│   │   └── finance.js            # Finance calculations
│   │
│   ├── assets/                   # Static assets (logo, favicon)
│   ├── cache/                    # Compiled template cache
│   └── test/                     # Manual test pages
│
├── sql/
│   ├── msqldump.sh               # Database backup script
│   ├── msql.sh                   # Database restore script
│   └── *.sql                     # Table creation scripts
│
├── data/                         # Runtime data storage
├── files/                        # Managed file storage (public/private/temp/cache)
├── CLAUDE.md                     # AI assistant instructions
└── fw/plans/                     # Architecture plans
```

### Request Flow

```
Browser Request
    │
    ▼
Apache (.htaccess) ─── URL rewriting ──→ web/index.php
    │
    ▼
┌─────────────────────────────────────────────────┐
│  BOOTSTRAP PHASE                                │
│                                                 │
│  1. Load config/db.php (DB credentials)         │
│  2. Require fw/bootstrap.php                    │
│     ├─ Define __FWDIR__, __APPDIR__             │
│     ├─ Load core classes (Kernel, Router, etc.) │
│     └─ Load user entity classes                 │
│  3. Create Kernel (loads YAML configs)          │
│  4. Init SecurityClass with roles               │
│  5. Create RouterClass from routes config       │
│  6. Init Renderer (template engine)             │
│  7. Register translation filters                │
│  8. Create RequestClass from $_SERVER           │
│  9. Start session                               │
│ 10. Detect language from URL prefix             │
│ 11. Check authentication (session/cookie)       │
│ 12. Set default location                        │
│ 13. Register all modules                        │
└─────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────┐
│  ROUTING PHASE                                  │
│                                                 │
│  1. RouterClass::matchRoute($Request)           │
│     ├─ Tokenize URL path                        │
│     ├─ Compare against all routes               │
│     ├─ Score match quality (best wins)          │
│     ├─ Extract {param} values                   │
│     └─ Check HTTP method match                  │
│  2. Store match in $_SESSION['route_match']     │
└─────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────┐
│  HANDLER PHASE                                  │
│                                                 │
│  RouterClass::routerCallFunction($match)        │
│  ├─ Check access permission                     │
│  │   └─ 401 if unauthorized                     │
│  ├─ Record analytics                            │
│  ├─ Call handler function with $params           │
│  │   ├─ SecurityClass::require('permission')    │
│  │   ├─ Fetch data from entity classes          │
│  │   └─ Return Renderer::render() output        │
│  └─ 404 if no match                             │
└─────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────┐
│  RENDER PHASE                                   │
│                                                 │
│  Kernel::renderPage()                           │
│  ├─ Render each region (header, nav, content,   │
│  │   notification, footer)                      │
│  │   ├─ For each block in region structure:     │
│  │   │   ├─ If module → call module->render()   │
│  │   │   ├─ If layout → recursive renderBlock() │
│  │   │   └─ Wrap in template suggestion system  │
│  │   └─ Assemble region HTML                    │
│  ├─ Inject CSS <link> tags into <head>          │
│  ├─ Inject JS <script> tags into <head>/<foot>  │
│  ├─ Generate SEO meta tags                      │
│  ├─ Render page.zetem with all regions          │
│  └─ Output final HTML                           │
└─────────────────────────────────────────────────┘
    │
    ▼
Browser receives HTML
```

---

## 3. Zeus Framework Deep Dive

### 3.1 Kernel (`fw/core/kernel/Kernel.php`)

The Kernel is the central coordinator. Key responsibilities:

**Configuration Management:**
- Loads framework config (`zeusfw.info.yaml`)
- Loads site config (`site.info.yaml`)
- Loads project config (`settings.info.yaml`)
- Merges configs recursively with `addConfig()`
- Modules can add their own config (merged at boot)

**Key Methods:**
```php
$kernel = new Kernel($_SERVER, "../config");

// Configuration
$kernel->getConfig('routes');           // Get config section
$kernel->safeGetConfig('key');          // Get with null fallback
$kernel->addConfig($yaml_array);        // Merge additional config

// User/Auth
$kernel->loginUser($uname, $roles);     // Start authenticated session
$kernel->logoutUser();                  // Destroy session
$kernel->isUserLoggedin();              // Check auth (session + cookie)
$kernel->getUserName();                 // Current username
$kernel->getUserRoles();                // Current user roles

// Modules
$kernel->registerModule($module);       // Register module instance
$kernel->getModule('name');             // Retrieve registered module
$kernel->renderModule($module);         // Execute module render

// Language
$kernel->setCurrentLanguage('en');      // Set language
$kernel->getCurrentLanguage();          // Get current language code
$kernel->getSupportedLanguages();       // Get all languages

// Status Messages
$kernel->addStatus('notice', 'Saved');  // Queue message (notice/warning/error)
$kernel->getStatus('notice');           // Retrieve messages

// Paths
$kernel->getrootpath();                 // HTTP root path
$kernel->getbasepath();                 // Filesystem base path

// Rendering
$kernel->renderPage();                  // Assemble and output final page
```

### 3.2 Router (`fw/core/router/Router.php`)

Pattern-matching URL router with parameter extraction.

**Route Matching Algorithm:**
1. Tokenize the request URL by `/`
2. For each defined route, compare tokens
3. Exact token matches score higher than `{param}` matches
4. Best-scoring route wins (handles ambiguity)
5. Extract `{param}` values into `$params` array

**HTTP Method Handling:**
- Routes declare `method: get` or `method: post`
- A route without `method` matches any method
- GET and POST routes can share the same URL (e.g., `/patient/{id}/edit`)

**Key Methods:**
```php
$router = new RouterClass($kernel->getConfig('routes'));

$match = $router->matchRoute($Request);    // Find matching route
$router->routerCallFunction($match);       // Execute handler
$router->getRoute('route_name');           // Get route definition
$router->getAllRoutes();                   // Get all routes
$router->initRouteTable($routes);         // Reload routes (used by modules)
```

### 3.3 Database Layer (`fw/core/db/dbal.php`)

Three-tier database abstraction:

**Tier 1: dbConnection — PDO Singleton**
```php
dbConnection::init($host, $user, $pass, $db);
$pdo = dbConnection::getConnection();

// Direct PDO usage
$st = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$st->execute([$id]);
$row = $st->fetch();
```

**Tier 2: dbQuery — Fluent Query Builder**
```php
$query = new dbQuery($pdo, 'patients', 'patientsClass');

$results = $query->table('patients')
    ->where('deleted', 'IS', 'NULL')
    ->whereLike('pname', '%smith%')
    ->whereIn('status', ['active', 'pending'])
    ->whereJsonValue('metadata', '$.role', 'doctor')
    ->orderBy('pname', 'ASC')
    ->limit(20, 0)
    ->get();       // Returns associative arrays

$objects = $query->cget();  // Returns entity class instances
```

**Tier 3: dbAbstractEntityClass — ORM Base**
```php
// Auto-generated from YAML schema:
class patientsClass extends dbAbstractEntityClass {
    // Auto-generated methods:
    static function sgetById(int $aid);           // Get by primary key
    static function sgetAll($where, $limit);      // Get all/filtered
    function loadFields($data);                   // Populate from array
    function getpname();                          // Getter
    function setpname($val);                      // Setter
    function insert();                            // INSERT INTO
    function update();                            // UPDATE SET
    function delete();                            // DELETE FROM
    static function table();                      // Return table name
}
```

### 3.4 Global Utility Functions (`fw/core/kernel/utils.php`)

```php
// URL helpers
rel_url('/patients');                     // → '/zpms/patients'
asset('logo.png');                        // → '/zpms/assets/logo.png'

// Translation
t('Search');                              // Translate token
t('Welcome {name}', ['name' => 'John']); // With parameters
t_plural('item', 5);                     // Pluralization
getLangText(['en' => 'Home', 'gr' => 'Αρχική']); // Language array

// File management
file_save_upload($_FILES['doc'], 'private://reports/scan.pdf', 'patient', '42', 'attachment');
file_url('public://docs/file.pdf');       // Get web URL for file
file_usage_count('public://shared.pdf');  // Count references
file_delete_if_unused('private://old.pdf'); // Delete if no references

// Misc
guid();                                   // Generate UUID v4
getDBtime();                              // Current timestamp for DB
formatDate('2026-01-15');                 // Localized date format
echopre($variable);                      // Debug output with source line
randomAlpha(16);                          // Random alphabetic string
attach_library('pikaday-library');        // Load CSS/JS bundle
attributes(new Attributes([...]));       // Generate HTML attributes
```

---

## 4. ZPMS Application Layer

### 4.1 Entry Point (`web/index.php`)

The single entry point containing all initialization and 30+ route handler functions:

**Initialization Block (lines 1–100):**
```php
include_once(__DIR__ . "/../config/db.php");
require_once(__FWDIR__ . '/bootstrap.php');

$kernel = new Kernel($_SERVER, "../config");
SecurityClass::init($kernel->getConfig('roles'));
$router = new RouterClass($kernel->getConfig('routes'));
Renderer::init($kernel->getConfig('templates'), false, ...);

// Language detection from URL prefix
$languageDetector = $kernel->getLanguageDetector();
// ... extract /en/ or /el/ prefix and rewrite REQUEST_URI

$kernel->isUserLoggedin();
session_start();
ob_start();
locationsClassEx::setDefaultLocation();
registerModules();

$match = $router->matchRoute($Request);
$content_response = $router->routerCallFunction($match);
$kernel->renderPage();
ob_end_flush();
```

**Handler Functions (lines 100+):**

Each handler follows this pattern:
```php
function handler_name($params) {
    // 1. Check permissions
    if ($ret = SecurityClass::require('permission')) return $ret;

    // 2. Extract route parameters
    $id = $params['id'] ?? null;

    // 3. Fetch data using entity classes
    $data = EntityClass::sgetById($id);

    // 4. Process business logic

    // 5. Return rendered template
    return Renderer::render('template.zetem', ['data' => $data]);
}
```

### 4.2 Complete Route Handler Reference

| Handler | URL | Method | Permission | Purpose |
|---------|-----|--------|------------|---------|
| `homepage` | `/` | GET | — | Dashboard (logged in) or login prompt |
| `login` | `/login` | GET | — | Login form |
| `login_post` | `/login` | POST | — | Process login |
| `logout` | `/logout` | GET | — | Destroy session |
| `patients_list` | `/patients`, `/patients/sort/{key}`, `/patients/sort/{key}/{order}` | GET | authenticated user | Patient list with sorting |
| `patients_search_post` | `/patients/search` | POST | — | Search form redirect |
| `patients_list_search` | `/patients/search/{term}` | GET | authenticated user | Search results page |
| `patients_list_search_ajax` | `/patients/searchajax/{term}` | GET | authenticated user | AJAX search (JSON) |
| `patient_edit` | `/patient/{id}/edit` | GET | patients-edit-patient | Edit form |
| `patient_edit_post` | `/patient/{id}/edit` | POST | patients-edit-patient | Save changes |
| `patient_new` | `/patient/new` | GET | patients-new-patient | New form |
| `patient_new_post` | `/patient/new` | POST | patients-new-patient | Create record |
| `patient_delete` | `/patient/{id}/delete` | GET | patients-delete-patient | Soft-delete |
| `appointments_list` | `/appointments` | GET | — | All appointments |
| `appointment_edit` | `/appointment/{id}/edit` | GET | appointment-edit | Edit form |
| `appointment_edit_post` | `/appointment/{id}/edit` | POST | appointment-edit | Save changes |
| `appointment_new` | `/appointment/new` | GET | — | New appointment form |
| `appointment_new_post` | `/appointment/new` | POST | — | Create appointment |
| `appointment_delete` | `/appointment/{id}/delete` | GET | — | Soft-delete |
| `appointment_recover` | `/appointment/{id}/recover` | GET | — | Un-delete |
| `patient_appointment_new` | `/appointment/{id}/newappointment` | GET | — | New appointment for patient |
| `patient_appointment_new_post` | `/appointment/{id}/newappointment` | POST | — | Create |
| `settings` | `/settings` | GET | — | Settings page |
| `ajax_update_patient_info` | `/patient/{id}/ajax` | POST | — | AJAX patient update |
| `app_generate_qr` | `/apps/genqr` | GET/POST | — | QR code tool |
| `clinics_edit` | `/apps/edit_clinics` | GET | — | Clinic management |
| `totp_handler` | `/totp/{action}` | GET | — | 2FA management |
| `handle_private_file` | `/files/get/{path}` | GET | authenticated | Private file download |
| `handle_translations_admin` | `/admin/translations` | GET | administer_translations | Translation dashboard |
| `handle_translations_list` | `/admin/translations/list` | GET | administer_translations | AJAX token list |
| `handle_translations_update` | `/admin/translations/update` | POST | administer_translations | AJAX update |
| `handle_translations_export` | `/admin/translations/export` | GET | administer_translations | Export CSV/YAML |
| `handle_translations_import` | `/admin/translations/import` | POST | administer_translations | Import CSV/YAML |

**Module-owned routes** (defined in module YAML files):
| Handler | URL | Module | Purpose |
|---------|-----|--------|---------|
| `pdflib_handler` | `/apps/pdflib` | pdflib | PDF archive page |
| `pdflib_process` | `/apps/pdflib-process` | pdflib | Upload & process PDF |
| `pdflib_action_delete` | `/apps/pdflib/{id}/delete` | pdflib | Delete PDF |
| `pdflib_action_download` | `/apps/pdflib/{id}/download` | pdflib | Download PDF |
| `updatelocation` | `/updatelocation` | location | AJAX location change |
| `backup_handler` | `/apps/backup` | backup | Backup manager page |

---

## 5. Database & Entity System

### 5.1 YAML Schema → Auto-Generated Classes

Entity schemas live in `web/classes/yaml/{table}.yaml`. A framework tool reads these and generates PHP entity classes in `web/classes/{table}.php`.

**Schema Syntax:**
```yaml
---
table:
  name: patients                              # MySQL table name
  class: patientsClass                        # PHP class name
  extention: __APPDIR__ . '/web/ClassesEx.php' # Optional extension file

  fields:
  - name: guid
    type: '@guid'          # Special: auto-generated UUID on insert
  - name: cdate
    type: '@cdate'         # Special: auto-set creation timestamp
  - name: cuser
    type: '@cuser'         # Special: auto-set creating user
  - name: pname
    type: varchar(64)
  - name: pdob
    type: datetime
  - name: deleted
    type: datetime
    default: null

form:                       # Optional: form definition for auto-rendering
  name: patients
  method: post
  action: "#"
  inputs:
    - name: pname
      label: "Name"
      type: text
      required: true
    - name: clinic
      label: "Clinic"
      type: select
      options:
        key1: "Value 1"
        key2: "Value 2"
  buttons:
    - type: submit
      label: "Submit"
...
```

**Special Field Types:**
| Type | Behavior |
|------|----------|
| `@guid` | Auto-generates UUID on insert |
| `@cdate` | Auto-sets `CURRENT_TIMESTAMP` on insert |
| `@cuser` | Auto-sets current username on insert |

### 5.2 Entity Classes

**All 12 entities:**

| Entity | Table | Key Fields | Relationships |
|--------|-------|------------|---------------|
| `patientsClass` | `patients` | guid, pname, pdob, pamka, ptel, paddr, pemail, pnote | → appointments (via guid), → operations (via pguid) |
| `appointmentsClass` | `appointments` | guid, pguid, adate, aplace, anote | → patients (pguid), → locations (aplace) |
| `operationsClass` | `operations` | guid, pguid, pname, opdate, opdiagnosis, opprocedure, clinic, surgeon1, surgeon2, anesthesiology | → patients (pguid) |
| `billingClass` | `billing` | guid, patientname, operationdate, operationtype, surgeon, amount, invoiceamount, invoicedate, invoicenumber, fees_pdf, invoice_pdf | Financial records |
| `locationsClass` | `locations` | guid, lang, name, machinename, address | Multi-language locations |
| `clinicsClass` | `clinics` | guid, clinic_name | Clinic registry |
| `doctorsClass` | `doctors` | guid, doctor_name, doctor_specialty | Doctor registry |
| `filesClass` | `files` | guid, furi, fpath, fname, fmime, fsize, fhash, fstatus | File storage records |
| `fileUsageClass` | `file_usage` | guid, file_guid, entity_type, entity_id, usage_type | File-to-entity links |
| `pdflibFilesClass` | `pdflib_files` | guid, file_name, file_path, file_hash, data (serialized) | PDF archive entries |
| `procedureInfoClass` | `procedure_info` | guid, field_name, field_category | Procedure catalog |
| `invoiceStatusClass` | `invoicestatus` | guid, status, label | Invoice status types |

### 5.3 ClassesEx — Custom Query Methods

Extended entity classes in `web/ClassesEx.php` add business-logic queries:

```php
// Patient search with multi-term matching
$results = patientsClassEx::search('SMITH JOHN', ['pname', 'pamka']);

// Patients sorted by last appointment (with LEFT JOIN)
$patients = patientsClassEx::getPatientsByLastAppointment('DESC');
// Returns: [['p' => patientsClass, 'a' => '2026-01-15 10:00'], ...]

// Patients sorted by name
$patients = patientsClassEx::getPatientsByName('ASC');

// Get patient by GUID
$patient = patientsClassEx::sgetByGuid('abc-123-def');

// Get appointments for a patient
$apps = appointmentsClassEx::getAppointmentsForPatient($pguid, 'DESC');

// User authentication
$user = usersClassEx::getUser($username, sha256($password));
$account = usersClassEx::getUserAccount($username);
```

**Location extensions** (`web/locationsClassEx.php`):
```php
// Get locations filtered by language
$locs = locationsClassEx::sgetAll('en');

// Set default location from session/cookie with fallback
locationsClassEx::setDefaultLocation();

// Get current location
$loc = locationsClassEx::getCurrentLocation();
```

---

## 6. Routing & Request Flow

### 6.1 Route Definition Syntax

Routes are defined in `config/settings.info.yaml` and module YAML files:

```yaml
routes:
  # Basic route
  homepage:
    title: { gr: "Αρχική", en: "Homepage" }
    name: homepage
    url: "/"
    handler: homepage

  # Route with parameter
  patient_edit:
    title: { en: "Patient edit", gr: "Επεξεργασία ασθενή" }
    url: "/patient/{id}/edit"
    handler: patient_edit
    method: get
    access: patients-edit-patient

  # POST variant (same URL, different method)
  patient_edit_post:
    title: "Patient edit"
    url: "/patient/{id}/edit"
    handler: patient_edit_post
    method: post

  # Multiple URL patterns for one handler
  patients_list:
    title: { gr: "Λίστα ασθενών", en: "Patients List" }
    url: ["/patients", "/patients/sort/{key}", "/patients/sort/{key}/{order}"]
    handler: patients_list
    access: authenticated user

  # Module-handled route
  pdflib-module:
    title: { gr: "Βιβλιοθήκη PDF", en: "PDF Library" }
    name: pdflib_handler
    module: pdflib          # Calls module's run() method
    url: /apps/pdflib
    method: get
```

### 6.2 How Modules Add Routes

Modules with a `.yaml` config file inject routes at boot time:

```php
// In module constructor (e.g., pdflib.php):
$rt = yaml_parse_file(__DIR__ . '/pdflib.yaml');
$srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
$kernel->addConfig($srt);                    // Merge routes into kernel config
$router->initRouteTable($kernel->getConfig('routes')); // Reload route table
```

This pattern allows modules to be self-contained — adding/removing a module from the modules list automatically registers/removes its routes.

### 6.3 Parameter Extraction

Route parameters like `{id}`, `{key}`, `{order}` are extracted and passed to handlers:

```php
// URL: /patient/42/edit
// Route: /patient/{id}/edit
// Handler receives: $params = ['id' => '42']

function patient_edit($params) {
    $id = $params['id'];  // '42'
    $patient = patientsClass::sgetById($id);
    // ...
}
```

---

## 7. Module System

### 7.1 Module Architecture

Each module in `web/modules/{name}/` has up to 4 files:

| File | Required | Purpose |
|------|----------|---------|
| `{name}.php` | Yes | Module class + registration function + route handlers |
| `{name}.info.yaml` | Yes | Module metadata (name, template reference) |
| `{name}.yaml` | No | Routes, libraries, and config (merged into kernel) |
| `.zetem` template | No | In `web/templates/modules/` (not in module dir) |

### 7.2 Module Class Pattern

```php
class pdflibModule extends moduleClass {

    // Constructor: load config, register routes
    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);

        // Load module-specific YAML config
        $rt = yaml_parse_file(__DIR__ . '/pdflib.yaml');

        // Merge into kernel config
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);

        // Reload router with new routes
        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    // Render into page region (called during page assembly)
    function render($params = array()) {
        return $this->renderTemplate(['key' => 'value']);
    }

    // Run when module route is matched (optional)
    function run($params = array()) {
        if ($ret = SecurityClass::require('permission')) return $ret;
        $data = SomeClass::sgetAll();
        return $this->renderTemplate(['data' => $data]);
    }
}

// Registration function (called by framework)
function register_pdflib_module() {
    global $kernel;
    $kernel->registerModule(new pdflibModule(__DIR__, 'pdflib', 'pdflib.zetem'));
}

// Route handler functions (called by router)
function pdflib_action_delete($params) {
    // ...
}
```

### 7.3 Module Registration Flow

1. `registerModules()` called from `index.php`
2. Framework iterates `modules.modules` list from `settings.info.yaml`
3. For each module, looks in `modules.path` directories
4. Finds `{name}/{name}.info.yaml` → confirms module exists
5. Includes `{name}/{name}.php`
6. Calls `register_{name}_module()` function
7. Module constructor may load `{name}.yaml` and merge config/routes

### 7.4 Module Conditional Display

Modules can be conditionally shown per route via `modconf` in `settings.info.yaml`:

```yaml
modconf:
  message:
    display:
      userprofile:
        arguments:
          type: "Announcements"
        access: authenticated
```

This shows the `message` module only on the `userprofile` route, with specific arguments.

### 7.5 All Registered Modules

| Module | Type | Location | Purpose |
|--------|------|----------|---------|
| mainnavigation | Core | `fw/core/modules/` | Primary site menu |
| notifications | Core | `fw/core/modules/` | System notifications |
| header | Core | `fw/core/modules/` | Page header |
| breadcrumbs | Core | `fw/core/modules/` | Navigation breadcrumbs |
| userblock | Core | `fw/core/modules/` | Logged-in user info |
| htmltext | Core | `fw/core/modules/` | Static HTML blocks |
| content | Core | `fw/core/modules/` | Route content renderer |
| language_selector | Core | `fw/core/modules/` | Language switcher |
| message | Core | `fw/core/modules/` | User messages/announcements |
| translation_admin | App | `web/modules/` | Translation management |
| userprofile | App | `web/modules/` | User profile + TOTP |
| githash | App | `web/modules/` | Git commit display |
| copyright | App | `web/modules/` | Footer copyright |
| location | App | `web/modules/` | Clinic/location selector |
| datepicker | App | `web/modules/` | Date picker widget |
| pdflib | App | `web/modules/` | PDF document archive |
| backup | App | `web/modules/` | Database backup manager |
| topbar | App | `web/modules/` | Alternative top navigation |

---

## 8. ZETEM Template Engine

### 8.1 Template Compilation

ZETEM compiles templates to PHP and caches them. The compilation pipeline:

1. `compileComments()` — Remove `{# ... #}`
2. `compileBlock()` — Extract `{% block name %}...{% endblock %}`
3. `compileYield()` — Insert `{% yield block_name %}`
4. `compileMacros()` — Process reusable macros
5. `compileSet()` — Handle variable assignment
6. `compileForLoops()` — Translate loop syntax
7. `compileConditionals()` — Process if/elseif/else
8. `compileEscapedEchos()` — Safe output `{{{ $var }}}`
9. `compileEchos()` — Regular output `{{ $var }}`
10. `compilePHP()` — Preserve raw PHP blocks

Cache invalidation is automatic — templates are recompiled when the source file (or any included dependency) is modified.

### 8.2 Complete Syntax Reference

**Output:**
```zetem
{{ $variable }}                    {# Simple variable #}
{{ $object->getField() }}          {# Method call #}
{{ $array['key'] }}                {# Array access #}
{{ $var | filter }}                 {# With filter #}
{{ $var | filter('arg1', 'arg2') }}{# Filter with args #}
{{ $var | filter1 | filter2 }}     {# Chained filters #}
{{{ $var }}}                       {# HTML-escaped output #}
```

**Control Flow:**
```zetem
{% if $condition %}
    ...
{% elseif $other_condition %}
    ...
{% else %}
    ...
{% endif %}

{% for $item in $list %}
    {{ $item }}
{% endfor %}

{% foreach($array as $key => $value): %}
    {{ $key }}: {{ $value }}
{% endforeach; %}

{% set $var = "value" %}
{% set $obj = new ClassName(['key' => 'val']) %}
```

**Template Composition:**
```zetem
{% include 'partial.zetem' %}
{% attach_library('library-name') %}

{% block sidebar %}
    Default sidebar content
{% endblock %}

{% yield sidebar %}

{% extends 'parent.zetem' %}
```

**Macros (Reusable Components):**
```zetem
{% macro badge($text, $color = 'primary') %}
    <span class="badge {{ $color }}">{{ $text }}</span>
{% endmacro %}

{{ badge('Active', 'success') }}
```

**Helper Functions in Templates:**
```zetem
{{ rel_url('/patients') }}                    {# Generate URL #}
{{ t("Search") }}                             {# Translate #}
{{ attributes($attrsObject) }}                {# HTML attributes #}
{{ $date | date('d-m-Y') }}                   {# Format date #}
{{ $price | number_format(2, ',', '.') }}     {# Format number #}
```

**Loop Index:**
```zetem
{% for $item in $list %}
    {{ $index.0 }}  {# Current loop index (0-based) #}
{% endfor %}
```

### 8.3 Template Filters (50+)

**String:**
`upper`, `lower`, `capitalize`, `title`, `trim`, `ltrim`, `rtrim`, `strip`, `nl2br`, `striptags`, `escape`/`e`, `raw`, `slug`, `truncate`, `wordwrap`, `replace`, `split`, `join`, `reverse`, `repeat`, `pad`, `substr`, `wrap`

**Number:**
`abs`, `round`, `floor`, `ceil`, `number_format`, `currency`, `percent`, `ordinal`

**Date:**
`date`, `date_modify`, `time_ago`, `timestamp`

**Array:**
`first`, `last`, `length`/`count`, `keys`, `values`, `merge`, `slice`, `sort`, `rsort`, `ksort`, `unique`, `column`, `filter`, `map`, `batch`, `shuffle`, `chunk`

**JSON/URL:**
`json_encode`, `json_decode`, `url_encode`, `url_decode`, `base64_encode`, `base64_decode`

**Type:**
`int`, `float`, `string`, `bool`, `array`

**Utility:**
`default`, `asset`, `cache_asset`, `t`/`translate`, `dump`/`debug`

### 8.4 Template File Locations

| Type | Directory | Naming | Purpose |
|------|-----------|--------|---------|
| Page layout | `web/templates/page/` | `page.zetem` | Main HTML structure |
| Content | `web/templates/content/` | `{page}.zetem` | Route page content |
| Blocks | `web/templates/blocks/` | `{block}.zetem` | Reusable UI blocks |
| Modules | `web/templates/modules/` | `{module}.zetem` | Module rendering |
| Menu | `web/templates/menu/` | `top_menu.zetem` | Navigation menus |
| Apps | `web/templates/apps/` | `{app}/{app}.zetem` | Application pages |

---

## 9. Security & Authentication

### 9.1 Role-Based Access Control

**Role Hierarchy:**

| Role | Permissions | Description |
|------|------------|-------------|
| `anonymous` | `anonymous` | Unauthenticated visitors |
| `authenticated` | `authenticated_content` | Any logged-in user |
| `user` | `patients-view-list`, `appointments-view-list` | View-only access |
| `power-user` | All `user` + `patients-new-patient`, `patients-delete-patient`, `patients-edit-patient`, `appointment-edit`, `pdflib-access`, `backup-access`, `administer_translations` | Full data management |
| `administrator` | `all` | Unrestricted access |

### 9.2 Permission Checking

```php
// In route handlers — returns error HTML if unauthorized
if ($ret = SecurityClass::require('patients-edit-patient')) return $ret;

// Boolean check
if (SecurityClass::userLoggedIn()) { ... }

// Check specific permission
if (SecurityClass::userIsPermitted('backup-access')) { ... }
```

### 9.3 Authentication Flow

**Login:**
1. User submits `/login` form (username + password)
2. `login_post()` handler calls `$kernel->authenticateUser()`
3. Password verified against DB (SHA-256 hash)
4. On success: `$kernel->loginUser($username, $roles)` sets session
5. Optional "remember me": secure token stored in cookie + DB

**Session:**
- Session lifetime: 1 hour (`gc_maxlifetime`, `cookie_lifetime`)
- Session stores: `user`, `user_id`, `CURRENT_LANGUAGE`, `location`, `route_match`

**Remember Me:**
- Token: `bin2hex(random_bytes(32))` — 64-char hex
- Stored in `user_tokens` DB table with expiry
- Cookie set with token, verified on page load
- Expired tokens cleaned by maintenance

**TOTP (Two-Factor):**
- Setup at `/totp/setup`
- QR code generation for authenticator apps
- Verification at `/totp/verify`

### 9.4 Route Access Control

```yaml
# In route definition:
patient_edit:
  url: "/patient/{id}/edit"
  handler: patient_edit
  access: patients-edit-patient   # Requires this permission

# Route-level check happens in routerCallFunction():
# - If user lacks permission, returns 401
# - Handler can also call SecurityClass::require() for double-checking
```

---

## 10. Multilingual System

### 10.1 Language Configuration

```yaml
# config/settings.info.yaml
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

### 10.2 Language Detection Priority

1. **URL prefix** — `/en/patients` or `/el/patients`
2. **Session** — `$_SESSION['CURRENT_LANGUAGE']`
3. **Cookie** — Persistent language preference
4. **User profile** — Authenticated user's saved preference
5. **Query parameter** — `?lang=en`
6. **Browser header** — `Accept-Language`
7. **Default** — `el` (Greek)

### 10.3 Translation Usage

**In PHP:**
```php
t('Search');                              // Simple token
t('Welcome, {name}!', ['name' => $user]); // With parameters
t_plural('item', $count);                // Pluralization
t_context('bank', 'financial');           // Context disambiguation
getLangText(['en' => 'Home', 'gr' => 'Αρχική']); // Inline multilingual
```

**In Templates:**
```zetem
{{ t("Search") }}
{{ t("Patients") }}
{{ $title | t }}
```

**In Configuration:**
```yaml
routes:
  homepage:
    title:
      gr: "Αρχική"
      en: "Homepage"

menu:
  main:
    - homepage:
      text:
        gr: 'Αρχική'
        en: 'Home'
```

### 10.4 Translation Management

The `translation_admin` module provides a full admin interface at `/admin/translations`:
- List all tokens with status
- Search/filter tokens
- Inline editing
- Import from CSV or YAML
- Export to CSV or YAML
- Statistics (translated/untranslated counts)
- Auto-registration of new tokens when encountered

---

## 11. File Management

### 11.1 Stream URI System

```
public://documents/report.pdf     → files/public/documents/report.pdf
private://patient-42/scan.dcm     → files/private/patient-42/scan.dcm
temp://upload-abc123/chunk.tmp    → files/temp/upload-abc123/chunk.tmp
cache://bundles/app.css           → files/cache/bundles/app.css
```

| Stream | Web Accessible | Serve Route | TTL | Purpose |
|--------|---------------|-------------|-----|---------|
| `public://` | Yes | Direct Apache | — | Public downloads |
| `private://` | No | `/files/get/{path}` | — | Auth-gated files |
| `temp://` | No | — | 24 hours | Upload staging |
| `cache://` | No | — | — | Generated assets |

### 11.2 File Entity

Each managed file has a DB record:
```php
$file = new filesClass();
$file->setfuri('private://patient-42/scan.pdf');  // Stream URI
$file->setfpath('/var/www/.../files/private/patient-42/scan.pdf'); // Resolved path
$file->setfname('scan.pdf');                       // Original filename
$file->setfmime('application/pdf');                // MIME type
$file->setfsize(1048576);                          // Size in bytes
$file->setfhash(hash_file('sha256', $path));       // Integrity hash
$file->setfstatus('active');                       // active|deleted|orphaned
$file->insert();
```

### 11.3 File Usage Tracking

Links files to entities with usage types:
```php
// Attach file to patient record
file_save_upload(
    $_FILES['document'],
    'private://patient-42/scan.pdf',
    'patient',          // entity_type
    '42',               // entity_id
    'attachment'         // usage_type
);

// Check if file is still referenced
$count = file_usage_count('private://old-report.pdf');

// Safe delete (only if no references)
file_delete_if_unused('private://old-report.pdf');
```

---

## 12. Design System & Frontend

### 12.1 CSS Architecture

**Layer 1: Reset & Foundation**
- `normalize.css` — Browser reset
- `color-palette.css` — Color variable definitions

**Layer 2: Design System** (`web/css/design/`)
- `design-system.css` — CSS custom properties (tokens)
- `layout.css` — Grid system and layout utilities
- `components.css` — Reusable component styles

**Layer 3: Application Styles**
- `styles.css` — Main application (25KB)
- `navigation.css` — Base navigation
- Feature-specific CSS files

### 12.2 Design Tokens

**Colors:**
```css
/* Primary: Medical Teal */
--primary-50: #f0fdfa;
--primary-500: #14b8a6;
--primary-900: #134e4a;

/* Neutral: Slate */
--slate-50: #f8fafc;
--slate-500: #64748b;
--slate-900: #0f172a;

/* Semantic */
--success-500: #22c55e;    /* Green */
--warning-500: #f59e0b;    /* Amber */
--danger-500: #ef4444;     /* Red */
--info-500: #3b82f6;       /* Blue */
```

**Spacing (8px base):**
```css
--space-1: 0.25rem;   /* 4px */
--space-2: 0.5rem;    /* 8px */
--space-4: 1rem;      /* 16px */
--space-6: 1.5rem;    /* 24px */
--space-8: 2rem;      /* 32px */
--space-12: 3rem;     /* 48px */
--space-16: 4rem;     /* 64px */
```

**Typography:**
```css
--text-xs: 0.75rem;   /* 12px */
--text-sm: 0.875rem;  /* 14px */
--text-base: 1rem;    /* 16px */
--text-lg: 1.125rem;  /* 18px */
--text-xl: 1.25rem;   /* 20px */
--text-2xl: 1.5rem;   /* 24px */
```

### 12.3 Reusable Components

From `components.css`:

| Component | Class | Usage |
|-----------|-------|-------|
| KPI Cards | `.kpi-card`, `.kpi-value`, `.kpi-trend` | Dashboard statistics |
| Filter Chips | `.filter-chip`, `.filter-chip.active` | Toggle filters |
| Alerts | `.alert.success`, `.alert.warning`, `.alert.danger` | Status messages |
| Patient List | `.patient-item`, `.patient-avatar`, `.patient-meta` | Patient cards |
| Appointment List | `.appointment-item.consultation` | Appointment cards |
| Data Tables | `.table-wrapper`, `.table-header`, `.table-action-btn` | Sortable tables |
| Action Tiles | `.action-tile`, `.action-tile-icon` | Quick action buttons |
| Empty State | `.empty-state`, `.empty-state-icon` | No-data placeholder |
| Pagination | `.pagination`, `.pagination-btn.active` | Page navigation |
| Filter Bar | `.filter-bar`, `.filter-group` | Search/filter controls |
| Badges | `.badge.success`, `.badge.warning` | Status indicators |

### 12.4 Library System

CSS/JS bundles loaded on-demand per page:

```yaml
# Defined in settings.info.yaml or module YAML:
libraries:
  pikaday-library:
    css:
      - pikaday_css:
        src: 'https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css'
    head_script:
      - pikaday_js:
        src: "https://cdn.jsdelivr.net/npm/pikaday/pikaday.js"
    foot_script:
      - pikaday_js:
        src: "."

  pdflib-library:
    css:
      - pdflib:
        src: "@app/css/pdflib.css"    # @app = application web root
    foot_script:
      - pdflib:
        src: "@app/js/pdflib.js"
        defer:
```

**Attaching in templates:**
```zetem
{% attach_library('pikaday-library') %}
{% attach_library('loader-library') %}
```

### 12.5 JavaScript

**Main (`scripts.js` — 11KB):**
- Patient search with AJAX autocomplete
- Dropdown submenu positioning
- Copy-to-clipboard functionality
- Delete confirmation dialogs (`[confirmation]` attribute)
- Pikaday date picker initialization
- Mobile menu toggle

**AJAX Pattern:**
```javascript
// Patient search autocomplete
function searchPatients(term) {
    fetch(baseUrl + '/patients/searchajax/' + term)
        .then(r => r.json())
        .then(data => {
            // Render results dropdown
            data.list.forEach(patient => {
                // patient.id, patient.name, patient.amka, patient.link
            });
        });
}
```

---

## 13. Application Features

### 13.1 Patient Management

**List View** (`/patients`):
- Sortable by name (A-Z, Z-A) or last appointment (newest/oldest)
- Search with multi-term matching (searches name, AMKA, phone)
- AJAX instant search with autocomplete dropdown
- Copy-to-clipboard for patient name and AMKA
- Soft-delete with confirmation

**Edit View** (`/patient/{id}/edit`):
- Patient info form (name, DOB, AMKA, phone, address, email)
- Age auto-calculation from DOB
- AMKA validator
- Inline copy buttons
- Appointment history (collapsible accordion)
- Create new appointment from patient page
- Auto-expanding textarea for notes
- File attachments

### 13.2 Appointments

- Linked to patients via GUID
- Location/clinic selector (stored in session)
- Date picker with Pikaday
- Soft-delete with recovery
- View/edit with all patient context

### 13.3 PDF Archive (`/apps/pdflib`)

- Drag-and-drop or file input upload
- Server-side PDF text extraction (`pdftotext`)
- Automatic metadata parsing (invoice numbers, patient names, amounts)
- SHA-256 file integrity hashing
- Download and delete actions
- Serialized metadata storage

### 13.4 Backup Manager (`/apps/backup`)

- Database backup creation (mysqldump)
- List available backups
- Download backup files
- Managed through module interface

### 13.5 Translation Admin (`/admin/translations`)

- Full CRUD for translation tokens
- Search/filter by status (translated/untranslated)
- Pagination for large token sets
- CSV import/export
- YAML import/export
- Translation statistics dashboard

### 13.6 QR Code Generator (`/apps/genqr`)

- Text input form
- Server-side QR generation (`qrencode` CLI tool)
- Image display

---

## 14. Configuration Reference

### 14.1 `config/settings.info.yaml` — Complete Structure

```yaml
# ─── Site Metadata ───
title: "Patient Management System"
tz: "Europe/Athens"
meta: [charset, viewport, X-UA-Compatible]
favicon: { type: image/png, src: assets/logo3.png }

# ─── Template Paths ───
templates: ['./core/templates/', './templates/']

# ─── Global CSS ───
css:
  - normalize: { src: 'css/normalize.css' }
  - colorpalette: { src: 'css/color-palette.css' }
  - navigation: { src: 'css/navigation.css' }
  - styles: { src: 'css/styles.css' }
  - boxicons: { src: 'css/bx/css/boxicons.min.css' }
  - designsystem: { src: ['css/design/design-system.css', ...] }

# ─── Global JS ───
foot_script:
  - scripts: { src: "js/scripts.js", defer: }
  - mobile-menu: { src: "js/mobile-menu.js", defer: }

# ─── File System ───
file_system:
  base_path: 'files'
  streams: { public, private, temp, cache }
  cleanup: { temp_ttl: 86400, run_on_cron: true }

# ─── Languages ───
multilingual: true
languages: { en: {...}, el: {...} }
language_detection: { default: el, priority: [url, session, cookie, ...] }
dictionary: { auto_register: true, fallback_to_file: true }
language_switcher: { mode: path_prefix, preserve_page: true }

# ─── Libraries ───
libraries:
  loader-library: { css: [...], foot_script: [...] }
  pikaday-library: { css: [...], head_script: [...] }
  # ... more libraries

# ─── Security ───
roles:
  anonymous: anonymous
  administrator: all
  authenticated: authenticated_content
  user: [patients-view-list, appointments-view-list]
  power-user: [patients-view-list, ..., administer_translations]

permissions:
  administer_translations: { roles: [administrator, power-user] }

# ─── Routes ───
routes:
  homepage: { url: "/", handler: homepage }
  patients_list: { url: "/patients", handler: patients_list, access: authenticated user }
  # ... 28+ routes

# ─── Page Layout ───
regions: [header, main_navigation, notification, main_content, footer]
structure:
  header: [header, header-grid-2x1: [...], location, breadcrumbs]
  main_navigation: [mainnavigation]
  notification: [notifications, message]
  main_content: [content]
  footer: [footer, copyright]

# ─── Navigation Menu ───
menu:
  main:
    - homepage: { text: {gr: 'Αρχική', en: 'Home'}, url: '/' }
    - patients: { text: {...}, submenu: [...], access: power-user }
    - appointments: { text: {...}, url: '/appointments', disabled: true }
    - settings: { text: {...}, submenu: [...], access: power-user }
    - applications: { text: {...}, submenu: [qrgenerator, pdflib, backup] }

# ─── Module Conditional Display ───
modconf:
  message:
    display:
      userprofile: { arguments: {type: "Announcements"}, access: authenticated }

# ─── Module Registration ───
modules:
  path: ["/web/core/modules/", "/web/modules/"]
  modules: [mainnavigation, notifications, header, breadcrumbs, userblock,
            translation_admin, userprofile, htmltext, githash, copyright,
            content, location, datepicker, pdflib, language_selector,
            message, backup]
```

### 14.2 `config/db.php.in` — Database Config Template

```php
define('DB_HOST', '%DB_HOST%');
define('DB_USER', '%DB_USER%');
define('DB_PASS', '%DB_PASS%');
define('DB_NAME', '%DB_NAME%');
```

---

## 15. Developer Guide

### 15.1 Adding a New Entity

1. **Create YAML schema** in `web/classes/yaml/{table}.yaml`
2. **Generate entity class** (run the class generator tool)
3. **Import SQL** via `sql/msql.sh` or manually
4. **Add ClassesEx extensions** in `web/ClassesEx.php` for custom queries
5. **Use in handlers**: `$obj = tableClass::sgetById($id);`

### 15.2 Adding a New Route

1. **Add route** in `config/settings.info.yaml`:
   ```yaml
   my_route:
     title: { en: "My Page", gr: "Η σελίδα μου" }
     url: "/my-page/{id}"
     handler: my_handler
     method: get
     access: authenticated
   ```
2. **Write handler** in `web/index.php`:
   ```php
   function my_handler($params) {
       if ($ret = SecurityClass::require('my-permission')) return $ret;
       $data = MyClass::sgetById($params['id']);
       return Renderer::render('my_template.zetem', ['data' => $data]);
   }
   ```
3. **Create template** in `web/templates/content/my_template.zetem`

### 15.3 Adding a New Module

1. Create directory: `web/modules/mymodule/`
2. Create `mymodule.info.yaml`:
   ```yaml
   name: mymodule
   template: mymodule.zetem
   ```
3. Create `mymodule.php`:
   ```php
   class myModule extends moduleClass {
       public function __construct($adir, $amodule, $atemplate) {
           parent::__construct($adir, $amodule, $atemplate);
           // Optional: load mymodule.yaml for routes/libraries
       }
       function render($params = []) {
           return $this->renderTemplate(['key' => 'value']);
       }
   }
   function register_mymodule_module() {
       global $kernel;
       $kernel->registerModule(new myModule(__DIR__, 'mymodule', 'mymodule.zetem'));
   }
   ```
4. Create template: `web/templates/modules/mymodule.zetem`
5. Register in `config/settings.info.yaml`:
   ```yaml
   modules:
     modules:
       - mymodule
   ```

### 15.4 Adding a CSS/JS Library

1. Add to `config/settings.info.yaml` or module YAML:
   ```yaml
   libraries:
     my-library:
       css:
         - mystyle:
           src: "@app/css/mystyle.css"
       foot_script:
         - myscript:
           src: "@app/js/myscript.js"
           defer:
   ```
2. Attach in template:
   ```zetem
   {% attach_library('my-library') %}
   ```

### 15.5 Debug Helpers

```php
echopre($variable);                    // Formatted var_dump with source location
Renderer::init($paths, false, ...);    // false = disable template cache
// Template cache in web/cache/ — delete to force recompilation
```

---

## 16. Codebase Statistics

### File Counts

| Category | Count | Lines |
|----------|-------|-------|
| **Framework PHP** | ~25 files | ~180KB |
| **App PHP (index.php)** | 1 file | 1,177 lines |
| **Entity Classes (auto-gen)** | 13 files | ~3,525 lines |
| **ClassesEx** | 2 files | 302 lines |
| **Module PHP** | ~18 files | ~1,500 lines |
| **YAML Schemas** | 12 files | ~400 lines |
| **ZETEM Templates** | 36 files | ~2,500 lines |
| **CSS Files** | 22+ files | ~200KB |
| **JS Files** | 8 files | ~30KB |
| **Config YAML** | 3 files | ~650 lines |
| **SQL Scripts** | 12 files | varies |

### Dependencies

| Dependency | Type | Purpose |
|------------|------|---------|
| PHP 8.x | Runtime | Application server |
| ext-pdo_mysql | PHP Extension | Database connectivity |
| ext-yaml | PHP Extension | YAML config parsing |
| ext-gd | PHP Extension | Image processing |
| ext-zip | PHP Extension | ZIP archive handling |
| ext-finfo | PHP Extension | MIME type detection |
| MySQL/MariaDB | Database | Data storage |
| Apache | Web Server | URL rewriting, PHP execution |
| Boxicons | Frontend | Icon library (bundled) |
| Pikaday | Frontend | Date picker (CDN) |
| Moment.js | Frontend | Date utilities (CDN) |
| qrencode | CLI Tool | QR code generation |
| pdftotext | CLI Tool | PDF text extraction |

### No External PHP Dependencies

ZPMS has **zero Composer dependencies**. The Zeus Framework and all application code are self-contained PHP with no third-party packages. Only PHP core extensions are required.

---

*Document generated from full codebase analysis — February 2026*
