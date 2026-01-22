<?php

require_once 'MenuBuilder.php';
require_once 'MenuThemePreprocessor.php';

/**
 * menuModule - Orchestrates menu rendering using MenuBuilder and theme preprocessor
 * Now follows separation of concerns pattern
 */
class menuModule extends moduleClass {
    protected $menuBuilder;
    protected $themePreprocessor;
    protected $menuTree;
    protected $menuName;
    protected $variant;

    /**
     * @param string $adir Module directory
     * @param string $amodule Module name
     * @param string $atemplate Template file (e.g., 'main_navigation.zetem')
     * @param string $menu_name Menu identifier (e.g., 'main', 'footer')
     * @param string $variant Menu variant ('sidebar' or 'topbar')
     */
    function __construct($adir, $amodule, $atemplate, $menu_name = null, $variant = null) {
        global $Request, $kernel;

        parent::__construct($adir, $amodule, $atemplate);
        
        $this->menuName = $menu_name;
        $this->variant = $variant;
        
        // Initialize MenuBuilder with dependency injection
        $this->menuBuilder = new MenuBuilder(
            $kernel->getConfig('menu')
            // new SecurityClass()
        );
        
        // Initialize theme preprocessor
        $this->themePreprocessor = new MenuThemePreprocessor('default', $variant);
        
        // Build menu tree with current path
        $current_path = $Request->getQueryRoute();
        $this->buildMenu($menu_name, $current_path[0]);
        
    }
    
    /**
     * Build or rebuild the menu
     * 
     * @param string $menu_name Menu identifier
     * @param string|null $current_path Current route path
     */
    protected function buildMenu($menu_name, $current_path = null) {
        // Build menu tree (business logic)
        $this->menuTree = $this->menuBuilder->build($menu_name, $current_path);
        // echopre("menu tree: " . print_r($this->menuTree, 1));
        // Apply theme/presentation (presentation logic)
        $this->themePreprocessor->preprocess($this->menuTree);
    }
    
    /**
     * Set or switch to a different menu
     * 
     * @param string $menu_name Menu identifier
     * @param string|null $current_path Optional path override
     */
    function setMenu($menu_name, $current_path = null) {
        global $Request;
        
        if (!$current_path) {
            $current_path = $Request->getQueryRoute();
        }
        
        $this->menuName = $menu_name;
        $this->buildMenu($menu_name, $current_path[0]);
    }
    
    /**
     * Change menu variant (sidebar/topbar)
     * 
     * @param string $variant 'sidebar' or 'topbar'
     */
    function setVariant($variant) {
        $this->variant = $variant;
        $this->themePreprocessor = new MenuThemePreprocessor('default', $variant);
        
        // Re-apply preprocessing with new variant
        $this->themePreprocessor->preprocess($this->menuTree);
    }

    /**
     * Render the menu
     * 
     * @param array $params Additional parameters for template
     * @return string Rendered HTML
     */
    function render($params = []) {
        // Build menu wrapper attributes
        $menu_attributes = $this->themePreprocessor->buildMenuAttributes(
            $this->menuName,
            $params['menu_options'] ?? []
        );
        
        echopre("ready to render: " . print_r($this->variant, 1) );

        return $this->RenderTemplate([
            'menu' => $this->menuTree,
            'menu_name' => $this->menuName,
            'menu_attributes' => $menu_attributes,
            'menu_variant' => $this->variant,
            'breadcrumbs' => $this->menuBuilder->getBreadcrumbs($this->menuTree),
        ]);
    }
    
    /**
     * Get the menu tree (for external use)
     * 
     * @return array Processed menu tree
     */
    function getMenuTree() {
        return $this->menuTree;
    }
    
    /**
     * Get breadcrumbs
     * 
     * @return array Breadcrumb items
     */
    function getBreadcrumbs() {
        return $this->menuBuilder->getBreadcrumbs($this->menuTree);
    }
    
    /**
     * Get active trail
     * 
     * @return array Active trail keys
     */
    function getActiveTrail() {
        return $this->menuBuilder->getActiveTrail();
    }
}

/**
 * Register main navigation module (sidebar variant)
 */
function register_mainnavigation_module() {
    global $kernel;

    $kernel->registerModule(
        new menuModule(
            __DIR__, 
            'mainnavigation', 
            'main_navigation.zetem',  // Original template name
            'main',
            'topbar'
        )
    );
}

/**
 * Register top navigation module (topbar variant)
 */
// function register_topnavigation_module() {
//     global $kernel;

//     $kernel->registerModule(
//         new menuModule(
//             __DIR__, 
//             'topnavigation', 
//             'main_navigation.zetem',  // Uses same template, variant changes output
//             'main',
//             'topbar'
//         )
//     );
// }