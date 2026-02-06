<?php
/**
 * Render Array Helper Functions
 *
 * Convenience functions for building common render array structures.
 *
 * @author Evangelos Rokas
 * @version 1.0
 * @date February 2026
 */

/**
 * Build a container render array
 *
 * @param array $children Child elements (keyed array of render arrays)
 * @param array $attributes HTML attributes
 * @param string $tag HTML tag (default: 'div')
 * @return array Render array
 */
function render_container(array $children = [], array $attributes = [], string $tag = 'div'): array {
    return array_merge(
        [
            '#type' => 'container',
            '#tag' => $tag,
            '#attributes' => $attributes,
        ],
        $children
    );
}

/**
 * Build a markup render array
 *
 * @param string $html HTML markup
 * @param string $prefix HTML prefix
 * @param string $suffix HTML suffix
 * @return array Render array
 */
function render_markup(string $html, string $prefix = '', string $suffix = ''): array {
    $render = [
        '#type' => 'markup',
        '#markup' => $html,
    ];

    if ($prefix !== '') {
        $render['#prefix'] = $prefix;
    }
    if ($suffix !== '') {
        $render['#suffix'] = $suffix;
    }

    return $render;
}

/**
 * Build a link render array
 *
 * @param string $title Link text
 * @param string $url Link URL
 * @param array $attributes HTML attributes
 * @return array Render array
 */
function render_link(string $title, string $url, array $attributes = []): array {
    return [
        '#type' => 'link',
        '#title' => $title,
        '#url' => $url,
        '#attributes' => $attributes,
    ];
}

/**
 * Build a template render array
 *
 * @param string $theme Template name
 * @param array $context Template context variables
 * @param array $suggestions Template suggestions
 * @return array Render array
 */
function render_template(string $theme, array $context = [], array $suggestions = []): array {
    $render = [
        '#type' => 'template',
        '#theme' => $theme,
        '#context' => $context,
    ];

    if (!empty($suggestions)) {
        $render['#theme_suggestions'] = $suggestions;
    }

    return $render;
}

/**
 * Build an HTML tag render array
 *
 * @param string $tag HTML tag name
 * @param string $value Tag content
 * @param array $attributes HTML attributes
 * @return array Render array
 */
function render_html_tag(string $tag, string $value = '', array $attributes = []): array {
    return [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#value' => $value,
        '#attributes' => $attributes,
    ];
}

/**
 * Build a module render array
 *
 * @param string $moduleName Module class name
 * @param array $params Module parameters
 * @return array Render array
 */
function render_module(string $moduleName, array $params = []): array {
    return [
        '#type' => 'module',
        '#module_name' => $moduleName,
        '#module_params' => $params,
    ];
}

/**
 * Add a class to render array attributes
 *
 * @param array &$element Render array element (passed by reference)
 * @param string|array $class Class name(s) to add
 */
function render_add_class(array &$element, $class): void {
    if (!isset($element['#attributes']['class'])) {
        $element['#attributes']['class'] = [];
    }

    if (is_string($class)) {
        $class = [$class];
    }

    $element['#attributes']['class'] = array_merge(
        $element['#attributes']['class'],
        $class
    );
}

/**
 * Set an attribute on a render array
 *
 * @param array &$element Render array element (passed by reference)
 * @param string $attribute Attribute name
 * @param mixed $value Attribute value
 */
function render_set_attribute(array &$element, string $attribute, $value): void {
    $element['#attributes'][$attribute] = $value;
}

/**
 * Set the weight of a render array element
 *
 * @param array &$element Render array element (passed by reference)
 * @param int $weight Weight value
 */
function render_set_weight(array &$element, int $weight): void {
    $element['#weight'] = $weight;
}

/**
 * Set access control on a render array element
 *
 * @param array &$element Render array element (passed by reference)
 * @param mixed $access Access control (bool, string, callable, array)
 */
function render_set_access(array &$element, $access): void {
    $element['#access'] = $access;
}

/**
 * Attach a library to a render array
 *
 * @param array &$element Render array element (passed by reference)
 * @param string|array $library Library name(s)
 */
function render_attach_library(array &$element, $library): void {
    if (!isset($element['#attached']['library'])) {
        $element['#attached']['library'] = [];
    }

    if (is_string($library)) {
        $library = [$library];
    }

    $element['#attached']['library'] = array_merge(
        $element['#attached']['library'],
        $library
    );
}

/**
 * Build a table render array
 *
 * @param array $header Table header row
 * @param array $rows Table data rows
 * @param array $options Additional options (footer, caption, empty, responsive, sticky_header, attributes)
 * @return array Render array
 */
function render_table(array $header = [], array $rows = [], array $options = []): array {
    $render = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
    ];

    if (isset($options['footer'])) {
        $render['#footer'] = $options['footer'];
    }

    if (isset($options['caption'])) {
        $render['#caption'] = $options['caption'];
    }

    if (isset($options['empty'])) {
        $render['#empty'] = $options['empty'];
    }

    if (isset($options['responsive'])) {
        $render['#responsive'] = $options['responsive'];
    }

    if (isset($options['sticky_header'])) {
        $render['#sticky_header'] = $options['sticky_header'];
    }

    if (isset($options['attributes'])) {
        $render['#attributes'] = $options['attributes'];
    }

    return $render;
}

/**
 * Build an AJAX link render array
 *
 * @param string $title Link text
 * @param string $url AJAX URL
 * @param array $options Options (method, target, callback, confirm, attributes)
 * @return array Render array
 */
function render_ajax_link(string $title, string $url, array $options = []): array {
    $render = [
        '#type' => 'ajax_link',
        '#title' => $title,
        '#url' => $url,
    ];

    if (isset($options['method'])) {
        $render['#method'] = $options['method'];
    }

    if (isset($options['target'])) {
        $render['#target'] = $options['target'];
    }

    if (isset($options['callback'])) {
        $render['#callback'] = $options['callback'];
    }

    if (isset($options['confirm'])) {
        $render['#confirm'] = $options['confirm'];
    }

    if (isset($options['attributes'])) {
        $render['#attributes'] = $options['attributes'];
    }

    return $render;
}

/**
 * Build an AJAX button render array
 *
 * @param string $value Button text
 * @param string $url AJAX URL
 * @param array $options Options (method, target, callback, confirm, data, button_type, attributes)
 * @return array Render array
 */
function render_ajax_button(string $value, string $url, array $options = []): array {
    $render = [
        '#type' => 'ajax_button',
        '#value' => $value,
        '#url' => $url,
    ];

    if (isset($options['method'])) {
        $render['#method'] = $options['method'];
    }

    if (isset($options['target'])) {
        $render['#target'] = $options['target'];
    }

    if (isset($options['callback'])) {
        $render['#callback'] = $options['callback'];
    }

    if (isset($options['confirm'])) {
        $render['#confirm'] = $options['confirm'];
    }

    if (isset($options['data'])) {
        $render['#data'] = $options['data'];
    }

    if (isset($options['button_type'])) {
        $render['#button_type'] = $options['button_type'];
    }

    if (isset($options['attributes'])) {
        $render['#attributes'] = $options['attributes'];
    }

    return $render;
}
