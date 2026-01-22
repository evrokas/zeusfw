<?php
/**
 * MenuThemePreprocessor - Applies design system classes and attributes
 * Separates presentation logic from business logic
 */
class MenuThemePreprocessor {
    protected $theme;
    protected $variant;
    
    /**
     * @param string $theme Theme name (e.g., 'default', 'admin')
     * @param string $variant Menu variant (e.g., 'sidebar', 'topbar', 'footer')
     */
    public function __construct($theme = 'default', $variant = 'sidebar') {
        $this->theme = $theme;
        $this->variant = $variant;
    }
    
    /**
     * Preprocess menu tree - add all presentation attributes
     * 
     * @param array &$items Menu items (passed by reference)
     * @param int $depth Current depth level
     */
    public function preprocess(&$items, $depth = 0) {
        foreach ($items as &$item) {
            // Create all attribute objects
            $item['attributes'] = $this->buildItemAttributes($item, $depth);
            $item['link_attributes'] = $this->buildLinkAttributes($item, $depth);
            
            // Toggle checkbox attributes (for dropdowns)
            if ($item['has_children']) {
                $item['toggle_attributes'] = $this->buildToggleAttributes($item, $depth);
                $item['toggle_label_attributes'] = $this->buildToggleLabelAttributes($item, $depth);
            }
            
            // Icon attributes
            if ($item['icon']) {
                $item['icon_attributes'] = $this->buildIconAttributes($item);
            }
            
            // Badge attributes
            if ($item['badge']) {
                $item['badge_attributes'] = $this->buildBadgeAttributes($item);
            }
            
            // Submenu container attributes
            if ($item['has_children']) {
                $item['submenu_attributes'] = $this->buildSubmenuAttributes($item, $depth);
                
                // Recursively preprocess children
                $this->preprocess($item['children'], $depth + 1);
            }
        }
        // echopre("theme preprocess: " . print_r($items, 1));
    }
    
    /**
     * Build attributes for <li> element
     */
    protected function buildItemAttributes($item, $depth) {
        $attr = new Attributes();
        
        // Variant-specific base classes
        if ($this->variant === 'sidebar') {
            $attr->addClass('sidebar-menu-item');
        } elseif ($this->variant === 'topbar') {
            $attr->addClass('topbar-menu-item');
        }
        
        // State classes
        if ($item['has_children']) {
            $attr->addClass('has-submenu');
        }
        
        if (!$item['published']) {
            $attr->addClass('unpublished');
        }
        
        if ($item['in_active_trail']) {
            $attr->addClass('in-trail');
        }
        
        if ($item['is_current_page']) {
            $attr->addClass('current-page');
        }
        
        if ($item['is_expanded']) {
            $attr->addClass('is-expanded');
        }
        
        // Depth class
        $attr->addClass('menu-item-depth-' . $depth);
        
        // Custom classes from options
        if (isset($item['options']['attributes']['class'])) {
            foreach ((array)$item['options']['attributes']['class'] as $class) {
                $attr->addClass($class);
            }
        }
        
        return $attr;
    }
    
    /**
     * Build attributes for <a> or <label> element
     */
    protected function buildLinkAttributes($item, $depth) {
        $attr = new Attributes();
        
        // Variant-specific base classes
        if ($this->variant === 'sidebar') {
            $attr->addClass('sidebar-menu-link');
        } elseif ($this->variant === 'topbar') {
            $attr->addClass('topbar-menu-link');
        }
        
        // Active state
        if ($item['is_current_page']) {
            $attr->addClass('active');
        }
        
        // In trail but not current
        if ($item['in_active_trail'] && !$item['is_current_page']) {
            $attr->addClass('in-trail');
        }
        
        // Expanded state for items with children
        if ($item['has_children'] && $item['is_expanded']) {
            $attr->addClass('expanded');
        }
        
        // Custom link attributes
        if (isset($item['options']['link_attributes'])) {
            foreach ($item['options']['link_attributes'] as $key => $value) {
                if ($key === 'class') {
                    foreach ((array)$value as $class) {
                        $attr->addClass($class);
                    }
                } else {
                    $attr->addAttribute([$key =>$value]);
                }
            }
        }
        
        return $attr;
    }
    
