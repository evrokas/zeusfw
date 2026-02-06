<?php
/**
 * Render Array Manager
 *
 * A Drupal-inspired render array system for Zeus Framework.
 * Provides hierarchical, array-based content rendering with support for
 * access control, weight-based ordering, and template integration.
 *
 * @author Evangelos Rokas
 * @version 1.0 (Phase 1 MVP)
 * @date February 2026
 */

class RenderArrayManager {

    /**
     * Registered element type renderers
     * @var array
     */
    private static $typeRenderers = [];

    /**
     * Registered alteration hooks
     * @var array
     */
    private static $alterHooks = [];

    /**
     * Configuration
     * @var array
     */
    private static $config = [];

    /**
     * Collected attached assets during rendering
     * @var array
     */
    private static $attachedAssets = [
        'libraries' => [],
        'css' => [],
        'js' => [],
        'head_script' => [],
        'foot_script' => [],
    ];

    /**
     * Initialize the render array system
     *
     * @param array $config Configuration array
     */
    public static function init($config = []) {
        self::$config = array_merge([
            'cache_enabled' => false,
            'cache_path' => 'web/cache/render/',
            'default_cache_max_age' => 3600,
            'type_templates' => [],
        ], $config);

        // Register built-in element types
        self::registerBuiltInTypes();
    }

    /**
     * Register built-in element type renderers
     */
    private static function registerBuiltInTypes() {
        require_once __DIR__ . '/RenderElementTypes.php';

        self::$typeRenderers = [
            'markup' => [RenderElementTypes::class, 'renderMarkup'],
            'container' => [RenderElementTypes::class, 'renderContainer'],
            'template' => [RenderElementTypes::class, 'renderTemplate'],
            'html_tag' => [RenderElementTypes::class, 'renderHtmlTag'],
            'link' => [RenderElementTypes::class, 'renderLink'],
            'module' => [RenderElementTypes::class, 'renderModule'],
            'table' => [RenderElementTypes::class, 'renderTable'],
            'ajax_link' => [RenderElementTypes::class, 'renderAjaxLink'],
            'ajax_button' => [RenderElementTypes::class, 'renderAjaxButton'],
        ];
    }

    /**
     * Render a render array to HTML
     *
     * @param array &$element Render array element (passed by reference for alteration)
     * @param array $context Additional context for rendering
     * @return string Rendered HTML
     */
    public static function render(array &$element, array $context = []): string {
        // Step 1: Check access
        if (!self::checkAccess($element, $context)) {
            return '';
        }

        // Step 2: Check if already printed
        if (!empty($element['#printed'])) {
            return '';
        }

        // Step 3: Run alteration hooks
        self::runAlterHooks($element, $context);

        // Step 4: Execute pre_render callbacks
        if (!empty($element['#pre_render'])) {
            self::executePreRenderCallbacks($element, $context);
        }

        // Step 5: Sort children by weight
        $children = self::getChildren($element);
        if (!empty($children)) {
            $children = self::sortByWeight($children, $element);
        }

        // Step 6: Render children recursively
        $renderedChildren = '';
        foreach ($children as $key) {
            if (is_array($element[$key])) {
                $renderedChildren .= self::render($element[$key], $context);
            }
        }

        // Step 7: Determine rendering method and render element
        $output = self::renderElement($element, $renderedChildren, $context);

        // Step 8: Apply prefix and suffix
        if (isset($element['#prefix'])) {
            $output = $element['#prefix'] . $output;
        }
        if (isset($element['#suffix'])) {
            $output = $output . $element['#suffix'];
        }

        // Step 9: Execute post_render callbacks
        if (!empty($element['#post_render'])) {
            $output = self::executePostRenderCallbacks($output, $element, $context);
        }

        // Step 10: Process attached assets
        if (!empty($element['#attached'])) {
            self::collectAttachedAssets($element['#attached']);
        }

        // Mark as printed
        $element['#printed'] = true;

        return $output;
    }

    /**
     * Render without altering the original array (makes a copy)
     *
     * @param array $element Render array element
     * @param array $context Additional context
     * @return string Rendered HTML
     */
    public static function renderPlain(array $element, array $context = []): string {
        $copy = $element;
        return self::render($copy, $context);
    }

    /**
     * Check if element has access
     *
     * @param array $element Render array element
     * @param array $context Additional context
     * @return bool True if access is granted
     */
    public static function checkAccess(array $element, array $context = []): bool {
        require_once __DIR__ . '/RenderAccess.php';
        return RenderAccess::check($element, $context);
    }

    /**
     * Get child element keys (non-# prefixed keys)
     *
     * @param array $element Render array element
     * @return array Array of child keys
     */
    private static function getChildren(array $element): array {
        $children = [];
        foreach (array_keys($element) as $key) {
            if (is_string($key) && $key[0] !== '#' && $key[0] !== '_') {
                $children[] = $key;
            }
        }
        return $children;
    }

