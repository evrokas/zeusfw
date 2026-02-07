# Multilingual CMS Implementation - Complete Guide

**Date:** February 6, 2026  
**Topic:** Implementing Multilingual Support in a Custom PHP CMS

---

## Table of Contents

1. [Conversation Summary](#conversation-summary)
2. [Key Questions & Answers](#key-questions--answers)
3. [Artifacts](#artifacts)
   - [Artifact 1: .htaccess Configuration](#artifact-1-htaccess-configuration)
   - [Artifact 2: PHP Multilingual Core](#artifact-2-php-multilingual-core)
   - [Artifact 3: Translation Files Structure](#artifact-3-translation-files-structure)
   - [Artifact 4: Example Controller & View](#artifact-4-example-controller--view)
   - [Artifact 5: Implementation Guide](#artifact-5-implementation-guide)
   - [Artifact 6: How Drupal Handles Multilingual](#artifact-6-how-drupal-handles-multilingual)
   - [Artifact 7: URL Helper Functions](#artifact-7-url-helper-functions)
   - [Artifact 8: URL Functions Guide](#artifact-8-url-functions-guide)
   - [Artifact 9: Absolute URL Implementation](#artifact-9-absolute-url-implementation)
4. [Implementation Checklist](#implementation-checklist)
5. [File Structure](#file-structure)

---

## Conversation Summary

This conversation covered the complete implementation of multilingual support for a custom PHP CMS that:
- Uses YAML configuration files (not database storage)
- Avoids Composer and external libraries where possible
- Already has a design system (design-system.css, layout.css, components.css)
- Has an existing `rel_url()` function for assets
- Uses `.htaccess` for routing to `index.php?q=$1`

### Main Topics Covered:

1. **Basic multilingual implementation** using URL path prefixes (`/en/`, `/es/`, etc.)
2. **Language detection priority**: URL → Session → Cookie → Browser → Default
3. **How Drupal handles multilingual** (comparison with enterprise approach)
4. **Integration with existing `rel_url()` function**
5. **Asset URLs vs Page URLs** (language-independent vs language-dependent)
6. **Absolute URL functions** for emails, SEO, and social sharing

---

## Key Questions & Answers

### Q1: How to implement multilingual support?

**Answer:** Use URL-based language detection with subdirectory method:
- `yoursite.com/en/page` (English)
- `yoursite.com/es/page` (Spanish)
- `yoursite.com/fr/page` (French)

**Detection Priority:**
1. URL parameter (from .htaccess rewrite)
2. Session variable
3. Cookie
4. Browser language (`HTTP_ACCEPT_LANGUAGE`)
5. Default language (fallback)

### Q2: How does Drupal handle this situation?

**Answer:** Drupal uses a sophisticated plugin-based architecture:
- **Language Negotiation API** with weighted detection methods
- **Separate language types** (Interface, Content, URL)
- **Plugin system** instead of simple URL rewriting
- All detection happens in PHP (not .htaccess)
- Entity-based translation system

**Your implementation vs Drupal:**
- Your approach: Simpler, uses .htaccess to extract language
- Drupal approach: More complex, enterprise-level features

### Q3: How to integrate with existing `rel_url()` function?

**Answer:** Create separate functions:
- `rel_url($path)` - For assets WITHOUT language (`/styles/main.css`)
- `lang_url($path, $lang)` - For pages WITH language (`/en/dashboard`)
- `absolute_url($path)` - For full URLs without language (emails)
- `absolute_lang_url($path, $lang)` - For full URLs with language (SEO)

### Q4: Aren't `base_url()` and `rel_url()` the same?

**Answer:** Yes! This was redundant. Simplified to:
- `rel_url()` - Relative, no language
- `lang_url()` - Relative, with language
- `absolute_url()` - Absolute (domain), no language
- `absolute_lang_url()` - Absolute (domain), with language

---

## Artifacts

### Artifact 1: .htaccess Configuration

**File:** `.htaccess`

```apache
# Enable Rewrite Engine
RewriteEngine On
RewriteBase /

# Force HTTPS (Optional but recommended)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ==========================================
# MULTILINGUAL URL HANDLING
# ==========================================

# Redirect root to default language (English)
# Example: yoursite.com → yoursite.com/en/
RewriteCond %{REQUEST_URI} ^/$
RewriteRule ^$ /en/ [R=302,L]

# Extract language code from URL and pass to index.php
# Pattern: /en/page/subpage → lang=en&q=page/subpage
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(en|es|fr|de|pt|it|nl|pl|ru|zh|ja|ar)/(.*)$ index.php?lang=$1&q=$2 [L,QSA]

# Handle language root pages (e.g., /en/, /es/)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(en|es|fr|de|pt|it|nl|pl|ru|zh|ja|ar)/?$ index.php?lang=$1 [L,QSA]

# Fallback: Redirect URLs without language code to default language
# Example: /dashboard → /en/dashboard
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/(en|es|fr|de|pt|it|nl|pl|ru|zh|ja|ar)/
RewriteCond %{REQUEST_URI} !^/index\.php
RewriteRule ^(.*)$ /en/$1 [R=302,L]

# ==========================================
# STANDARD RULES
# ==========================================

# Prevent direct access to certain files
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql|conf)$">
  Order Allow,Deny
  Deny from all
</FilesMatch>

# Enable browser caching
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpg "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/gif "access plus 1 year"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Compress text files
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/javascript
</IfModule>
```

**For Subdirectory Installation (`/mysite/`):**

```apache
RewriteEngine On
RewriteBase /mysite/

# Redirect root to default language
RewriteCond %{REQUEST_URI} ^/mysite/?$
RewriteRule ^$ /mysite/en/ [R=302,L]

# Language-aware routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(en|es|fr|de)/(.*)$ index.php?lang=$1&q=$2 [L,QSA]

# Handle language root pages
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(en|es|fr|de)/?$ index.php?lang=$1 [L,QSA]

# Fallback to default language
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/mysite/(en|es|fr|de)/
RewriteCond %{REQUEST_URI} !^/mysite/index\.php
RewriteCond %{REQUEST_URI} !^/mysite/(styles|js|images)/
RewriteRule ^(.*)$ /mysite/en/$1 [R=302,L]
```

---

### Artifact 2: PHP Multilingual Core

**File:** `index.php` (or include this in a separate file)

```php
<?php
/**
 * Multilingual CMS - Main Index File
 * 
 * This file receives:
 * - lang parameter (language code from URL)
 * - q parameter (the rest of the URL path)
 */

session_start();

// ==========================================
// CONFIGURATION
// ==========================================

define('DEFAULT_LANG', 'en');
define('SUPPORTED_LANGS', ['en', 'es', 'fr', 'de', 'pt', 'it', 'nl', 'pl', 'ru', 'zh', 'ja', 'ar']);
define('LANG_DIR', __DIR__ . '/languages/');
define('BASE_URL', 'https://yoursite.com/');

// ==========================================
// MULTILINGUAL CLASS
// ==========================================

class Multilingual {
    private $currentLang;
    private $translations = [];
    private $fallbackLang = DEFAULT_LANG;
    
    public function __construct() {
        $this->currentLang = $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Detect language from multiple sources
     */
    private function detectLanguage() {
        // 1. Check URL parameter (from .htaccess)
        if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS)) {
            $_SESSION['user_lang'] = $_GET['lang'];
            return $_GET['lang'];
        }
        
        // 2. Check session
        if (isset($_SESSION['user_lang']) && in_array($_SESSION['user_lang'], SUPPORTED_LANGS)) {
            return $_SESSION['user_lang'];
        }
        
        // 3. Check cookie
        if (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], SUPPORTED_LANGS)) {
            return $_COOKIE['user_lang'];
        }
        
        // 4. Check browser language
        $browserLang = $this->getBrowserLanguage();
        if ($browserLang && in_array($browserLang, SUPPORTED_LANGS)) {
            return $browserLang;
        }
        
        // 5. Default language
        return $this->fallbackLang;
    }
    
    /**
     * Get browser language
     */
    private function getBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if (!empty($langs)) {
                $lang = substr($langs[0], 0, 2);
                return strtolower($lang);
            }
        }
        return null;
    }
    
    /**
     * Load translation files
     */
    private function loadTranslations() {
        // Load current language
        $currentFile = LANG_DIR . $this->currentLang . '.php';
        if (file_exists($currentFile)) {
            $this->translations = include $currentFile;
        }
        
        // Load fallback language if current is not default
        if ($this->currentLang !== $this->fallbackLang) {
            $fallbackFile = LANG_DIR . $this->fallbackLang . '.php';
            if (file_exists($fallbackFile)) {
                $fallbackTranslations = include $fallbackFile;
                // Merge with current translations (current takes precedence)
                $this->translations = array_merge($fallbackTranslations, $this->translations);
            }
        }
    }
    
    /**
     * Get translation
     */
    public function get($key, $params = []) {
        $text = $this->translations[$key] ?? $key;
        
        // Replace parameters {name}, {count}, etc.
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $text = str_replace('{' . $param . '}', $value, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Short alias for get()
     */
    public function t($key, $params = []) {
        return $this->get($key, $params);
    }
    
    /**
     * Get current language
     */
    public function getCurrentLang() {
        return $this->currentLang;
    }
    
    /**
     * Set language and save preference
     */
    public function setLanguage($lang) {
        if (in_array($lang, SUPPORTED_LANGS)) {
            $this->currentLang = $lang;
            $_SESSION['user_lang'] = $lang;
            setcookie('user_lang', $lang, time() + (86400 * 365), '/'); // 1 year
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    /**
     * Generate URL with language prefix
     */
    public function url($path = '') {
        $path = ltrim($path, '/');
        return BASE_URL . $this->currentLang . '/' . $path;
    }
    
    /**
     * Get all supported languages
     */
    public function getSupportedLanguages() {
        return SUPPORTED_LANGS;
    }
    
    /**
     * Get language name
     */
    public function getLanguageName($langCode = null) {
        $code = $langCode ?? $this->currentLang;
        
        $names = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'pt' => 'Português',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية'
        ];
        
        return $names[$code] ?? $code;
    }
}

// ==========================================
// INITIALIZE MULTILINGUAL
// ==========================================

$ml = new Multilingual();

// Make it globally accessible (optional - you can also use dependency injection)
function t($key, $params = []) {
    global $ml;
    return $ml->t($key, $params);
}

function current_lang() {
    global $ml;
    return $ml->getCurrentLang();
}

function lang_url($path = '') {
    global $ml;
    return $ml->url($path);
}

// ==========================================
// ROUTING
// ==========================================

// Get the requested path
$path = isset($_GET['q']) ? trim($_GET['q'], '/') : '';

// Simple routing example
$parts = explode('/', $path);
$controller = !empty($parts[0]) ? $parts[0] : 'home';

// Include appropriate controller/view
$controllerFile = __DIR__ . '/controllers/' . $controller . '.php';

if (file_exists($controllerFile)) {
    include $controllerFile;
} else {
    // 404 page
    header("HTTP/1.0 404 Not Found");
    include __DIR__ . '/views/404.php';
}
?>
```

---

### Artifact 3: Translation Files Structure

**File:** `languages/en.php`

```php
<?php
return [
    // Navigation
    'nav.home' => 'Home',
    'nav.dashboard' => 'Dashboard',
    'nav.content' => 'Content',
    'nav.settings' => 'Settings',
    'nav.logout' => 'Logout',
    
    // Common
    'common.welcome' => 'Welcome',
    'common.save' => 'Save',
    'common.cancel' => 'Cancel',
    'common.delete' => 'Delete',
    'common.edit' => 'Edit',
    'common.view' => 'View',
    'common.search' => 'Search',
    'common.filter' => 'Filter',
    'common.loading' => 'Loading...',
    'common.no_results' => 'No results found',
    
    // Dashboard
    'dashboard.title' => 'Dashboard',
    'dashboard.welcome_message' => 'Welcome back, {name}!',
    'dashboard.total_users' => 'Total Users',
    'dashboard.total_content' => 'Total Content',
    'dashboard.recent_activity' => 'Recent Activity',
    
    // Messages
    'msg.success' => 'Operation completed successfully',
    'msg.error' => 'An error occurred',
    'msg.saved' => 'Changes saved successfully',
    'msg.deleted' => 'Item deleted successfully',
    
    // Forms
    'form.title' => 'Title',
    'form.description' => 'Description',
    'form.content' => 'Content',
    'form.publish_date' => 'Publish Date',
    'form.status' => 'Status',
    'form.required' => 'This field is required',
    
    // User
    'user.profile' => 'Profile',
    'user.admin' => 'Administrator',
    'user.editor' => 'Editor',
    'user.viewer' => 'Viewer',
];
```

**File:** `languages/es.php`

```php
<?php
return [
    // Navigation
    'nav.home' => 'Inicio',
    'nav.dashboard' => 'Panel',
    'nav.content' => 'Contenido',
    'nav.settings' => 'Configuración',
    'nav.logout' => 'Cerrar Sesión',
    
    // Common
    'common.welcome' => 'Bienvenido',
    'common.save' => 'Guardar',
    'common.cancel' => 'Cancelar',
    'common.delete' => 'Eliminar',
    'common.edit' => 'Editar',
    'common.view' => 'Ver',
    'common.search' => 'Buscar',
    'common.filter' => 'Filtrar',
    'common.loading' => 'Cargando...',
    'common.no_results' => 'No se encontraron resultados',
    
    // Dashboard
    'dashboard.title' => 'Panel de Control',
    'dashboard.welcome_message' => '¡Bienvenido de nuevo, {name}!',
    'dashboard.total_users' => 'Total de Usuarios',
    'dashboard.total_content' => 'Total de Contenido',
    'dashboard.recent_activity' => 'Actividad Reciente',
    
    // Messages
    'msg.success' => 'Operación completada exitosamente',
    'msg.error' => 'Ocurrió un error',
    'msg.saved' => 'Cambios guardados exitosamente',
    'msg.deleted' => 'Elemento eliminado exitosamente',
    
    // Forms
    'form.title' => 'Título',
    'form.description' => 'Descripción',
    'form.content' => 'Contenido',
    'form.publish_date' => 'Fecha de Publicación',
    'form.status' => 'Estado',
    'form.required' => 'Este campo es obligatorio',
    
    // User
    'user.profile' => 'Perfil',
    'user.admin' => 'Administrador',
    'user.editor' => 'Editor',
    'user.viewer' => 'Visor',
];
```

**File:** `languages/fr.php`

```php
<?php
return [
    // Navigation
    'nav.home' => 'Accueil',
    'nav.dashboard' => 'Tableau de bord',
    'nav.content' => 'Contenu',
    'nav.settings' => 'Paramètres',
    'nav.logout' => 'Déconnexion',
    
    // Common
    'common.welcome' => 'Bienvenue',
    'common.save' => 'Enregistrer',
    'common.cancel' => 'Annuler',
    'common.delete' => 'Supprimer',
    'common.edit' => 'Modifier',
    'common.view' => 'Voir',
    'common.search' => 'Rechercher',
    'common.filter' => 'Filtrer',
    'common.loading' => 'Chargement...',
    'common.no_results' => 'Aucun résultat trouvé',
    
    // Dashboard
    'dashboard.title' => 'Tableau de Bord',
    'dashboard.welcome_message' => 'Bon retour, {name}!',
    'dashboard.total_users' => 'Total des Utilisateurs',
    'dashboard.total_content' => 'Total du Contenu',
    'dashboard.recent_activity' => 'Activité Récente',
    
    // Messages
    'msg.success' => 'Opération réussie',
    'msg.error' => 'Une erreur est survenue',
    'msg.saved' => 'Modifications enregistrées avec succès',
    'msg.deleted' => 'Élément supprimé avec succès',
    
    // Forms
    'form.title' => 'Titre',
    'form.description' => 'Description',
    'form.content' => 'Contenu',
    'form.publish_date' => 'Date de Publication',
    'form.status' => 'Statut',
    'form.required' => 'Ce champ est obligatoire',
    
    // User
    'user.profile' => 'Profil',
    'user.admin' => 'Administrateur',
    'user.editor' => 'Éditeur',
    'user.viewer' => 'Spectateur',
];
```

---

### Artifact 4: Example Controller & View

This artifact is too long to include in full. See the separate files in the export.

---

### Artifact 5: Implementation Guide

This comprehensive guide covers everything you need to know. See the markdown file in the export.

---

### Artifact 6: How Drupal Handles Multilingual

Detailed comparison of Drupal's approach vs your custom implementation. See the markdown file.

---

### Artifact 7: URL Helper Functions

**File:** `includes/url_helpers.php`

```php
<?php
/**
 * URL Helper Functions for Multilingual CMS
 */

// ==========================================
// CONFIGURATION
// ==========================================

define('BASE_PATH', '/'); // or '/mysite/' if in subdirectory
define('BASE_URL', 'https://yoursite.com');

// ==========================================
// CORE URL FUNCTIONS
// ==========================================

/**
 * Get the base path of the site
 */
function get_base_path() {
    static $base_path = null;
    
    if ($base_path === null) {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $base_path = dirname($script_name);
        $base_path = str_replace('\\', '/', $base_path);
        $base_path = rtrim($base_path, '/') . '/';
        
        if ($base_path === '//') {
            $base_path = '/';
        }
    }
    
    return $base_path;
}

/**
 * Generate relative URL for assets (NO language prefix)
 */
function rel_url($path) {
    $path = ltrim($path, '/');
    return get_base_path() . $path;
}

/**
 * Generate language-aware URL for pages (WITH language prefix)
 */
function lang_url($path = '', $lang = null) {
    global $ml;
    
    if ($lang === null) {
        $lang = $ml->getCurrentLang();
    }
    
    $path = trim($path, '/');
    $url = get_base_path() . $lang . '/';
    
    if (!empty($path)) {
        $url .= $path;
    }
    
    return rtrim($url, '/') . (empty($path) ? '/' : '');
}

/**
 * Generate absolute URL (with domain), NO language
 */
function absolute_url($path = '') {
    return rtrim(BASE_URL, '/') . '/' . ltrim(rel_url($path), '/');
}

/**
 * Generate absolute URL with language
 */
function absolute_lang_url($path = '', $lang = null) {
    return rtrim(BASE_URL, '/') . '/' . ltrim(lang_url($path, $lang), '/');
}

/**
 * Get URL for current page in different language
 */
function switch_language_url($lang) {
    $current_path = current_path();
    return lang_url($current_path, $lang);
}

/**
 * Get current page path without language prefix
 */
function current_path() {
    return isset($_GET['q']) ? $_GET['q'] : '';
}

/**
 * Get language switcher data
 */
function get_language_switcher_data() {
    global $ml;
    
    $current_lang = $ml->getCurrentLang();
    $current_path = current_path();
    $languages = [];
    
    foreach ($ml->getSupportedLanguages() as $lang) {
        $languages[] = [
            'code' => $lang,
            'name' => $ml->getLanguageName($lang),
            'url' => lang_url($current_path, $lang),
            'active' => ($lang === $current_lang)
        ];
    }
    
    return $languages;
}

/**
 * Auto-detect base URL
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host;
}
```

---

### Artifact 8: URL Functions Guide

Complete guide explaining the difference between asset URLs and page URLs. See markdown file.

---

### Artifact 9: Absolute URL Implementation

Complete implementation guide with examples. See PHP file.

---

## Implementation Checklist

### Phase 1: Basic Setup
- [ ] Create `.htaccess` file with language routing rules
- [ ] Create `Multilingual` class in `index.php`
- [ ] Create `languages/` directory
- [ ] Create translation files (`en.php`, `es.php`, `fr.php`, etc.)
- [ ] Define constants (DEFAULT_LANG, SUPPORTED_LANGS, LANG_DIR, BASE_URL)

### Phase 2: URL Helpers
- [ ] Implement `get_base_path()` function
- [ ] Implement `rel_url()` for assets
- [ ] Implement `lang_url()` for pages
- [ ] Implement `absolute_url()` for emails/APIs
- [ ] Implement `absolute_lang_url()` for SEO
- [ ] Implement `switch_language_url()` for language switcher

### Phase 3: Template Integration
- [ ] Update all asset references to use `rel_url()`
- [ ] Update all navigation links to use `lang_url()`
- [ ] Add language switcher to header/navigation
- [ ] Add SEO meta tags (canonical, hreflang)
- [ ] Test all pages in different languages

### Phase 4: Content Translation
- [ ] Add all translatable strings to language files
- [ ] Replace hardcoded text with `t()` calls
- [ ] Test parameter replacement (`{name}`, `{count}`, etc.)
- [ ] Verify fallback to default language works

### Phase 5: Testing
- [ ] Test in root installation
- [ ] Test in subdirectory installation
- [ ] Test language switching maintains current page
- [ ] Test browser language detection
- [ ] Test session/cookie persistence
- [ ] Test all asset URLs load correctly
- [ ] Test SEO meta tags are correct
- [ ] Test with different browsers

### Phase 6: SEO Optimization
- [ ] Implement canonical URLs
- [ ] Implement hreflang tags
- [ ] Create sitemap.xml with all languages
- [ ] Add Open Graph meta tags
- [ ] Add Twitter Card meta tags
- [ ] Test with Google Search Console

---

## File Structure

```
your-cms/
├── .htaccess                           # URL rewriting rules
├── index.php                           # Main entry point + Multilingual class
├── config.php                          # Configuration (optional)
│
├── styles/                             # Design system CSS
│   ├── design-system.css
│   ├── layout.css
│   └── components.css
│
├── js/                                 # JavaScript files
│   └── app.js
│
├── images/                             # Images and assets
│   ├── logo.png
│   └── ...
│
├── languages/                          # Translation files
│   ├── en.php                          # English
│   ├── es.php                          # Spanish
│   ├── fr.php                          # French
│   ├── de.php                          # German
│   └── ...
│
├── includes/                           # Helper files (optional)
│   ├── url_helpers.php                 # URL functions
│   └── ...
│
├── controllers/                        # Controllers
│   ├── home.php
│   ├── dashboard.php
│   ├── content.php
│   └── ...
│
└── views/                              # View templates
    ├── home.php
    ├── dashboard.php
    ├── 404.php
    └── ...
```

---

## Quick Reference

### Common Functions

```php
// Translations
t('nav.home')                           // "Home" or "Inicio" or "Accueil"
t('welcome.message', ['name' => 'John']) // Parameter replacement

// Current language
current_lang()                          // "en", "es", "fr", etc.

// Asset URLs (no language)
rel_url('styles/main.css')              // /styles/main.css
rel_url('images/logo.png')              // /images/logo.png

// Page URLs (with language)
lang_url('')                            // /en/
lang_url('dashboard')                   // /en/dashboard
lang_url('about', 'es')                 // /es/about

// Absolute URLs
absolute_url('images/logo.png')         // https://yoursite.com/images/logo.png
absolute_lang_url('dashboard')          // https://yoursite.com/en/dashboard

// Language switcher
switch_language_url('es')               // /es/current-page
get_language_switcher_data()            // Array of all languages with URLs
```

### URL Patterns

| Type | Function | Example Input | Example Output |
|------|----------|---------------|----------------|
| Asset (CSS) | `rel_url()` | `'styles/main.css'` | `/styles/main.css` |
| Asset (Image) | `rel_url()` | `'images/logo.png'` | `/images/logo.png` |
| Page | `lang_url()` | `'dashboard'` | `/en/dashboard` |
| Page (specific lang) | `lang_url()` | `'about', 'es'` | `/es/about` |
| Email asset | `absolute_url()



## Example Templates
### Language Switcher Dropdown:

```php
<div class="language-selector">
    <button id="langBtn"><?php echo $ml->getLanguageName(); ?> ▼</button>
    <div class="language-menu" id="langMenu">
        <?php foreach (get_language_switcher_data() as $lang): ?>
            <a href="<?php echo $lang['url']; ?>" 
               class="<?php echo $lang['active'] ? 'active' : ''; ?>">
                <?php echo $lang['name']; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
```

### SEO Meta Tags:

```php
<head>
    <!-- Canonical -->
    <link rel="canonical" href="<?php echo absolute_lang_url(current_path()); ?>">
    
    <!-- Alternate Languages -->
    <?php foreach ($ml->getSupportedLanguages() as $lang): ?>
    <link rel="alternate" hreflang="<?php echo $lang; ?>" 
          href="<?php echo absolute_lang_url(current_path(), $lang); ?>">
    <?php endforeach; ?>
    
    <!-- Open Graph -->
    <meta property="og:url" content="<?php echo absolute_lang_url(current_path()); ?>">
    <meta property="og:locale" content="<?php echo current_lang(); ?>_<?php echo strtoupper(current_lang()); ?>">
</head>
```

### Complete Page Template:
```php
<!DOCTYPE html>
<html lang="<?php echo current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('page.title'); ?></title>
    <link rel="stylesheet" href="<?php echo rel_url('styles/design-system.css'); ?>">
</head>
<body>
    <header>
        <img src="<?php echo rel_url('images/logo.png'); ?>" alt="Logo">
        <nav>
            <a href="<?php echo lang_url(''); ?>"><?php echo t('nav.home'); ?></a>
            <a href="<?php echo lang_url('about'); ?>"><?php echo t('nav.about'); ?></a>
        </nav>
        
        <!-- Language Switcher -->
        <?php foreach (get_language_switcher_data() as $lang): ?>
            <a href="<?php echo $lang['url']; ?>"><?php echo $lang['name']; ?></a>
        <?php endforeach; ?>
    </header>
    
    <main>
        <h1><?php echo t('welcome.title'); ?></h1>
        <p><?php echo t('welcome.message', ['name' => $userName]); ?></p>
    </main>
    
    <script src="<?php echo rel_url('js/app.js'); ?>"></script>
</body>
</html>
```

## Troubleshooting
### Issue: CSS/JS not loading
Solution: Make sure you're using rel_url() for assets, not lang_url()
```php
<!-- WRONG -->
<link rel="stylesheet" href="<?php echo lang_url('styles/main.css'); ?>">

<!-- CORRECT -->
<link rel="stylesheet" href="<?php echo rel_url('styles/main.css'); ?>">
```

### Issue: Language not detected from URL
Solution: Check that .htaccess rewrite rules are active
```php
bash# Test if mod_rewrite is enabled
apache2ctl -M | grep rewrite
```

### Issue: Translations not showing
```php
Solution: Verify language files exist and return correct format
// languages/en.php must return an array
return [
    'key' => 'value'
];


// NOT just defining an array
$translations = ['key' => 'value'];
```

### Issue: Works at root but not in subdirectory
Solution: Update RewriteBase in .htaccess
```apache
apache
# For subdirectory installation
RewriteBase /mysite/
```

###Issue: Language switcher doesn't maintain current page
Solution: Use switch_language_url() function
```php
<!-- WRONG -->
<a href="<?php echo lang_url('', 'es'); ?>">Español</a>

<!-- CORRECT -->
<a href="<?php echo switch_language_url('es'); ?>">Español</a>
```


---

## Performance Tips

1. **Cache translations:** Load translation files once per request
2. **Use OPcache:** Enable PHP OPcache for better performance
3. **CDN for assets:** Serve static files from CDN
4. **Minimize language files:** Only include strings actually used
5. **Database indexes:** If storing translations in DB, index language columns

---

## Security Considerations

1. **Validate language codes:** Always check against SUPPORTED_LANGS array
2. **Escape output:** Use `htmlspecialchars()` for user-generated content
3. **Sanitize paths:** Validate `$_GET['q']` to prevent path traversal
4. **CSRF protection:** Add tokens to forms
5. **SQL injection:** Use prepared statements for database queries

---

## Next Steps

1. **Add more languages:** Simply create new PHP files in `languages/` directory
2. **Database content translation:** Implement content translation tables
3. **Translation management:** Build admin interface for managing translations
4. **Import/Export:** Add functionality to export/import translation files
5. **Professional translations:** Integrate with translation services (optional)
6. **RTL support:** Add right-to-left language support for Arabic, Hebrew
7. **Date/Number formatting:** Implement locale-specific formatting

---

