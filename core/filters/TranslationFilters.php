<?php

/**
 * TranslationFilters - ZETEM Template Translation Filters
 *
 * Provides translation filters for ZETEM templates with support for:
 * - Key-based translations from YAML files
 * - Array-based language-specific values (backward compatible)
 * - Parameter replacement
 * - Pluralization
 * - Context-aware translations
 *
 * @package Zeus Framework
 * @version 1.0
 */
class TranslationFilters {

    /**
     * Register all translation filters with the template engine
     *
     * This should be called during application bootstrap
     */
    public static function register() {
        // Core translation filters
        TemplateFilter::register('t', [self::class, 'filterTranslate']);
        TemplateFilter::register('translate', [self::class, 'filterTranslate']);

        // Array-based translation (backward compatible)
        TemplateFilter::register('lang_text', [self::class, 'filterLangText']);

        // Translation with parameters
        TemplateFilter::register('t_params', [self::class, 'filterTranslateParams']);

        // Pluralization
        TemplateFilter::register('t_plural', [self::class, 'filterTranslatePlural']);

        // Context-aware translation
        TemplateFilter::register('t_context', [self::class, 'filterTranslateContext']);
    }

    /**
     * Main translation filter
     *
     * Translates a key using the multilingual system (YAML files + database)
     *
     * Usage in templates:
     * - {{ 'nav.home' | t }}
     * - {{ 'dashboard.welcome' | t }}
     *
     * @param mixed $value Translation key (string) or language array
     * @param array $args Optional: [0] => language code
     * @return string Translated text
     */
    public static function filterTranslate($value, array $args = []) {
        global $kernel;

        // If value is null or empty, return as-is
        if ($value === null || $value === '') {
            return $value;
        }

        // Handle array-based translations (backward compatibility)
        if (is_array($value)) {
            return self::filterLangText($value, $args);
        }

        // String key - use translation system
        $lang = $args[0] ?? null;

        // Use t() function if available (integrates with MultilingualManager)
        if (function_exists('t')) {
            return t((string)$value, [], $lang);
        }

        // Fallback: return the key itself
        return $value;
    }

    /**
     * Array-based language text filter (backward compatible)
     *
     * Handles language-keyed arrays: ['en' => 'Home', 'el' => 'Αρχική']
     *
     * Usage in templates:
     * - {{ $menuItem['title'] | lang_text }}
     * - {{ $route['name'] | lang_text }}
     *
     * @param mixed $value Language array or string
     * @param array $args Not used
     * @return string Translated text
     */
    public static function filterLangText($value, array $args = []) {
        global $kernel;

        // If not an array, return as-is
        if (!is_array($value)) {
            return is_string($value) ? $value : '';
        }

        // Use getLangText() function if available
        if (function_exists('getLangText')) {
            return getLangText($value);
        }

        // Fallback: try to get current language
        if ($kernel && method_exists($kernel, 'getCurrentLanguage')) {
            $lang = $kernel->getCurrentLanguage();
            if (isset($value[$lang])) {
                return $value[$lang];
            }
        }

        // Return first value as fallback
        return reset($value) ?: '';
    }

    /**
     * Translation with parameter replacement
     *
     * Replaces {key} placeholders in the translated string
     *
     * Usage in templates:
     * - {{ 'dashboard.welcome' | t_params({'name': $userName}) }}
     * - {{ 'messages.delete_confirm' | t_params({'item': $itemName}) }}
     *
     * @param mixed $value Translation key
     * @param array $args [0] => parameters array, [1] => language code (optional)
     * @return string Translated text with replaced parameters
     */
    public static function filterTranslateParams($value, array $args = []) {
        // First argument is parameters array
        $params = $args[0] ?? [];

        // Second argument is optional language code
        $lang = $args[1] ?? null;

        // Translate the key
        $translated = self::filterTranslate($value, $lang ? [$lang] : []);

        // Replace parameters if provided
        if (is_array($params) && !empty($params)) {
            foreach ($params as $key => $val) {
                $translated = str_replace('{' . $key . '}', (string)$val, $translated);
                $translated = str_replace(':' . $key, (string)$val, $translated);
            }
        }

        return $translated;
    }

    /**
     * Pluralization filter
     *
     * Returns appropriate plural form based on count
     *
     * Usage in templates:
     * - {{ 'patient' | t_plural(5) }}
     * - {{ 'item' | t_plural($count) }}
     * - {{ 'appointment' | t_plural($total, {'date': $date}) }}
     *
     * @param mixed $value Translation key (without _plural suffix)
     * @param array $args [0] => count, [1] => parameters (optional), [2] => language (optional)
     * @return string Translated plural form
     */
    public static function filterTranslatePlural($value, array $args = []) {
        // Extract arguments
        $count = isset($args[0]) ? (int)$args[0] : 0;
        $params = $args[1] ?? [];
        $lang = $args[2] ?? null;

        // Use t_plural() function if available
        if (function_exists('t_plural')) {
            return t_plural((string)$value, $count, is_array($params) ? $params : [], $lang);
        }

        // Fallback: simple English pluralization
        $key = (string)$value;
        if ($count === 1) {
            $translated = self::filterTranslate($key . '_singular', [$lang]);
            if ($translated === $key . '_singular') {
                $translated = self::filterTranslate($key, [$lang]);
            }
        } else {
            $translated = self::filterTranslate($key . '_plural', [$lang]);
            if ($translated === $key . '_plural') {
                // Fallback: add 's' to singular
                $singular = self::filterTranslate($key, [$lang]);
                $translated = $singular . 's';
            }
        }

        // Add count to parameters
        if (!is_array($params)) {
            $params = [];
        }
        $params['count'] = $count;

        // Replace parameters
        foreach ($params as $pkey => $pval) {
            $translated = str_replace('{' . $pkey . '}', (string)$pval, $translated);
        }

        return $translated;
    }

    /**
     * Context-aware translation filter
     *
     * Disambiguates words with multiple meanings
     *
     * Usage in templates:
     * - {{ 'bank' | t_context('financial') }}  → "Bank (Financial Institution)"
     * - {{ 'bank' | t_context('river') }}       → "River Bank"
     *
     * @param mixed $value Translation key
     * @param array $args [0] => context, [1] => parameters (optional), [2] => language (optional)
     * @return string Context-specific translation
     */
    public static function filterTranslateContext($value, array $args = []) {
        $context = $args[0] ?? '';
        $params = $args[1] ?? [];
        $lang = $args[2] ?? null;

        // Use t_context() function if available
        if (function_exists('t_context')) {
            return t_context((string)$value, $context, is_array($params) ? $params : [], $lang);
        }

        // Fallback: try key@context format
        $contextKey = $value . '@' . $context;
        $translated = self::filterTranslate($contextKey, [$lang]);

        // If context-specific not found, use regular translation
        if ($translated === $contextKey) {
            $translated = self::filterTranslate($value, [$lang]);
        }

        // Replace parameters if provided
        if (is_array($params) && !empty($params)) {
            foreach ($params as $key => $val) {
                $translated = str_replace('{' . $key . '}', (string)$val, $translated);
            }
        }

        return $translated;
    }
}