    /**
     * Sort children by weight
     *
     * @param array $children Array of child keys
     * @param array $element Parent element containing children
     * @return array Sorted array of child keys
     */
    private static function sortByWeight(array $children, array $element): array {
        // Create array of [key => weight] pairs
        $weighted = [];
        foreach ($children as $key) {
            $weight = $element[$key]['#weight'] ?? 0;
            $weighted[$key] = $weight;
        }

        // Stable sort by weight
        uasort($weighted, function($a, $b) {
            return $a <=> $b;
        });

        return array_keys($weighted);
    }

    /**
     * Render an individual element based on its type
     *
     * @param array $element Render array element
     * @param string $renderedChildren Pre-rendered children HTML
     * @param array $context Additional context
     * @return string Rendered HTML
     */
    private static function renderElement(array $element, string $renderedChildren, array $context): string {
        // Priority 1: Direct markup
        if (isset($element['#markup'])) {
            return $element['#markup'] . $renderedChildren;
        }

        // Priority 2: Theme/Template
        if (isset($element['#theme']) || isset($element['#template'])) {
            $templateName = $element['#theme'] ?? $element['#template'];
            $templateContext = array_merge(
                $element['#context'] ?? [],
                ['children' => $renderedChildren]
            );

            if (class_exists('Renderer')) {
                return Renderer::render($templateName, $templateContext);
            }
            return $renderedChildren;
        }

        // Priority 3: Type-based rendering
        if (isset($element['#type']) && isset(self::$typeRenderers[$element['#type']])) {
            $renderer = self::$typeRenderers[$element['#type']];
            return call_user_func($renderer, $element, $renderedChildren, $context);
        }

        // Fallback: just return children
        return $renderedChildren;
    }

    /**
     * Execute pre-render callbacks
     *
     * @param array &$element Render array element
     * @param array $context Additional context
     */
    private static function executePreRenderCallbacks(array &$element, array $context): void {
        foreach ($element['#pre_render'] as $callback) {
            if (is_callable($callback)) {
                call_user_func_array($callback, [&$element, $context]);
            } elseif (is_string($callback) && function_exists($callback)) {
                call_user_func_array($callback, [&$element, $context]);
            }
        }
    }

    /**
     * Execute post-render callbacks
     *
     * @param string $html Rendered HTML
     * @param array $element Render array element
     * @param array $context Additional context
     * @return string Modified HTML
     */
    private static function executePostRenderCallbacks(string $html, array $element, array $context): string {
        foreach ($element['#post_render'] as $callback) {
            if (is_callable($callback)) {
                $html = call_user_func($callback, $html, $element, $context);
            } elseif (is_string($callback) && function_exists($callback)) {
                $html = call_user_func($callback, $html, $element, $context);
            }
        }
        return $html;
    }

    /**
     * Run alteration hooks on the element
     *
     * @param array &$element Render array element
     * @param array $context Additional context
     */
    private static function runAlterHooks(array &$element, array $context): void {
        foreach (self::$alterHooks as $hook) {
            if (is_callable($hook)) {
                call_user_func_array($hook, [&$element, $context]);
            }
        }
    }

    /**
     * Collect attached assets for later processing
     *
     * @param array $attached Attached assets array
     */
    private static function collectAttachedAssets(array $attached): void {
        if (isset($attached['library'])) {
            self::$attachedAssets['libraries'] = array_merge(
                self::$attachedAssets['libraries'],
                (array)$attached['library']
            );
        }
        if (isset($attached['css'])) {
            self::$attachedAssets['css'] = array_merge(
                self::$attachedAssets['css'],
                (array)$attached['css']
            );
        }
        if (isset($attached['js'])) {
            self::$attachedAssets['js'] = array_merge(
                self::$attachedAssets['js'],
                (array)$attached['js']
            );
        }
        if (isset($attached['head_script'])) {
            self::$attachedAssets['head_script'] = array_merge(
                self::$attachedAssets['head_script'],
                (array)$attached['head_script']
            );
        }
        if (isset($attached['foot_script'])) {
            self::$attachedAssets['foot_script'] = array_merge(
                self::$attachedAssets['foot_script'],
                (array)$attached['foot_script']
            );
        }
    }

    /**
     * Get collected attached assets
     *
     * @return array Attached assets
     */
    public static function getAttachedAssets(): array {
        return self::$attachedAssets;
    }

    /**
     * Clear collected attached assets
     */
    public static function clearAttachedAssets(): void {
        self::$attachedAssets = [
            'libraries' => [],
            'css' => [],
            'js' => [],
            'head_script' => [],
            'foot_script' => [],
        ];
    }

    /**
     * Register a custom element type renderer
     *
     * @param string $type Element type name
     * @param callable $renderer Renderer callback
     */
    public static function registerType(string $type, callable $renderer): void {
        self::$typeRenderers[$type] = $renderer;
    }

    /**
     * Register an alteration hook
     *
     * @param string $module Module name (for documentation)
     * @param callable $hook Alteration hook callback
     */
    public static function registerAlter(string $module, callable $hook): void {
        self::$alterHooks[$module] = $hook;
    }

    /**
     * Build a render array with the specified type
     *
     * @param string $type Element type
     * @param array $properties Additional properties
     * @return array Render array
     */
    public static function build(string $type, array $properties = []): array {
        return array_merge(['#type' => $type], $properties);
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function getConfig(string $key, $default = null) {
        return self::$config[$key] ?? $default;
    }
}