    /**
     * Build attributes for toggle checkbox (mobile dropdowns)
     */
    protected function buildToggleAttributes($item, $depth) {
        $attr = new Attributes();
        
        $attr->addAttribute(['type' => 'checkbox']);
        $attr->addAttribute(['id' => 'submenu-toggle-' . $item['key']]);
        $attr->addClass('submenu-toggle');
        $attr->addClass('submenu-toggle-level-' . $depth);
        
        // Auto-expand if in trail
        if ($item['is_expanded']) {
            $attr->addAttribute(['checked' =>'checked']);
        }
        
        return $attr;
    }
    
    /**
     * Build attributes for toggle label
     */
    protected function buildToggleLabelAttributes($item, $depth) {
        $attr = new Attributes();
        
        $attr->addAttribute(['for' =>'submenu-toggle-' . $item['key']]);
        $attr->addClass('submenu-toggle-label');
        
        return $attr;
    }
    
    /**
     * Build attributes for icon element
     */
    protected function buildIconAttributes($item) {
        $attr = new Attributes();
        $attr->addClass('menu-icon');
        
        // Add icon-specific classes if provided
        if (isset($item['icon']['attributes'])) {
            foreach ($item['icon']['attributes'] as $key => $value) {
                if ($key === 'class') {
                    foreach ((array)$value as $class) {
                        $attr->addClass($class);
                    }
                } else {
                    $attr->addAttribute([$key => $value]);
                }
            }
        }
        
        return $attr;
    }
    
    /**
     * Build attributes for badge element
     */
    protected function buildBadgeAttributes($item) {
        $attr = new Attributes();
        $attr->addClass('badge');
        
        // Badge style from config or default
        if (isset($item['badge']['class'])) {
            $attr->addClass($item['badge']['class']);
        } else {
            // Default badge style
            $attr->addClass('badge-info');
        }
        
        // Badge size
        if (isset($item['badge']['size'])) {
            $attr->addClass('badge-' . $item['badge']['size']);
        }
        
        return $attr;
    }
    
    /**
     * Build attributes for submenu <ul> element
     */
    protected function buildSubmenuAttributes($item, $depth) {
        $attr = new Attributes();
        
        // Variant-specific classes
        if ($this->variant === 'sidebar') {
            $attr->addClass('sidebar-submenu');
            $attr->addClass('sidebar-submenu-level-' . ($depth + 1));
        } elseif ($this->variant === 'topbar') {
            $attr->addClass('topbar-dropdown');
            $attr->addClass('topbar-dropdown-level-' . ($depth + 1));
        }
        
        // Custom submenu class from config
        if ($item['submenu_class']) {
            $attr->addClass($item['submenu_class']);
        }
        
        // Alignment classes
        if (isset($item['options']['submenu_alignment'])) {
            $attr->addClass('align-' . $item['options']['submenu_alignment']);
        }
        
        return $attr;
    }
    
    /**
     * Build complete menu wrapper attributes
     */
    public function buildMenuAttributes($menu_name, $options = []) {
        $attr = new Attributes();
        
        // Base menu class
        $attr->addClass('menu');
        $attr->addClass('menu-' . $menu_name);
        
        // Variant class
        if ($this->variant === 'sidebar') {
            $attr->addClass('sidebar-menu');
        } elseif ($this->variant === 'topbar') {
            $attr->addClass('topbar-menu');
        }
        
        // Custom classes from options
        if (isset($options['class'])) {
            foreach ((array)$options['class'] as $class) {
                $attr->addClass($class);
            }
        }
        
        // Custom attributes
        if (isset($options['attributes'])) {
            foreach ($options['attributes'] as $key => $value) {
                if ($key !== 'class') {
                    $attr->addAttribute([$key =>$value]);
                }
            }
        }
        
        return $attr;
    }
}