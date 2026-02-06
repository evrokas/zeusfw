<?php
/**
 * Render Element Types
 *
 * Built-in element type renderers for the Zeus Render Array system.
 *
 * @author Evangelos Rokas
 * @version 1.0
 * @date February 2026
 */

class RenderElementTypes {

    /**
     * Render a markup element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderMarkup(array $element, string $children, array $context): string {
        $markup = $element['#markup'] ?? '';
        return $markup . $children;
    }

    /**
     * Render a container element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderContainer(array $element, string $children, array $context): string {
        $attributes = self::buildAttributes($element['#attributes'] ?? []);
        $tag = $element['#tag'] ?? 'div';

        return "<{$tag}{$attributes}>{$children}</{$tag}>";
    }

    /**
     * Render a template element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderTemplate(array $element, string $children, array $context): string {
        $templateName = $element['#theme'] ?? $element['#template'] ?? null;

        if (!$templateName) {
            return $children;
        }

        $templateContext = array_merge(
            $element['#context'] ?? [],
            ['children' => $children]
        );

        // Use theme suggestions if provided
        $suggestions = null;
        if (isset($element['#theme_suggestions'])) {
            $suggestions = [$element['#theme_suggestions'], $templateName];
        }

        if (class_exists('Renderer')) {
            return Renderer::render($templateName, $templateContext, $suggestions);
        }

        return $children;
    }

    /**
     * Render an HTML tag element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderHtmlTag(array $element, string $children, array $context): string {
        $tag = $element['#tag'] ?? 'div';
        $value = $element['#value'] ?? '';
        $attributes = self::buildAttributes($element['#attributes'] ?? []);

        // Self-closing tags
        $selfClosing = ['br', 'hr', 'img', 'input', 'meta', 'link'];
        if (in_array($tag, $selfClosing)) {
            return "<{$tag}{$attributes} />";
        }

        $content = $value . $children;
        return "<{$tag}{$attributes}>{$content}</{$tag}>";
    }

    /**
     * Render a link element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderLink(array $element, string $children, array $context): string {
        $title = $element['#title'] ?? '';
        $url = $element['#url'] ?? '#';
        $attributes = $element['#attributes'] ?? [];

        // Merge href into attributes at the beginning for consistent ordering
        $attributes = array_merge(['href' => $url], $attributes);

        $attrString = self::buildAttributes($attributes);
        $content = $children ?: htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return "<a{$attrString}>{$content}</a>";
    }

    /**
     * Render a module element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderModule(array $element, string $children, array $context): string {
        $moduleName = $element['#module_name'] ?? null;
        $moduleParams = $element['#module_params'] ?? [];

        if (!$moduleName) {
            return $children;
        }

        // Try to instantiate and render the module
        if (class_exists($moduleName)) {
            try {
                $moduleInstance = new $moduleName();
                if (method_exists($moduleInstance, 'render')) {
                    return $moduleInstance->render($moduleParams) . $children;
                }
            } catch (Exception $e) {
                // Silently fail and return children
            }
        }

        return $children;
    }

    /**
     * Render a table element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderTable(array $element, string $children, array $context): string {
        $header = $element['#header'] ?? [];
        $rows = $element['#rows'] ?? [];
        $footer = $element['#footer'] ?? [];
        $empty = $element['#empty'] ?? 'No data available';
        $caption = $element['#caption'] ?? '';
        $responsive = $element['#responsive'] ?? false;
        $sticky_header = $element['#sticky_header'] ?? false;
        $attributes = $element['#attributes'] ?? [];

        // Add responsive wrapper classes if needed
        $wrapperClass = [];
        if ($responsive) {
            $wrapperClass[] = 'table-responsive';
        }

        // Add sticky header class to table
        if ($sticky_header && !isset($attributes['class'])) {
            $attributes['class'] = [];
        }
        if ($sticky_header) {
            $attributes['class'][] = 'table-sticky-header';
        }

        $tableAttrs = self::buildAttributes($attributes);
        $html = '';

        // Add wrapper for responsive tables
        if ($responsive) {
            $html .= '<div class="' . implode(' ', $wrapperClass) . '">';
        }

        $html .= "<table{$tableAttrs}>";

        // Caption
        if ($caption) {
            $html .= '<caption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</caption>';
        }

        // Header
        if (!empty($header)) {
            $html .= '<thead><tr>';
            foreach ($header as $cellKey => $cell) {
                $cellAttrs = '';
                $cellContent = $cell;

                // Support array format for header cells with attributes
                if (is_array($cell)) {
                    $cellContent = $cell['data'] ?? '';
                    $cellAttrs = self::buildAttributes($cell['attributes'] ?? []);
                }

                $html .= "<th{$cellAttrs}>" . htmlspecialchars($cellContent, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead>';
        }

        // Body
        $html .= '<tbody>';
        if (empty($rows)) {
            // Empty message
            $colspan = !empty($header) ? count($header) : 1;
            $html .= '<tr><td colspan="' . $colspan . '" class="text-center">' . htmlspecialchars($empty, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        } else {
            foreach ($rows as $rowKey => $row) {
                $rowAttrs = '';
                $rowData = $row;

                // Support row-level attributes
                if (isset($row['data']) && is_array($row['data'])) {
                    $rowData = $row['data'];
                    $rowAttrs = self::buildAttributes($row['attributes'] ?? []);
                }

                $html .= "<tr{$rowAttrs}>";
                foreach ($rowData as $cellKey => $cell) {
                    $cellAttrs = '';
                    $cellContent = $cell;

                    // Support array format for cells with attributes
                    if (is_array($cell) && isset($cell['data'])) {
                        $cellContent = $cell['data'];
                        $cellAttrs = self::buildAttributes($cell['attributes'] ?? []);
                    }

                    // Allow HTML in cells (for render arrays)
                    $html .= "<td{$cellAttrs}>" . $cellContent . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';

        // Footer
        if (!empty($footer)) {
            $html .= '<tfoot><tr>';
            foreach ($footer as $cell) {
                $cellAttrs = '';
                $cellContent = $cell;

                if (is_array($cell)) {
                    $cellContent = $cell['data'] ?? '';
                    $cellAttrs = self::buildAttributes($cell['attributes'] ?? []);
                }

                $html .= "<td{$cellAttrs}>" . htmlspecialchars($cellContent, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr></tfoot>';
        }

        $html .= '</table>';

        if ($responsive) {
            $html .= '</div>';
        }

        return $html . $children;
    }

    /**
     * Render an AJAX link element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderAjaxLink(array $element, string $children, array $context): string {
        $title = $element['#title'] ?? '';
        $url = $element['#url'] ?? '#';
        $attributes = $element['#attributes'] ?? [];
        $method = strtoupper($element['#method'] ?? 'GET');
        $target = $element['#target'] ?? null;
        $callback = $element['#callback'] ?? null;
        $confirm = $element['#confirm'] ?? null;

        // Add AJAX data attributes
        $attributes['data-ajax-url'] = $url;
        $attributes['data-ajax-method'] = $method;

        if ($target) {
            $attributes['data-ajax-target'] = $target;
        }

        if ($callback) {
            $attributes['data-ajax-callback'] = $callback;
        }

        if ($confirm) {
            $attributes['data-ajax-confirm'] = $confirm;
        }

        // Add default AJAX link class
        if (!isset($attributes['class'])) {
            $attributes['class'] = [];
        }
        $attributes['class'][] = 'ajax-link';

        // Prevent default link behavior
        $attributes['href'] = $url;

        $attrString = self::buildAttributes($attributes);
        $content = $children ?: htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return "<a{$attrString}>{$content}</a>";
    }

    /**
     * Render an AJAX button element
     *
     * @param array $element Render array element
     * @param string $children Rendered children
     * @param array $context Render context
     * @return string Rendered HTML
     */
    public static function renderAjaxButton(array $element, string $children, array $context): string {
        $value = $element['#value'] ?? 'Submit';
        $url = $element['#url'] ?? '';
        $attributes = $element['#attributes'] ?? [];
        $method = strtoupper($element['#method'] ?? 'POST');
        $target = $element['#target'] ?? null;
        $callback = $element['#callback'] ?? null;
        $confirm = $element['#confirm'] ?? null;
        $data = $element['#data'] ?? [];

        // Add AJAX data attributes
        $attributes['data-ajax-url'] = $url;
        $attributes['data-ajax-method'] = $method;

        if ($target) {
            $attributes['data-ajax-target'] = $target;
        }

        if ($callback) {
            $attributes['data-ajax-callback'] = $callback;
        }

        if ($confirm) {
            $attributes['data-ajax-confirm'] = $confirm;
        }

        if (!empty($data)) {
            $attributes['data-ajax-data'] = json_encode($data);
        }

        // Add default AJAX button class
        if (!isset($attributes['class'])) {
            $attributes['class'] = [];
        }
        $attributes['class'][] = 'ajax-button';

        // Set button type
        $attributes['type'] = $element['#button_type'] ?? 'button';

        $attrString = self::buildAttributes($attributes);
        $content = $children ?: htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return "<button{$attrString}>{$content}</button>";
    }

    /**
     * Build HTML attributes string from array
     *
     * @param array $attributes Associative array of attributes
     * @return string HTML attributes string (with leading space)
     */
    public static function buildAttributes(array $attributes): string {
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            // Handle boolean attributes
            if ($value === true) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                continue;
            }

            // Handle array values (e.g., class array)
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            $escapedKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $parts[] = "{$escapedKey}=\"{$escapedValue}\"";
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
