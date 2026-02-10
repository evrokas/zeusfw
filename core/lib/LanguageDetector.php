<?php

/**
 * LanguageDetector - Intelligent multilingual language detection
 *
 * Detects user language preference from multiple sources with configurable priority:
 * - Session: Current session language
 * - Cookie: Persistent user preference
 * - User profile: Database-stored preference for authenticated users
 * - Query parameter: Manual override via ?lang=en
 * - Browser: HTTP Accept-Language header
 * - Default: Configured fallback language
 *
 * @package Zeus Framework
 * @version 1.0
 */
class LanguageDetector {

    private $kernel;
    private $config;
    private $detectionPriority;
    private $defaultLanguage;
    private $supportedLanguages;

    /**
     * Constructor
     *
     * @param Kernel $kernel - The kernel instance
     */
    public function __construct($kernel) {
        $this->kernel = $kernel;
        $this->config = $kernel->getConfig();
        $this->loadConfiguration();
    }

    /**
     * Load language detection configuration from settings.info.yaml
     */
    private function loadConfiguration() {
        // Get language detection config
        $langDetection = $this->config['language_detection'] ?? [];

        // Set default language
        $this->defaultLanguage = $langDetection['default'] ?? 'el';

        // Set detection priority (default order if not configured)
        $this->detectionPriority = $langDetection['priority'] ?? [
            'session',
            'cookie',
            'user',
            'query',
            'browser',
            'default'
        ];

        // Get supported languages from config
        $this->supportedLanguages = $this->getSupportedLanguagesFromConfig();
    }

    /**
     * Extract supported languages from configuration
     *
     * @return array - Array of supported language codes
     */
    private function getSupportedLanguagesFromConfig() {
        $languages = $this->config['languages'] ?? [];

        // New format: associative array with keys as language codes
        // Old format: simple array ['en', 'el']
        // Using array_keys() works for both formats:
        // - New: array_keys(['en' => [...], 'el' => [...]]) = ['en', 'el']
        // - Old: array_keys(['en', 'el']) = [0, 1] but we check if numeric
        if (is_array($languages) && !empty($languages)) {
            $keys = array_keys($languages);
            // If first key is numeric, it's old format - return values instead
            if (is_numeric($keys[0])) {
                return $languages;
            }
            // New format - filter by enabled status
            $supported = [];
            foreach ($languages as $code => $details) {
                if (is_array($details)) {
                    // Only include if enabled (default to true if not specified)
                    if ($details['enabled'] ?? true) {
                        $supported[] = $code;
                    }
                } else {
                    // Simple value, include it
                    $supported[] = $code;
                }
            }
            return $supported;
        }

        // Fallback if no languages configured
        return ['el', 'en'];
    }

    /**
     * Detect language using configured priority
     *
     * @return string - Detected language code
     */
    public function detect() {
        foreach ($this->detectionPriority as $method) {
            $lang = null;

            switch ($method) {
                case 'url':
                    $lang = $this->detectFromUrl();
                    break;

                case 'session':
                    $lang = $this->detectFromSession();
                    break;

                case 'cookie':
                    $lang = $this->detectFromCookie();
                    break;

                case 'user':
                    $lang = $this->detectFromUserProfile();
                    break;

                case 'query':
                    $lang = $this->detectFromQueryParameter();
                    break;

                case 'browser':
                    $lang = $this->detectFromBrowser();
                    break;

                case 'default':
                    $lang = $this->getDefaultLanguage();
                    break;
            }

            // If a valid language was detected, return it
            if ($lang && $this->isValidLanguage($lang)) {
                return $lang;
            }
        }

        // Final fallback
        return $this->getDefaultLanguage();
    }

    /**
     * Detect language from session
     *
     * @return string|null - Language code or null
     */
    private function detectFromSession() {
        return $_SESSION['CURRENT_LANGUAGE'] ?? null;
    }

    /**
     * Detect language from cookie
     *
     * @return string|null - Language code or null
     */
    private function detectFromCookie() {
        return $_COOKIE['user_lang'] ?? null;
    }

    /**
     * Detect language from user profile (database)
     *
     * @return string|null - Language code or null
     */
    private function detectFromUserProfile() {
        // Check if user is logged in
        if (!SecurityClass::userLoggedIn()) {
            return null;
        }

        // Get current user
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return null;
            }

