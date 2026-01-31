<?php
/**
 * TemplateSuggestion - Standalone class for handling template suggestions
 * 
 * Generates and manages template suggestions based on keywords, context, and patterns.
 */

if (!class_exists("TemplateSuggestion")) {

class TemplateSuggestion {
    
    /** @var array Available template files */
    private static $templateFiles = [];
    
    /** @var string Template file extension */
    private static $extension = '.zetem';
    
    /** @var string Separator for template name parts */
    private static $separator = '--';
    
    /** @var array Registered suggestion callbacks */
    private static $callbacks = [];
    
    /**
     * Set the available template files
     */
    public static function setTemplateFiles(array $files): void {
        self::$templateFiles = $files;
    }
    
    /**
     * Set template file extension
     */
    public static function setExtension(string $ext): void {
        self::$extension = $ext;
    }
    
    /**
     * Set separator for template name parts
     */
    public static function setSeparator(string $sep): void {
        self::$separator = $sep;
    }
    
    /**
     * Register a suggestion callback for a specific context
     */
    public static function registerCallback(string $context, callable $callback): void {
        self::$callbacks[$context] = $callback;
    }
    
    /**
     * Generate suggestions from keywords using power set combination
     * 
     * @param array $keywords Keywords to combine
     * @param string $prefix Base template name prefix
     * @return array Suggestions ordered from most specific to least specific
     */
    public static function fromKeywords(array $keywords, string $prefix = ''): array {
        $suggestions = [];
        $count = count($keywords);
        
        if ($count === 0) {
            return $prefix ? [$prefix] : [];
        }
        
        // Generate all non-empty subsets (power set minus empty set)
        for ($i = 1; $i < (1 << $count); $i++) {
            $subset = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i & (1 << $j)) {
                    $subset[] = $keywords[$j];
                }
            }
            $suggestion = implode('-', $subset);
            $suggestions[] = $prefix ? "{$prefix}" . self::$separator . "{$suggestion}" : $suggestion;
        }
        
        // Sort by number of elements (more specific first)
        usort($suggestions, function ($a, $b) {
            $countA = substr_count($a, '-');
            $countB = substr_count($b, '-');
            return $countB <=> $countA ?: strcmp($a, $b);
        });
        
        // Add base prefix if provided
        if ($prefix) {
            $suggestions[] = $prefix;
        }
        
        return $suggestions;
    }
    
    /**
     * Generate suggestions for a specific context using registered callback
     */
    public static function fromContext(string $context, array $args = []): array {
        if (!isset(self::$callbacks[$context])) {
            return [];
        }
        
        $suggestions = [];
        call_user_func(self::$callbacks[$context], $args, $suggestions);
        
        return $suggestions;
    }
    
    /**
     * Generate hierarchical suggestions from a path
     * 
     * @param string $path Path like "admin/users/edit"
     * @param string $prefix Optional prefix
     * @return array Suggestions from most specific to least specific
     */
    public static function fromPath(string $path, string $prefix = ''): array {
        $parts = array_filter(explode('/', trim($path, '/')));
        $suggestions = [];
        
        // Build suggestions from full path down to single parts
        while (!empty($parts)) {
            $suggestion = implode('-', $parts);
            $suggestions[] = $prefix ? "{$prefix}" . self::$separator . "{$suggestion}" : $suggestion;
            array_pop($parts);
        }
        
        // Add base prefix
        if ($prefix) {
            $suggestions[] = $prefix;
        }
        
        return $suggestions;
    }
    
    /**
     * Generate entity-based suggestions
     * 
     * @param string $entityType Entity type (e.g., 'node', 'user', 'block')
     * @param string|null $bundle Bundle/type (e.g., 'article', 'page')
     * @param string|null $viewMode View mode (e.g., 'full', 'teaser')
     * @param string|int|null $id Entity ID
     * @return array Suggestions from most specific to least specific
     */
    public static function forEntity(
        string $entityType, 
        ?string $bundle = null, 
        ?string $viewMode = null, 
        $id = null
    ): array {
        $suggestions = [];
        $base = $entityType;
        
        // Most specific: entity--bundle--viewmode--id
        if ($bundle && $viewMode && $id !== null) {
            $suggestions[] = "{$base}" . self::$separator . "{$bundle}" . self::$separator . "{$viewMode}" . self::$separator . "{$id}";
        }
        
        // entity--bundle--id
        if ($bundle && $id !== null) {
            $suggestions[] = "{$base}" . self::$separator . "{$bundle}" . self::$separator . "{$id}";
        }
        
        // entity--bundle--viewmode
        if ($bundle && $viewMode) {
            $suggestions[] = "{$base}" . self::$separator . "{$bundle}" . self::$separator . "{$viewMode}";
        }
        
        // entity--viewmode--id
        if ($viewMode && $id !== null) {
            $suggestions[] = "{$base}" . self::$separator . "{$viewMode}" . self::$separator . "{$id}";
        }
        
        // entity--bundle
        if ($bundle) {
            $suggestions[] = "{$base}" . self::$separator . "{$bundle}";
        }
        
        // entity--viewmode
        if ($viewMode) {
            $suggestions[] = "{$base}" . self::$separator . "{$viewMode}";
        }
        
        // entity--id
        if ($id !== null) {
            $suggestions[] = "{$base}" . self::$separator . "{$id}";
        }
        
        // Base entity type
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate field-based suggestions
     */
    public static function forField(
        string $fieldName,
        string $entityType,
        ?string $bundle = null,
        ?string $viewMode = null
    ): array {
        $suggestions = [];
        $base = "field";
        
        // field--fieldname--entitytype--bundle--viewmode
        if ($bundle && $viewMode) {
            $suggestions[] = "{$base}" . self::$separator . "{$fieldName}" . self::$separator . "{$entityType}" . self::$separator . "{$bundle}" . self::$separator . "{$viewMode}";
        }
        
        // field--fieldname--entitytype--bundle
        if ($bundle) {
            $suggestions[] = "{$base}" . self::$separator . "{$fieldName}" . self::$separator . "{$entityType}" . self::$separator . "{$bundle}";
        }
        
        // field--fieldname--entitytype
        $suggestions[] = "{$base}" . self::$separator . "{$fieldName}" . self::$separator . "{$entityType}";
        
        // field--fieldname
        $suggestions[] = "{$base}" . self::$separator . "{$fieldName}";
        
        // field
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate page-based suggestions
     */
    public static function forPage(string $route, ?string $theme = null): array {
        $suggestions = [];
        $base = "page";
        
        // Normalize route
        $routeParts = array_filter(explode('/', trim($route, '/')));
        $routeSlug = implode('-', $routeParts);
        
        // page--theme--route
        if ($theme && !empty($routeSlug)) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}" . self::$separator . "{$routeSlug}";
        }
        
        // Build hierarchical route suggestions
        while (!empty($routeParts)) {
            $slug = implode('-', $routeParts);
            $suggestions[] = "{$base}" . self::$separator . "{$slug}";
            array_pop($routeParts);
        }
        
        // page--theme
        if ($theme) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}";
        }
        
        // Base page
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate block-based suggestions
     */
    public static function forBlock(
        string $blockId,
        ?string $region = null,
        ?string $theme = null
    ): array {
        $suggestions = [];
        $base = "block";
        
        // block--theme--region--id
        if ($theme && $region) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}" . self::$separator . "{$region}" . self::$separator . "{$blockId}";
        }
        
        // block--region--id
        if ($region) {
            $suggestions[] = "{$base}" . self::$separator . "{$region}" . self::$separator . "{$blockId}";
        }
        
        // block--theme--id
        if ($theme) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}" . self::$separator . "{$blockId}";
        }
        
        // block--id
        $suggestions[] = "{$base}" . self::$separator . "{$blockId}";
        
        // block--region
        if ($region) {
            $suggestions[] = "{$base}" . self::$separator . "{$region}";
        }
        
        // Base block
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Find the best matching template from suggestions
     * 
     * @param array $suggestions Template suggestions (most specific first)
     * @return string|null Best matching template name or null if none found
     */
    public static function findBestMatch(array $suggestions): ?string {
        foreach ($suggestions as $suggestion) {
            $filename = $suggestion . self::$extension;
            if (isset(self::$templateFiles[$filename])) {
                return $filename;
            }
        }
        return null;
    }
    
    /**
     * Get template with suggestions metadata
     * 
     * @param array $suggestions Template suggestions
     * @return array [suggestions, matched_template]
     */
    public static function resolveWithMetadata(array $suggestions): array {
        $matched = self::findBestMatch($suggestions);
        return [$suggestions, $matched];
    }
    
    /**
     * Check if a specific template exists
     */
    public static function templateExists(string $name): bool {
        $filename = $name;
        if (!str_ends_with($filename, self::$extension)) {
            $filename .= self::$extension;
        }
        return isset(self::$templateFiles[$filename]);
    }
    
    /**
     * Get all available templates matching a pattern
     */
    public static function findTemplates(string $pattern): array {
        $matches = [];
        $regex = '/' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '/i';
        
        foreach (array_keys(self::$templateFiles) as $name) {
            if (preg_match($regex, $name)) {
                $matches[] = $name;
            }
        }
        
        return $matches;
    }
    
    /**
     * Debug helper: get all suggestions with their existence status
     */
    public static function debugSuggestions(array $suggestions): array {
        $debug = [];
        foreach ($suggestions as $suggestion) {
            $filename = $suggestion . self::$extension;
            $debug[] = [
                'suggestion' => $suggestion,
                'filename' => $filename,
                'exists' => isset(self::$templateFiles[$filename]),
                'path' => self::$templateFiles[$filename] ?? null
            ];
        }
        return $debug;
    }
    
    /**
     * Generate menu-specific suggestions
     */
    public static function forMenu(string $menuName, ?string $level = null): array {
        $suggestions = [];
        $base = "menu";
        
        // menu--name--level
        if ($level !== null) {
            $suggestions[] = "{$base}" . self::$separator . "{$menuName}" . self::$separator . "level-{$level}";
        }
        
        // menu--name
        $suggestions[] = "{$base}" . self::$separator . "{$menuName}";
        
        // Base menu
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate form-specific suggestions
     */
    public static function forForm(string $formId, ?string $mode = null): array {
        $suggestions = [];
        $base = "form";
        
        // form--id--mode
        if ($mode) {
            $suggestions[] = "{$base}" . self::$separator . "{$formId}" . self::$separator . "{$mode}";
        }
        
        // form--id
        $suggestions[] = "{$base}" . self::$separator . "{$formId}";
        
        // Base form
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate view-specific suggestions (for list views, grids, etc.)
     */
    public static function forView(
        string $viewId, 
        ?string $display = null,
        ?string $style = null
    ): array {
        $suggestions = [];
        $base = "view";
        
        // view--id--display--style
        if ($display && $style) {
            $suggestions[] = "{$base}" . self::$separator . "{$viewId}" . self::$separator . "{$display}" . self::$separator . "{$style}";
        }
        
        // view--id--display
        if ($display) {
            $suggestions[] = "{$base}" . self::$separator . "{$viewId}" . self::$separator . "{$display}";
        }
        
        // view--id--style
        if ($style) {
            $suggestions[] = "{$base}" . self::$separator . "{$viewId}" . self::$separator . "{$style}";
        }
        
        // view--id
        $suggestions[] = "{$base}" . self::$separator . "{$viewId}";
        
        // Base view
        $suggestions[] = $base;
        
        return $suggestions;
    }
}

} // end class_exists check
