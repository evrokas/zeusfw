<?php

/**
 * MultilingualManager - File-based translation system
 *
 * Provides YAML-based translation files with advanced features:
 * - Nested key access with dot notation: t('dashboard.welcome.message')
 * - Parameter replacement: t('msg.hello', ['name' => 'John'])
 * - Pluralization: t_plural('item', $count)
 * - Context support: t_context('bank', 'financial')
 * - Memory caching for performance
 * - Fallback chain: specific language → default language → key itself
 *
 * @package Zeus Framework
 * @version 1.0
 */
class MultilingualManager {

    private $kernel;
    private $config;
    private $translationsCache = [];
    private $currentLanguage;
    private $defaultLanguage;
    private $translationsPath;

    /**
     * Constructor
     *
     * @param Kernel $kernel - The kernel instance
     */
    public function __construct($kernel) {
        $this->kernel = $kernel;
        $this->config = $kernel->getConfig();

        // Set translations path (can be overridden in config)
        $this->translationsPath = $this->config['translations_path'] ??
                                  $kernel->getbasepath() . '../config/translations/';

        // Ensure path ends with slash
        $this->translationsPath = rtrim($this->translationsPath, '/') . '/';

        // Get current and default languages
        $this->currentLanguage = $kernel->getCurrentLanguage();
        $this->defaultLanguage = $kernel->getDefaultLanguage();
    }

    /**
     * Load translations for a specific language
     *
     * @param string $lang - Language code
     * @return array - Translation array
     */
    private function loadTranslations($lang) {
        // Check if already cached
        if (isset($this->translationsCache[$lang])) {
            return $this->translationsCache[$lang];
        }

        $filePath = $this->translationsPath . $lang . '.yaml';

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->translationsCache[$lang] = [];
            return [];
        }

