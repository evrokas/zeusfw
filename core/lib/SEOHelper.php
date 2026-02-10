<?php

/**
 * SEOHelper - SEO and metadata utilities for multilingual content
 *
 * Provides methods for generating proper SEO tags for multilingual websites:
 * - hreflang alternate language links
 * - Canonical URLs
 * - Open Graph locale tags
 * - Language-specific meta tags
 *
 * @package Zeus Framework
 * @version 1.0
 * @date February 2026
 */
class SEOHelper {

    private $kernel;
    private $config;
    private $supportedLanguages;
    private $currentLanguage;
    private $baseUrl;

    /**
     * Constructor
     *
     * @param Kernel $kernel - The kernel instance
     */
    public function __construct($kernel) {
        $this->kernel = $kernel;
        $this->config = $kernel->getConfig();
        $this->currentLanguage = $kernel->getCurrentLanguage();
        $this->supportedLanguages = $this->getSupportedLanguages();
        $this->baseUrl = $this->getBaseUrl();
    }

    /**
     * Get supported languages from configuration
     *
     * @return array Array of language codes
     */
    private function getSupportedLanguages() {
        $languages = $this->config['languages'] ?? [];

        if (empty($languages)) {
            return ['el', 'en'];
        }

        // Extract language codes from config
        $codes = [];
        foreach ($languages as $code => $details) {
            if (is_array($details)) {
                // New format: check if enabled
                if ($details['enabled'] ?? true) {
                    $codes[] = $code;
                }
            } else {
                // Old format: just use the code
                $codes[] = is_numeric($code) ? $details : $code;
            }
        }

        return $codes;
    }

    /**
     * Get base URL from configuration or server
     *
     * @return string Base URL (e.g., 'https://example.com')
     */
    private function getBaseUrl() {
        // Try to get from config first
        if (isset($this->config['site']['base_url'])) {
            return rtrim($this->config['site']['base_url'], '/');
        }

        // Build from server variables
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * Get current page path (without language prefix or parameter)
     *
     * @return string Current path
     */
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

    /**
     * Generate hreflang tags for all alternate languages
     *
     * Returns HTML link tags with rel="alternate" and hreflang attributes
     * for each supported language, pointing to the same page in different languages.
     *
     * @return string HTML link tags for hreflang
     */
    public function generateHreflangTags() {
        $currentPath = $this->getCurrentPath();
        $tags = [];

        // Generate tag for each supported language
        foreach ($this->supportedLanguages as $lang) {
            $url = $this->getUrlForLanguage($lang, $currentPath);
            $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($url) . '">';
        }

        // Add x-default tag (usually points to default language or language selector)
        $defaultLang = $this->config['language_detection']['default'] ?? 'el';
        $defaultUrl = $this->getUrlForLanguage($defaultLang, $currentPath);
        $tags[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl) . '">';

        return implode("\n    ", $tags);
    }

    /**
     * Generate canonical URL tag
     *
     * Returns the canonical URL for the current page in the current language.
     * Helps prevent duplicate content issues.
     *
     * @return string HTML link tag for canonical URL
     */
    public function generateCanonicalTag() {
        $currentPath = $this->getCurrentPath();
        $canonicalUrl = $this->getUrlForLanguage($this->currentLanguage, $currentPath);

        return '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">';
    }

    /**
     * Get URL for a specific language
     *
     * @param string $lang Language code
     * @param string|null $path Optional path (uses current path if null)
     * @return string Full URL with language path prefix
     */
    public function getUrlForLanguage($lang, $path = null) {
        if ($path === null) {
            $path = $this->getCurrentPath();
        }

        // Parse path and query
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

    /**
     * Get language-specific metadata
     *
     * Returns an array of metadata for the current language including:
     * - HTML lang attribute value
     * - Open Graph locale
     * - Language name and native name
     *
     * @return array Language metadata
     */
    public function getLanguageMetadata() {
        $langConfig = $this->config['languages'][$this->currentLanguage] ?? [];

        return [
            'code' => $this->currentLanguage,
            'html_lang' => $this->getHtmlLangCode($this->currentLanguage),
            'og_locale' => $this->getOpenGraphLocale($this->currentLanguage),
            'name' => $langConfig['name'] ?? $this->currentLanguage,
            'native_name' => $langConfig['native_name'] ?? $this->currentLanguage,
            'direction' => $langConfig['direction'] ?? 'ltr',
        ];
    }

    /**
     * Get HTML lang attribute value
     *
     * Converts language code to proper HTML lang format (e.g., 'el' -> 'el', 'en' -> 'en')
     *
     * @param string $lang Language code
     * @return string HTML lang attribute value
     */
    private function getHtmlLangCode($lang) {
        // For most cases, the language code is the same as HTML lang
        // Special cases can be added here if needed
        $mapping = [
            'el' => 'el',  // Greek
            'en' => 'en',  // English
            'gr' => 'el',  // Map 'gr' to 'el' (correct Greek code)
        ];

        return $mapping[$lang] ?? $lang;
    }

    /**
     * Get Open Graph locale code
     *
     * Converts language code to Open Graph locale format (e.g., 'en' -> 'en_US', 'el' -> 'el_GR')
     *
     * @param string $lang Language code
     * @return string Open Graph locale
     */
    private function getOpenGraphLocale($lang) {
        // Map language codes to Open Graph locales
        $mapping = [
            'el' => 'el_GR',  // Greek (Greece)
            'en' => 'en_US',  // English (US)
            'gr' => 'el_GR',  // Map 'gr' to 'el_GR'
        ];

        return $mapping[$lang] ?? $lang . '_' . strtoupper($lang);
    }

    /**
     * Get all alternate language URLs
     *
     * Returns an array of alternate language information for template use
     *
     * @return array Array of language info with 'code', 'name', 'url'
     */
    public function getAlternateLanguages() {
        $currentPath = $this->getCurrentPath();
        $alternates = [];

        foreach ($this->supportedLanguages as $lang) {
            $langConfig = $this->config['languages'][$lang] ?? [];

            $alternates[] = [
                'code' => $lang,
                'name' => $langConfig['name'] ?? $lang,
                'native_name' => $langConfig['native_name'] ?? $lang,
                'url' => $this->getUrlForLanguage($lang, $currentPath),
                'is_current' => ($lang === $this->currentLanguage),
            ];
        }

        return $alternates;
    }

    /**
     * Generate Open Graph locale meta tags
     *
     * Returns HTML meta tags for Open Graph locale and alternate locales
     *
     * @return string HTML meta tags for Open Graph locale
     */
    public function generateOpenGraphLocaleTags() {
        $tags = [];
        $currentLocale = $this->getOpenGraphLocale($this->currentLanguage);

        // Current locale
        $tags[] = '<meta property="og:locale" content="' . htmlspecialchars($currentLocale) . '">';

        // Alternate locales
        foreach ($this->supportedLanguages as $lang) {
            if ($lang !== $this->currentLanguage) {
                $locale = $this->getOpenGraphLocale($lang);
                $tags[] = '<meta property="og:locale:alternate" content="' . htmlspecialchars($locale) . '">';
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * Get current language code
     *
     * @return string Current language code
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }

    /**
     * Get supported language codes
     *
     * @return array Supported language codes
     */
    public function getSupportedLanguageCodes() {
        return $this->supportedLanguages;
    }
}
