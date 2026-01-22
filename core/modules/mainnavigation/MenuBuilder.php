<?php
/**
 * MenuBuilder - Handles menu tree construction and business logic
 * Separates data processing from presentation
 */
class MenuBuilder {
    protected $config;
    // protected $security;
    protected $activeTrail = [];
    
    public function __construct($config) {  //}, $security = null) {
        $this->config = $config;
        // $this->security = $security ?: new SecurityClass();
    }
    
    /**
     * Build menu tree with all metadata
     * 
     * @param string $menu_name Menu identifier (e.g., 'main', 'footer')
     * @param string|null $current_path Current route for active trail calculation
     * @return array Processed menu tree with metadata
     */
    public function build($menu_name, $current_path = null) {
        $menu_config = $this->getMenuConfig($menu_name);
        
        if (!$menu_config) {
            return [];
        }
        
        // this is temporary to get going with the implementation,
        // will come back later
        $current_path = null;

        // Calculate active trail first
        if ($current_path) {
            $this->activeTrail = $this->calculateActiveTrail($menu_config, $current_path);
        }
        
        // Build tree with metadata
        return $this->buildTree($menu_config, 0);
    }
    
    /**
     * Get menu configuration from config array or file
     */
    protected function getMenuConfig($menu_name) {
        // Support both array and YAML config
        if (is_array($this->config)) {
            return $this->config[$menu_name] ?? null;
        }
        
        // If config is a path to YAML file
        if (is_string($this->config) && file_exists($this->config)) {
            // Load YAML (assuming you have a YAML parser)
            $all_menus = yaml_parse_file($this->config);
            return $all_menus[$menu_name] ?? null;
        }
        
        return null;
    }
    
    /**
     * Recursively build menu tree with metadata
     * 
     * @param array $items Menu items configuration
     * @param int $depth Current nesting level
     * @param array $parent_trail Trail of parent keys
     * @return array Processed menu tree
     */
    protected function buildTree($items, $depth = 0, $parent_trail = []) {
        $tree = [];
        $position = 0;
        
        foreach ($items as $key => $item) {
            // Skip if not accessible
            if (!$this->isAccessible($item)) {
                continue;
            }
            
            // Skip if disabled
            if (isset($item['disabled']) && $item['disabled']) {
                continue;
            }
            
            // Skip if unpublished (unless user has permission)
            if (isset($item['published']) && !$item['published']) {
                // if (!$this->security->hasPermission('view_unpublished_menu')) {
                if (SecurityClass::require('view_unpublished')) {
                    continue;
                }
            }
            
            // Get weight for ordering
            $weight = $item['weight'] ?? $position;
            
            // Build current trail
            $current_trail = array_merge($parent_trail, [$key]);
            $trail_string = implode('/', $current_trail);
            
            // Determine active states
            $in_trail = in_array($key, $this->activeTrail);
            $is_current = false;
            if (!empty($this->activeTrail)) {
                $is_current = ($this->activeTrail[count($this->activeTrail) - 1] === $key);
            }
            
            // Build menu item with metadata
            $menu_item = [
                'key' => $key,
                'title' => $item['text'] ?? $key,
                'url' => $item['url'] ?? null,
                'icon' => $item['icon'] ?? null,
                'badge' => $item['badge'] ?? null,
                'depth' => $depth,
                'weight' => $weight,
                'position' => $position,
                'trail' => $current_trail,
                'trail_string' => $trail_string,
                'published' => $item['published'] ?? true,
                'in_active_trail' => $in_trail,
                'is_expanded' => $in_trail || ($item['expanded'] ?? false),
                'is_current_page' => $is_current,
                'has_children' => isset($item['submenu']) && is_array($item['submenu']) && count($item['submenu']) > 0,
                'children' => [],
                'options' => $item['options'] ?? [],
                'submenu_class' => $item['submenu-class'] ?? null,
                'submenu_visibility' => $item['submenu-visibility'] ?? 'show',
            ];
            
            // Recursively build children
            if (isset($item['submenu']) && is_array($item['submenu'])) {
                $menu_item['children'] = $this->buildTree(
                    $item['submenu'], 
                    $depth + 1,
                    $current_trail
                );
                
                // Update has_children based on actual accessible children
                $menu_item['has_children'] = count($menu_item['children']) > 0;
                
                // Skip parent if no accessible children and configured to hide
                if (empty($menu_item['children']) && 
                    isset($item['hide_if_empty']) && $item['hide_if_empty']) {
                    continue;
                }
            }
            
            $tree[$key] = $menu_item;
            $position++;
        }
        
        // Sort by weight
        uasort($tree, function($a, $b) {
            return $a['weight'] <=> $b['weight'];
        });
        
        return $tree;
    }
    
    /**
     * Check if user has access to menu item
     * 
     * @param array $item Menu item configuration
     * @return bool True if accessible
     */
    protected function isAccessible($item) {
        if (!isset($item['access'])) {
            return true;
        }
        
        // Support multiple permission requirements
        $permissions = is_array($item['access']) ? $item['access'] : [$item['access']];
        
        foreach ($permissions as $permission) {
            // if (!$this->security->hasPermission($permission)) {
            if ($errmsg=SecurityClass::require($permission)) {
                echopre($errmsg);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate active trail based on current path
     * 
     * @param array $menu Menu configuration
     * @param string $path Current path
     * @param array $trail Current trail being built
     * @return array|null Array of keys representing the trail, or null if not found
     */
    protected function calculateActiveTrail($menu, $path, $trail = []) {
        foreach ($menu as $key => $item) {
            $current_trail = array_merge($trail, [$key]);
            echopre(" url: " . $item['url'] . " path: " . print_r($path, 1));
            // Direct match
            if (isset($item['url']) && $this->urlMatches($item['url'], $path)) {
                return $current_trail;
            }
            
            // Check pattern matching
            if (isset($item['url_pattern'])) {
                if (preg_match($item['url_pattern'], $path)) {
                    return $current_trail;
                }
            }
            
            // Recursively check children
            if (isset($item['submenu'])) {
                $result = $this->calculateActiveTrail($item['submenu'], $path, $current_trail);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if URL matches current path
     * 
     * @param string $url Menu item URL
     * @param string $path Current path
     * @return bool True if matches
     */
    protected function urlMatches($url, $path) {
        // Normalize URLs
        $url = trim($url, '/');
        echopre(print_r($path, 1));
        $path = trim($path, '/');
        
        // Exact match
        if ($url === $path) {
            return true;
        }
        
        // Front page special case
        if (empty($url) && empty($path)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the active trail
     * 
     * @return array Active trail keys
     */
    public function getActiveTrail() {
        return $this->activeTrail;
    }
    
    /**
     * Get breadcrumb array from active trail
     * 
     * @param array $menu_tree Built menu tree
     * @return array Breadcrumb items
     */
    public function getBreadcrumbs($menu_tree) {
        $breadcrumbs = [];
        $current_level = $menu_tree;
        
        foreach ($this->activeTrail as $key) {
            if (isset($current_level[$key])) {
                $item = $current_level[$key];
                $breadcrumbs[] = [
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'is_current' => $item['is_current_page'],
                ];
                $current_level = $item['children'];
            }
        }
        
        return $breadcrumbs;
    }
}