        try {
            // Parse YAML file
            $translations = yaml_parse_file($filePath);

            if (!is_array($translations)) {
                $translations = [];
            }

            // Cache the translations
            $this->translationsCache[$lang] = $translations;

            return $translations;
        } catch (Exception $e) {
            // Log error and return empty array
            error_log("MultilingualManager: Failed to load translations for language '{$lang}': " . $e->getMessage());
            $this->translationsCache[$lang] = [];
            return [];
        }
    }

    /**
     * Get a translation value by key using dot notation
     *
     * @param array $array - Translation array
     * @param string $key - Dot notation key (e.g., 'dashboard.welcome.message')
     * @return mixed - Translation value or null
     */
    private function getNestedValue($array, $key) {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $nestedKey) {
            if (!is_array($value) || !isset($value[$nestedKey])) {
                return null;
            }
            $value = $value[$nestedKey];
        }

        return $value;
    }

    /**
     * Replace parameters in translation string
     *
     * Supports both {key} and :key syntax
     *
     * @param string $translation - Translation string with placeholders
     * @param array $params - Parameters to replace
     * @return string - Translation with replaced parameters
     */
    private function replaceParameters($translation, $params = []) {
        if (empty($params) || !is_string($translation)) {
            return $translation;
        }

        foreach ($params as $key => $value) {
            // Support both {key} and :key syntax
            $translation = str_replace('{' . $key . '}', $value, $translation);
            $translation = str_replace(':' . $key, $value, $translation);
        }

        return $translation;
    }

    /**
     * Main translation function
     *
     * @param string $key - Translation key (supports dot notation)
     * @param array $params - Parameters for replacement
     * @param string|null $lang - Language code (null for current language)
     * @return string - Translated string
     */
    public function translate($key, $params = [], $lang = null) {
        // Use current language if not specified
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }

        $translation = null;

        // Get dictionary configuration
        $dictConfig = $this->config['dictionary'] ?? [];
        $preferDatabase = $dictConfig['prefer_database'] ?? false;

        // Check database first if preferred
        if ($preferDatabase && $this->shouldCheckDatabase()) {
            $translation = $this->translateFromDatabase($key, $lang);
        }

        // If not found, try file-based translations
        if ($translation === null) {
            // Load translations for the requested language
            $translations = $this->loadTranslations($lang);

            // Try to get the translation
            $translation = $this->getNestedValue($translations, $key);

            // If not found and not using default language, try default language
            if ($translation === null && $lang !== $this->defaultLanguage) {
                $defaultTranslations = $this->loadTranslations($this->defaultLanguage);
                $translation = $this->getNestedValue($defaultTranslations, $key);
            }
        }

        // If still not found and database not checked yet, check database (if configured)
        if ($translation === null && !$preferDatabase && $this->shouldCheckDatabase()) {
            $translation = $this->translateFromDatabase($key, $lang);
        }

        // Final fallback: return the key itself
        if ($translation === null) {
            $translation = $key;
        }

        // Replace parameters if provided
        if (!empty($params) && is_array($params)) {
            $translation = $this->replaceParameters($translation, $params);
        }

        return $translation;
    }

    /**
     * Translate with pluralization support
     *
     * @param string $key - Translation key
     * @param int $count - Count for pluralization
     * @param array $params - Additional parameters
     * @param string|null $lang - Language code
     * @return string - Translated string with correct plural form
     */
    public function translatePlural($key, $count, $params = [], $lang = null) {
        // Use current language if not specified
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }

        // Load translations
        $translations = $this->loadTranslations($lang);

        // Try to get plural forms
        $pluralKey = $key . '_plural';
        $pluralForms = $this->getNestedValue($translations, $pluralKey);

        // If plural forms exist as array
        if (is_array($pluralForms)) {
            // Get the appropriate form based on count
            $form = $this->getPluralForm($count, $lang);

            if (isset($pluralForms[$form])) {
                $translation = $pluralForms[$form];
            } else {
                // Fallback to first form
                $translation = reset($pluralForms);
            }
        } else {
            // Simple pluralization: check for singular/plural keys
            if ($count === 1) {
                $translation = $this->getNestedValue($translations, $key . '_singular')
                            ?? $this->getNestedValue($translations, $key);
            } else {
                $translation = $this->getNestedValue($translations, $key . '_plural')
                            ?? $this->getNestedValue($translations, $key);
            }
        }

        // Fallback to regular translation
        if ($translation === null) {
            $translation = $this->translate($key, $params, $lang);
        }

        // Add count to parameters
        $params['count'] = $count;

        // Replace parameters
        $translation = $this->replaceParameters($translation, $params);

        return $translation;
    }

    /**
     * Get plural form index based on language rules
     *
     * @param int $count - The count
     * @param string $lang - Language code
     * @return string - Plural form key ('zero', 'one', 'few', 'many', 'other')
     */
    private function getPluralForm($count, $lang) {
        // Simplified plural rules
        // For production, use more comprehensive rules based on CLDR

        switch ($lang) {
            case 'en':
                // English: one/other
                return ($count === 1) ? 'one' : 'other';

            case 'el':
                // Greek (Ελληνικά): one/other
                return ($count === 1) ? 'one' : 'other';

            default:
                // Default: one/other
                return ($count === 1) ? 'one' : 'other';
        }
    }

    /**
     * Translate with context support
     *
     * Context helps disambiguate words with multiple meanings
     * Example: t_context('bank', 'financial') vs t_context('bank', 'river')
     *
     * @param string $key - Translation key
     * @param string $context - Context identifier
     * @param array $params - Parameters for replacement
     * @param string|null $lang - Language code
     * @return string - Translated string
     */
    public function translateContext($key, $context, $params = [], $lang = null) {
        // Build context key
        $contextKey = $key . '@' . $context;

        // Try to translate with context
        $translation = $this->translate($contextKey, $params, $lang);

        // If context-specific translation not found, fallback to regular translation
        if ($translation === $contextKey) {
            $translation = $this->translate($key, $params, $lang);
        }

        return $translation;
    }

    /**
     * Check if database dictionary should be checked
     *
     * @return bool
     */
    private function shouldCheckDatabase() {
        $dictConfig = $this->config['dictionary'] ?? [];
        // Check if fallback_to_file is true (allows checking database as fallback)
        // OR if prefer_database is true (prioritizes database)
        return ($dictConfig['fallback_to_file'] ?? false) || ($dictConfig['prefer_database'] ?? false);
    }

    /**
     * Translate from database dictionary
     *
     * @param string $key - Translation key
     * @param string $lang - Language code (optional, uses current language if not provided)
     * @return string|null - Translation or null
     */
    private function translateFromDatabase($key, $lang = null) {
        // Check if dictionaryClassEx exists
        if (!class_exists('dictionaryClassEx')) {
            return null;
        }

        try {
            // Store current language temporarily
            $originalLang = $this->kernel->getCurrentLanguage();

            // Set the language if provided
            if ($lang !== null && $lang !== $originalLang) {
                $this->kernel->setCurrentLanguage($lang);
            }

            // Call translateToken (it uses kernel's current language internally)
            $translation = dictionaryClassEx::translateToken($key);

            // Restore original language if we changed it
            if ($lang !== null && $lang !== $originalLang) {
                $this->kernel->setCurrentLanguage($originalLang);
            }

            // Return null if translation is same as key (not found)
            return ($translation === $key) ? null : $translation;

        } catch (Exception $e) {
            error_log("MultilingualManager: Database translation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reload translations (clear cache)
     *
     * @param string|null $lang - Specific language to reload, or null for all
     */
    public function reloadTranslations($lang = null) {
        if ($lang === null) {
            // Clear all cached translations
            $this->translationsCache = [];
        } else {
            // Clear specific language cache
            unset($this->translationsCache[$lang]);
        }
    }

    /**
     * Get all translations for a language
     *
     * @param string|null $lang - Language code (null for current)
     * @return array - All translations
     */
    public function getAllTranslations($lang = null) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }

        return $this->loadTranslations($lang);
    }

    /**
     * Check if a translation key exists
     *
     * @param string $key - Translation key
     * @param string|null $lang - Language code
     * @return bool - True if exists
     */
    public function hasTranslation($key, $lang = null) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }

        $translations = $this->loadTranslations($lang);
        $value = $this->getNestedValue($translations, $key);

        return $value !== null;
    }

    /**
     * Get current language
     *
     * @return string
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }

    /**
     * Set current language
     *
     * @param string $lang - Language code
     */
    public function setCurrentLanguage($lang) {
        $this->currentLanguage = $lang;
    }

    /**
     * Get default language
     *
     * @return string
     */
    public function getDefaultLanguage() {
        return $this->defaultLanguage;
    }

    /**
     * Get translations path
     *
     * @return string
     */
    public function getTranslationsPath() {
        return $this->translationsPath;
    }

    /**
     * Export translations to array (for debugging)
     *
     * @param string|null $lang - Language code
     * @return array - Flat array of key => translation
     */
    public function exportFlat($lang = null) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }

        $translations = $this->loadTranslations($lang);
        return $this->flattenArray($translations);
    }

    /**
     * Flatten nested array to dot notation keys
     *
     * @param array $array - Nested array
     * @param string $prefix - Key prefix
     * @return array - Flat array
     */
    private function flattenArray($array, $prefix = '') {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
