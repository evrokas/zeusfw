<?php
/**
 * Template Suggestion System - Merged Version
 * 
 * Handles template discovery, suggestions, and resolution.
 * Provides a robust system for finding the best matching template
 * based on various criteria like entity type, view mode, field name, etc.
 * 
 * @author Evangelos Rokas
 * @version 2.0 (Merged)
 * @date January 2026
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
     * Set available template files
     * 
     * @param array $files Associative array of template_name => file_path
     */
    public static function setTemplateFiles(array $files): void {
        self::$templateFiles = $files;
    }
    
    /**
     * Get available template files
     * 
     * @return array
     */
    public static function getTemplateFiles(): array {
        return self::$templateFiles;
    }
    
    /**
     * Set template file extension
     * 
     * @param string $ext Extension including dot
     */
    public static function setExtension(string $ext): void {
        self::$extension = $ext;
    }
    
    /**
     * Get template file extension
     * 
     * @return string
     */
    public static function getExtension(): string {
        return self::$extension;
    }
    
    /**
     * Set separator for template name parts
     * 
     * @param string $sep Separator string
     */
    public static function setSeparator(string $sep): void {
        self::$separator = $sep;
    }
    
    /**
     * Get separator
     * 
     * @return string
     */
    public static function getSeparator(): string {
        return self::$separator;
    }
    
    /**
     * Register a suggestion callback for a specific context
     */
    public static function registerCallback(string $context, callable $callback): void {
        self::$callbacks[$context] = $callback;
    }

    /**
     * Check if a template exists
     * 
     * @param string $name Template name (with or without extension)
     * @return bool
     */
    public static function exists(string $name): bool {
        // Try with extension
        if (isset(self::$templateFiles[$name])) {
            return true;
        }
        
        // Try without extension
        $nameWithExt = $name . self::$extension;
        if (isset(self::$templateFiles[$nameWithExt])) {
            return true;
        }
        
        // Also check if name already has extension
        if (!str_ends_with($name, self::$extension)) {
            return isset(self::$templateFiles[$name . self::$extension]);
        }
        
        return false;
    }
    
    /**
     * Get template path if it exists
     * 
     * @param string $name Template name
     * @return string|null File path or null
     */
    public static function getPath(string $name): ?string {
        if (isset(self::$templateFiles[$name])) {
            return self::$templateFiles[$name];
        }
        
        $nameWithExt = $name . self::$extension;
        if (isset(self::$templateFiles[$nameWithExt])) {
            return self::$templateFiles[$nameWithExt];
        }
        
        return null;
    }
    
    /**
     * Generate suggestions from keywords using power set combinations
     * Most specific first (all keywords), then combinations, then base
     * 
     * @param array $keywords Keywords to combine
     * @param string $prefix Optional prefix
     * @return array Suggestions in priority order
     */
    public static function fromKeywords(array $keywords, string $prefix = ''): array {
        $suggestions = [];
        $keywords = array_filter(array_map('trim', $keywords));
        
        if (empty($keywords)) {
            if ($prefix) {
                $suggestions[] = $prefix . self::$extension;
            }
            return $suggestions;
        }
        
        // Generate power set and sort by specificity
        $powerSet = self::generatePowerSet($keywords);
        usort($powerSet, fn($a, $b) => count($b) - count($a));
        
        foreach ($powerSet as $subset) {
            if (empty($subset)) continue;
            
            $name = ($prefix ? $prefix . self::$separator : '') . implode(self::$separator, $subset);
            $suggestions[] = $name . self::$extension;
        }
        
        // Add base prefix as fallback
        if ($prefix) {
            $suggestions[] = $prefix . self::$extension;
        }
        
        return array_unique($suggestions);
    }
    
    /**
     * Generate power set of an array
     * 
     * @param array $arr Input array
     * @return array Power set
     */
    private static function generatePowerSet(array $arr): array {
        $result = [[]];
        
        foreach ($arr as $element) {
            $newSubsets = [];
            foreach ($result as $subset) {
                $newSubset = $subset;
                $newSubset[] = $element;
                $newSubsets[] = $newSubset;
            }
            $result = array_merge($result, $newSubsets);
        }
        
        return $result;
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
     * Generate suggestions from a path-like structure
     * e.g., 'admin/users/edit' generates: admin--users--edit, admin--users, admin
     * 
     * @param string $path Path string
     * @param string $delimiter Path delimiter
     * @return array Suggestions
     */
    public static function fromPath(string $path, string $delimiter = '/'): array {
        $suggestions = [];
        $parts = array_filter(explode($delimiter, $path));
        
        if (empty($parts)) {
            return $suggestions;
        }
        
        // Start with most specific
        while (!empty($parts)) {
            $name = implode(self::$separator, $parts);
            $suggestions[] = $name . self::$extension;
            array_pop($parts);
        }
        
        return $suggestions;
    }
    
    /**
     * Generate entity-specific template suggestions
     * Pattern: entity--bundle--viewmode--field
     * 
     * @param string $entityType Entity type (node, user, taxonomy_term, etc.)
     * @param string $bundle Bundle/content type
     * @param string $viewMode View mode (full, teaser, etc.)
     * @param string $id Id
     * @return array Suggestions
     */
    public static function forEntity(string $entityType, ?string $bundle = null, ?string $viewMode = null, $id = null): array {
        $suggestions = [];
        $base = $entityType;
        
        // Most specific: entity--bundle--viewmode--id
        if ($bundle && $viewMode && $id !== null) {
            $suggestions[] = $base . self::$separator . $bundle . self::$separator . $viewMode . self::$separator . $id . self::$extension;
        }
        if ($bundle && $viewMode) {
            $suggestions[] = $base . self::$separator . $bundle . self::$separator . $viewMode . self::$extension;
        }
        
        // entity--bundle--id
        if ($bundle && $id !== null) {
            $suggestions[] = $base . self::$separator . $bundle . self::$separator . $id . self::$extension;
        }
        
        // entity--viewmode--id
        if ($viewMode && $id !== null) {
            $suggestions[] = $base . self::$separator . $viewMode . self::$separator . $id . self::$extension;
        }
        
        // entity--bundle
        if ($bundle) {
            $suggestions[] = $base . self::$separator . $bundle . self::$extension;
        }
        
        // entity--viewmode
        if ($viewMode) {
            $suggestions[] = $base . self::$separator . $viewMode . self::$extension;
        }
        
        // entity--id
        if ($id !== null) {
            $suggestions[] = $base . self::$separator . $id . self::$extension;
        }
        
        // Base entity
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    /**
     * Generate field-specific template suggestions
     * 
     * @param string $fieldName Field name
     * @param string $fieldType Field type
     * @param string $entityType Entity type
     * @param string $bundle Bundle
     * @return array Suggestions
     */
    public static function forField(string $fieldName, ?string $entityType = null, ?string $bundle = null, ?string $viewMode = null): array {
        $suggestions = [];
        $base = 'field';
        
        // field--name--entitytype--bundle--viewmode
        if ($entityType && $bundle && $viewMode) {
            $suggestions[] = $base . self::$separator . $fieldName . self::$separator . $entityType . self::$separator . $bundle . self::$separator . $viewMode . self::$extension;
        }

        // field--fieldname--entitytype--bundle
        if ($entityType && $bundle) {
            $suggestions[] = $base . self::$separator . $fieldName . self::$separator . $entityType . self::$separator . $bundle . self::$extension;
        }
        
        // field--fieldname--entitytype
        if ($entityType) {
            $suggestions[] = $base . self::$separator . $fieldName . self::$separator . $entityType . self::$extension;
        }
        
        // field--fieldname
        $suggestions[] = $base . self::$separator . $fieldName . self::$extension;
        
        // field (base)
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    /**
     * Generate page-specific template suggestions
     * 
     * @param string $pageType Page type (front, node, user, etc.)
     * @param string $pageId Specific page ID or alias
     * @return array Suggestions
     */
    public static function forPage(string $pageType, ?string $pageId = null, ?string $theme = null): array {
        $suggestions = [];
        $base = 'page';
        
        // page--pagetype--pageid
        if ($theme && $pageId) {
            $suggestions[] = $base . self::$separator . $theme . self::$separator . $pageType . self::$separator . $pageId . self::$extension;
        }
        if ($pageId) {
            $suggestions[] = $base . self::$separator . $pageType . self::$separator . $pageId . self::$extension;
        }
        if ($theme) {
            $suggestions[] = $base . self::$separator . $theme . self::$separator . $pageType . self::$extension;
        }
        
        // page--pagetype
        $suggestions[] = $base . self::$separator . $pageType . self::$extension;
        
        // page (base)
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    /**
     * Generate block-specific template suggestions
     * 
     * @param string $blockType Block type
     * @param string $blockId Block ID
     * @param string $region Region name
     * @return array Suggestions
     */
    public static function forBlock(string $blockType, ?string $blockId = null, ?string $region = null, ?string $theme = null): array {
        $suggestions = [];
        $base = 'block';
        
        // block--blocktype--blockid--region
        if ($blockId && $region && $theme) {
            $suggestions[] = $base . self::$separator . $blockType . self::$separator . $blockId . self::$separator . $region . self::$separator . $theme . self::$extension;
        }

        // block--region--id
        if ($blockId && $region) {
            $suggestions[] = $base . self::$separator . $blockType . self::$separator . $blockId . self::$separator . $region . self::$extension;
        }
        
        // block--blocktype--blockid
        if ($blockId) {
            $suggestions[] = $base . self::$separator . $blockType . self::$separator . $blockId . self::$extension;
        }
        
        // block--blocktype--region
        if ($region) {
            $suggestions[] = $base . self::$separator . $blockType . self::$separator . $region . self::$extension;
        }
        
        // block--blocktype
        $suggestions[] = $base . self::$separator . $blockType . self::$extension;
        
        // block (base)
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }

    /**
     * Generate section-based suggestions
     * 
     * Handles hierarchical section paths with optional region prefix.
     * 
     * @param array $sectionPath Array of section names forming the path, e.g., ['content', 'main', 'article']
     * @param string|null $region Optional region name (e.g., 'region-content')
     * @return array Suggestions from most specific to least specific
     * 
     * Example:
     *   forSection(['content', 'main', 'article'], 'region-content')
     * Returns:
     *   - region-content--section--content-main-article
     *   - section--content-main-article
     *   - region-content--section--content-main
     *   - section--content-main
     *   - region-content--section--content
     *   - section--content
     *   - region-content--section
     *   - section
     */
    public static function forSection(array $sectionPath, ?string $region = null): array {
        $suggestions = [];
        $base = "section";
        
        // Build progressive section paths from most specific to least
        $sectionParts = [];
        $sectionVariants = [];
        
        foreach ($sectionPath as $sect) {
            $sectionParts[] = $sect;
            $sectionVariants[] = implode('-', $sectionParts);
        }
        
        // Reverse to get most specific first
        $sectionVariants = array_reverse($sectionVariants);
        
        // Generate suggestions for each section depth
        foreach ($sectionVariants as $sectionSlug) {
            // With region prefix (most specific)
            if ($region) {
                $suggestions[] = "{$region}" . self::$separator . "{$base}" . self::$separator . "{$sectionSlug}";
            }
            // Without region prefix
            $suggestions[] = "{$base}" . self::$separator . "{$sectionSlug}";
        }
        
        // Base suggestions (least specific)
        if ($region) {
            $suggestions[] = "{$region}" . self::$separator . "{$base}";
        }
        $suggestions[] = $base;
        
        return $suggestions;
    }
    
    /**
     * Generate region-based suggestions
     * 
     * @param string $region Region name
     * @param string|null $theme Optional theme name
     * @param string|null $page Optional page context
     * @return array Suggestions from most specific to least specific
     */
    public static function forRegion(string $region, ?string $theme = null, ?string $page = null): array {
        $suggestions = [];
        $base = "region";
        
        // region--theme--page--regionname
        if ($theme && $page) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}" . self::$separator . "{$page}" . self::$separator . "{$region}";
        }
        
        // region--page--regionname
        if ($page) {
            $suggestions[] = "{$base}" . self::$separator . "{$page}" . self::$separator . "{$region}";
        }
        
        // region--theme--regionname
        if ($theme) {
            $suggestions[] = "{$base}" . self::$separator . "{$theme}" . self::$separator . "{$region}";
        }
        
        // region--regionname
        $suggestions[] = "{$base}" . self::$separator . "{$region}";
        
        // region
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
            $filename = $suggestion;
            if (isset(self::$templateFiles[$filename])) {
                return $filename;
            }
            // Also try with extension appended
            if (!str_ends_with($filename, self::$extension)) {
                $filenameWithExt = $filename . self::$extension;
                if (isset(self::$templateFiles[$filenameWithExt])) {
                    return $filenameWithExt;
                }
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
     * Generate menu-specific template suggestions
     * 
     * @param string $menuName Menu machine name
     * @param int $level Nesting level
     * @return array Suggestions
     */
    public static function forMenu(string $menuName, ?int $level = null, ?string $theme = null): array {
        $suggestions = [];
        $base = 'menu';
        
        // menu--name--level
        if ($level !== null && $theme) {
            $suggestions[] = $base . self::$separator . $menuName . self::$separator . 'level' . $level . self::$separator . $theme . self::$extension;
        }
        if ($level !== null) {
            $suggestions[] = $base . self::$separator . $menuName . self::$separator . 'level' . $level . self::$extension;
        }
        if ($theme) {
            $suggestions[] = $base . self::$separator . $menuName . self::$separator . $theme . self::$extension;
        }
        
        // menu--menuname
        $suggestions[] = $base . self::$separator . $menuName . self::$extension;
        
        // menu (base)
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    /**
     * Generate form-specific template suggestions
     * 
     * @param string $formId Form ID
     * @param string $formType Form type (login, register, contact, etc.)
     * @return array Suggestions
     */
    public static function forForm(string $formId, ?string $formType = null, ?string $mode = null): array {
        $suggestions = [];
        $base = 'form';
        
        if ($formType && $mode) {
            $suggestions[] = $base . self::$separator . $formId . self::$separator . $formType . self::$separator . $mode . self::$extension;
        }
        if ($formType) {
            $suggestions[] = $base . self::$separator . $formId . self::$separator . $formType . self::$extension;
        }
        if ($mode) {
            $suggestions[] = $base . self::$separator . $formId . self::$separator . $mode . self::$extension;
        }
        
        $suggestions[] = $base . self::$separator . $formId . self::$extension;
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    /**
     * Generate view-specific template suggestions (for list views)
     * 
     * @param string $viewId View ID
     * @param string $display Display ID
     * @param string $style Style plugin
     * @return array Suggestions
     */
    public static function forView(string $viewId, ?string $display = null, ?string $style = null): array {
        $suggestions = [];
        $base = 'view';
        
        // view--viewid--display--style
        if ($display && $style) {
            $suggestions[] = $base . self::$separator . $viewId . self::$separator . $display . self::$separator . $style . self::$extension;
        }
        
        // view--viewid--display
        if ($display) {
            $suggestions[] = $base . self::$separator . $viewId . self::$separator . $display . self::$extension;
        }
        
        // view--viewid--style
        if ($style) {
            $suggestions[] = $base . self::$separator . $viewId . self::$separator . $style . self::$extension;
        }
        
        // view--viewid
        $suggestions[] = $base . self::$separator . $viewId . self::$extension;
        
        // view (base)
        $suggestions[] = $base . self::$extension;
        
        return $suggestions;
    }
    
    // /**
    //  * Find the first matching template from a list of suggestions
    //  * 
    //  * @param array $suggestions Template suggestions in priority order
    //  * @return string|null Template name or null if none found
    //  */
    // public static function findBestMatch(array $suggestions): ?string {
    //     foreach ($suggestions as $suggestion) {
    //         if (self::exists($suggestion)) {
    //             return $suggestion;
    //         }
    //     }
    //     return null;
    // }
    
    /**
     * Find all matching templates from a list of suggestions
     * 
     * @param array $suggestions Template suggestions
     * @return array Matching templates
     */
    public static function findAllMatches(array $suggestions): array {
        $matches = [];
        foreach ($suggestions as $suggestion) {
            if (self::exists($suggestion)) {
                $matches[] = $suggestion;
            }
        }
        return $matches;
    }
    
    /**
     * Get debugging info about why a template was selected
     * 
     * @param array $suggestions Template suggestions
     * @return array Debug information
     */
    public static function debug(array $suggestions): array {
        $debug = [
            'suggestions' => $suggestions,
            'checked' => [],
            'selected' => null,
            'available_templates' => array_keys(self::$templateFiles)
        ];
        
        foreach ($suggestions as $suggestion) {
            $exists = self::exists($suggestion);
            $debug['checked'][$suggestion] = [
                'exists' => $exists,
                'path' => $exists ? self::getPath($suggestion) : null
            ];
            
            if ($exists && $debug['selected'] === null) {
                $debug['selected'] = $suggestion;
            }
        }
        
        return $debug;
    }
    
    /**
     * Format suggestions as HTML for debugging display
     */
    public static function debugHtml(array $suggestions): string {
        $html = '<div class="template-suggestions">';
        $html .= '<h4>Template Suggestions</h4>';
        $html .= '<ul>';
        
        $selected = self::findBestMatch($suggestions);
        
        foreach ($suggestions as $suggestion) {
            $exists = self::exists($suggestion);
            $class = $exists ? 'exists' : 'missing';
            $marker = '';
            
            if ($suggestion === $selected) {
                $class .= ' selected';
                $marker = ' ✓ (selected)';
            }
            
            $html .= sprintf('<li class="%s">%s%s</li>', $class, htmlspecialchars($suggestion), $marker);
        }
        
        $html .= '</ul></div>';
        return $html;
    }        

}

} // end class_exists check