            // Load user entity
            $user = usersClass::sgetById($userId);
            if ($user && method_exists($user, 'getLanguage')) {
                $lang = $user->getLanguage();
                return $lang ?: null;
            }
        } catch (Exception $e) {
            // User profile language detection failed, continue to next method
            return null;
        }

        return null;
    }

    /**
     * Detect language from query parameter (?lang=en)
     *
     * When a valid language is found in the query parameter, it is persisted
     * to both session and cookie to maintain the selection across page loads.
     *
     * @return string|null - Language code or null
     */
    private function detectFromQueryParameter() {
        $lang = $_GET['lang'] ?? null;

        if ($lang && $this->isValidLanguage($lang)) {
            // Persist the language selection to session and cookie
            $this->persistLanguageSelection($lang);
        }

        return $lang;
    }

    /**
     * Persist language selection to session and cookie
     *
     * @param string $lang - Language code to persist
     */
    private function persistLanguageSelection($lang) {
        // Set session variable
        $_SESSION['CURRENT_LANGUAGE'] = $lang;

        // Set cookie for 1 year
        $cookieExpiry = time() + (365 * 24 * 60 * 60);
        setcookie('user_lang', $lang, $cookieExpiry, '/', '', false, true);
    }

    /**
     * Detect language from URL path prefix (/en/page or /el/page)
     *
     * Extracts language code from the first segment of the URL path
     * Examples:
     * - /en/admin → 'en'
     * - /el/patients/list → 'el'
     * - /admin → null (no language prefix)
     *
     * @return string|null - Language code or null
     */
    private function detectFromUrl() {
        // Get the request URI
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Remove query string
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (!$path) {
            return null;
        }

        // Remove leading slash and split by /
        $path = ltrim($path, '/');
        $segments = explode('/', $path);

        // Check if first segment is a valid language code
        if (!empty($segments[0]) && strlen($segments[0]) === 2) {
            $possibleLang = strtolower($segments[0]);

            // Only return if it's a valid language code
            if ($this->isValidLanguage($possibleLang)) {
                return $possibleLang;
            }
        }

        return null;
    }

    /**
     * Detect language from browser Accept-Language header
     *
     * @return string|null - Language code or null
     */
    private function detectFromBrowser() {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

        // Parse Accept-Language header
        // Format: "en-US,en;q=0.9,gr;q=0.8"
        $languages = [];

        preg_match_all('/([a-z]{2})(?:-[A-Z]{2})?(?:;q=([0-9.]+))?/',
                       $acceptLanguage,
                       $matches,
                       PREG_SET_ORDER);

        foreach ($matches as $match) {
            $code = $match[1];
            $quality = isset($match[2]) ? (float)$match[2] : 1.0;
            $languages[$code] = $quality;
        }

        // Sort by quality value (highest first)
        arsort($languages);

        // Find first supported language
        foreach ($languages as $code => $quality) {
            if ($this->isValidLanguage($code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Detect language from IP geolocation (optional feature)
     *
     * @return string|null - Language code or null
     */
    private function detectFromGeoIP() {
        // This is a placeholder for optional geographic detection
        // Can be implemented using services like MaxMind GeoIP2, ip-api.com, etc.
        // Example:
        // - Detect user's country from IP address
        // - Map country to default language
        // - Return language code if supported

        return null;
    }

    /**
     * Get default language from configuration
     *
     * @return string - Default language code
     */
    public function getDefaultLanguage() {
        return $this->defaultLanguage;
    }

    /**
     * Get list of supported languages
     *
     * @return array - Array of language codes
     */
    public function getSupportedLanguages() {
        return $this->supportedLanguages;
    }

    /**
     * Check if a language code is valid and supported
     *
     * @param string $lang - Language code to validate
     * @return bool - True if valid, false otherwise
     */
    public function isValidLanguage($lang) {
        if (empty($lang) || !is_string($lang)) {
            return false;
        }

        // Normalize to lowercase
        $lang = strtolower(trim($lang));

        // Check if language is in supported list
        return in_array($lang, $this->supportedLanguages);
    }

    /**
     * Get language name (native or English)
     *
     * @param string $code - Language code
     * @param bool $native - Return native name (true) or English name (false)
     * @return string|null - Language name or null
     */
    public function getLanguageName($code, $native = true) {
        $languages = $this->config['languages'] ?? [];

        // Handle old format (simple array)
        if (!isset($languages[$code])) {
            return null;
        }

        // Handle new format (associative array with details)
        if (is_array($languages[$code])) {
            if ($native) {
                return $languages[$code]['native_name'] ?? $languages[$code]['name'] ?? null;
            } else {
                return $languages[$code]['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get full language configuration
     *
     * @param string $code - Language code
     * @return array|null - Language configuration or null
     */
    public function getLanguageConfig($code) {
        $languages = $this->config['languages'] ?? [];

        if (!isset($languages[$code])) {
            return null;
        }

        if (is_array($languages[$code])) {
            return $languages[$code];
        }

        return null;
    }

    /**
     * Extract language prefix from URL path
     *
     * Returns array with ['lang' => 'en', 'path' => '/admin'] or null if no prefix
     *
     * @param string $path - URL path
     * @return array|null - ['lang' => 'code', 'path' => 'remaining_path'] or null
     */
    public function extractLanguageFromPath($path) {
        // Remove leading slash and split by /
        $path = ltrim($path, '/');
        $segments = explode('/', $path);

        // Check if first segment is a valid language code
        if (!empty($segments[0]) && strlen($segments[0]) === 2) {
            $possibleLang = strtolower($segments[0]);

            if ($this->isValidLanguage($possibleLang)) {
                // Remove language segment and rebuild path
                array_shift($segments);
                $remainingPath = '/' . implode('/', $segments);

                return [
                    'lang' => $possibleLang,
                    'path' => $remainingPath
                ];
            }
        }

        return null;
    }

    /**
     * Add language prefix to URL path
     *
     * @param string $path - URL path
     * @param string $lang - Language code
     * @return string - Path with language prefix
     */
    public function addLanguageToPath($path, $lang) {
        if (!$this->isValidLanguage($lang)) {
            return $path;
        }

        // Remove leading slash, add language, re-add slash
        $path = ltrim($path, '/');
        return '/' . $lang . '/' . $path;
    }

    /**
     * Remove language prefix from URL path
     *
     * @param string $path - URL path
     * @return string - Path without language prefix
     */
    public function removeLanguageFromPath($path) {
        $extracted = $this->extractLanguageFromPath($path);

        if ($extracted) {
            return $extracted['path'];
        }

        return $path;
    }
}
