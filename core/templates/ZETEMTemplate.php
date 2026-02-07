<?php
/**
 * Zeus Template System (ZETEM) - Merged Version
 *
 * A lightweight template engine with Twig-like syntax.
 * Original code from: https://codeshack.io/lightweight-template-engine-php/
 * Thanks to David Adams
 *
 * Enhanced with modifications by Evangelos Rokas (c) 2024-2026
 *
 * Features:
 * - Dot notation ($region.header.0)
 * - Bracket notation ($var['key'])
 * - Variable context ($variable_context[])
 * - Set command {% set $variable = "text" %} with dot-path targets
 * - Macro support {% macro name($args) %} ... {% endmacro %} with nesting and defaults
 * - Conditionals {% if %} / {% elseif %} / {% else %} / {% fi|endif %}
 * - For-loop: {% for $item in $list %} with scoped variables
 * - Traditional foreach: {% foreach($list as $key => $item): %}
 * - $index tracking ($index.0 for current, $index.1 for parent)
 * - Include dependency tracking for cache invalidation
 * - Manual offset-based parsing (avoids regex callback state issues)
 *
 * @author Evangelos Rokas
 * @version 3.0 (Final Merged)
 * @date February 2026
 */

require_once __DIR__ . '/TemplateFilter.php';
require_once __DIR__ . '/TemplateSuggestion.php';
require_once __DIR__ . '/TemplateGlobalContext.php';

if (!class_exists("Renderer")) {

class Renderer {

    static $comment_block = [
        "html" => ["start" => "<!--", "end" => "-->"],
        "php"  => ['start' => "/*", "end" => "*/"],
        "js"   => ["start" => "/*", "end" => "*/"],
    ];

    static $blocks = [];
    static $template_path = [];
    static $cache_path = 'cache/';
    static $cache_enabled = FALSE;
    static $enable_comments = TRUE;
    static $template_files = [];
    static $template_mtimes = [];
    static $template_dependencies = [];

    static $macroArgs = [];
    static $loopVars = [];
    static $loopCounter = 0;
    static $tloopCounter = 1000;
    static $anonFuncParams = [];  // Track anonymous function parameters

    static $cstart;
    static $cend;

    /**
     * Initialize the template system
     *
     * @param string|array $template_path Template directory path(s)
     * @param bool $enable_cache Whether to enable template caching
     * @param string $cache_path Directory for cached templates
     * @param bool $enable_comments Whether to add debug comments
     * @param string $cblock_type Comment block type (html, php, js)
     */
    static function init($template_path, $enable_cache, $cache_path, $enable_comments = false, $cblock_type = 'html') {
        if (!$enable_cache) {
            self::$cache_enabled = true;
            self::$cache_path = $cache_path;
        } else {
            self::$cache_enabled = false;
        }

        if (isset($template_path)) {
            self::$template_path = is_array($template_path) ? $template_path : [$template_path];
        }

        if (isset($enable_comments)) {
            self::$enable_comments = $enable_comments;
        }

        self::$cstart = self::$comment_block[$cblock_type]['start'];
        self::$cend = self::$comment_block[$cblock_type]['end'];

        self::scanTemplates();
        self::loadDependencies();
        TemplateFilter::init();
        TemplateSuggestion::setTemplateFiles(self::$template_files);
        TemplateSuggestion::setExtension('.zetem');
    }

    static function scanTemplates() {
        self::$template_files = [];
        self::$template_mtimes = [];
        foreach (self::$template_path as $tpath) {
            self::findTemplates($tpath, self::$template_files);
        }
        TemplateSuggestion::setTemplateFiles(self::$template_files);
    }

    /**
     * Recursively find templates in a directory
     *
     * @param string $apath Directory path
     * @param array &$farr File array to populate
     */
    static function findTemplates($apath, &$farr) {
        if (!is_dir($apath)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($apath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $fnam) {
            $pathname = $fnam->getPathName();
            $farr[basename($pathname)] = $pathname;
            self::$template_mtimes[$pathname] = $fnam->getMTime();
        }
    }

    /**
     * Get template modification time (optimized with cached mtimes)
     *
     * @param string $filepath Template file path
     * @return int Modification timestamp
     */
    static function getTemplateMtime($filepath) {
        return self::$template_mtimes[$filepath] ?? filemtime($filepath);
    }

    static function saveDependencies() {
        $dep_file = self::$cache_path . '_dependencies.php';
        file_put_contents($dep_file, '<?php return ' . var_export(self::$template_dependencies, true) . ';');
    }

    static function loadDependencies() {
        $dep_file = self::$cache_path . '_dependencies.php';
        if (file_exists($dep_file)) {
            self::$template_dependencies = include $dep_file;
        }
    }

    /**
     * Render a template and output directly
     *
     * @param string $file Template file name
     * @param array $data Data to pass to template
     * @param array|null $stemplate Template suggestions
     */
    static function view($file, $data = [], $stemplate = null) {
        $cached_file = self::cache($file, $stemplate);
        $variable_context = $data;
        $_index_stack = [];
        require $cached_file;
    }

    /**
     * Render a template and return as string
     *
     * @param string $file Template file name
     * @param array $data Data to pass to template
     * @param array|null $stemplates Template suggestions
     * @return string Rendered output
     */
    static function render($file, $data = [], $stemplates = null): string {
        ob_start();
        $cached_file = self::cache($file, $stemplates);
        $variable_context = $data;
        TemplateGlobalContext::globalAssign( $variable_context );

        $_index_stack = [];
        require $cached_file;

        TemplateGlobalContext::reset();
        return ob_get_clean();
    }

    /**
     * Render template safely (returns 404 on missing template)
     *
     * @param string $file Template file name
     * @param array $data Data to pass to template
     * @param array|null $stemplated Template suggestions
     * @return string Rendered output or 404
     */
    static function renderSafe($file, $data = [], $stemplated = null): string {
        if (self::existsTemplate($file)) {
            return self::render($file, $data, $stemplated);
        } else {
            return function_exists('error_404') ? error_404() : '';
        }
    }

    /**
     * Render template without debug comments
     *
     * @param string $file Template file name
     * @param array $data Data to pass to template
     * @param array|null $stemplates Template suggestions
     * @return string Rendered output
     */
    static function renderRaw($file, $data = [], $stemplates = null): string {
        $comments = self::$enable_comments;
        self::$enable_comments = false;
        $result = self::render($file, $data, $stemplates);
        self::$enable_comments = $comments;
        return $result;
    }

    /**
     * Check if a template exists
     *
     * @param string $tname Template name
     * @return bool
     */
    static function existsTemplate($tname) {
        return array_key_exists($tname, self::$template_files);
    }

    /**
     * Get template suggestions using power set shuffle
     *
     * @param array $keywords Keywords to combine
     * @param string $prefix Optional prefix
     * @return array Suggestions
     */
    static function getTemplateSuggestionsShuffle(array $keywords, string $prefix = ''): array {
        return TemplateSuggestion::fromKeywords($keywords, $prefix);
    }

    /**
     * Get best matching template from suggestions
     *
     * @param array $suggestions Template suggestions
     * @return string|null Template name or null
     */
    static function getTemplate($suggestions) {
        return TemplateSuggestion::findBestMatch($suggestions);
    }

    public static function getTemplateSuggestions($args, callable $callback, &$tsuggestions) {
        $callback($args, $tsuggestions);
    }

    /**
     * Register a filter callback (legacy support)
     *
     * @param string $filter Filter name
     * @param callable $callback Filter callback
     */
    static function filterRegister($filter, $callback) {
        TemplateFilter::register($filter, $callback);
    }

    /**
     * Clear all cached templates
     */
    static function clearCache() {
        foreach (glob(self::$cache_path . '*') as $file) {
            if (is_file($file)) unlink($file);
        }
        self::$template_dependencies = [];
    }

    /**
     * Emit a debug comment
     *
     * @param string $acomment Comment text
     * @param string $acode Code to prepend
     * @param bool $anl Add newline
     * @return string Comment HTML/PHP
     */
    static function emmitComment($acomment, $acode = "", $anl = true) {
        if (!self::$enable_comments) return $acode;
        return $acode . ($anl ? PHP_EOL : '') . self::$cstart . $acomment . self::$cend . ($anl ? PHP_EOL : '');
    }

    static function resetCompilationState() {
        self::$blocks = [];
        self::$macroArgs = [];
        self::$loopVars = [];
        self::$anonFuncParams = [];
        self::$loopCounter = 0;
        self::$tloopCounter = 1000;
    }

    /**
     * Cache and compile a template
     *
     * @param string $file Template file name
     * @param array|null $stemplates Template suggestions
     * @return string Path to cached file
     */
    static function cache($file, $stemplates) {
        if (!file_exists(self::$cache_path)) {
            mkdir(self::$cache_path, 0744, true);
        }

        $cached_file = self::$cache_path . str_replace(['/', '.zetem'], ['_', ''], $file . '.php');
        $needs_recompile = false;

        if (!self::$cache_enabled || !file_exists($cached_file)) {
            $needs_recompile = true;
        } else {
            $deps = self::$template_dependencies[$file] ?? [];
            $cache_time = filemtime($cached_file);
            foreach ($deps as $dep_path) {
                if (file_exists($dep_path) && self::getTemplateMtime($dep_path) > $cache_time) {
                    $needs_recompile = true;
                    break;
                }
            }
        }

        if ($needs_recompile) {
            self::resetCompilationState();
            $dependencies = [];
            $code = self::includeFiles($file, $dependencies);
            self::$template_dependencies[$file] = $dependencies;

            if (is_array($stemplates) && count($stemplates) >= 2) {
                $code = self::emmitComment(" template suggestions: ") . $code;
                foreach ($stemplates[0] as $sugg) {
                    $marker = ($sugg . '.zetem' == $stemplates[1]) ? " * " : " - ";
                    $code = self::emmitComment($marker . $sugg . '.zetem', $code, false);
                }
            }

            $code = self::compileCode($code);
            file_put_contents($cached_file, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code);
            self::saveDependencies();
        }

        return $cached_file;
    }

    /**
     * Include files (extends, include directives)
     * Processes one include at a time using while-loop + substr_replace
     * to avoid str_replace replacing multiple identical tags at once.
     *
     * @param string $file Template file name
     * @param array &$dependencies Collected dependency paths
     * @return string Template code with includes resolved
     */
    static function includeFiles($file, &$dependencies = null) {
        if ($dependencies === null) $dependencies = [];
        if (!isset(self::$template_files[$file])) return '';

        $filepath = self::$template_files[$file];
        if (!in_array($filepath, $dependencies)) $dependencies[] = $filepath;

        $code = self::emmitComment(" begin include file : $file from $filepath ", false);
        $code .= file_get_contents($filepath);

        $includePattern = '/\{%\s*(extends|include)\s*[\'"]?\s*(.*?)\s*[\'"]?\s*%\}/i';

        while (preg_match($includePattern, $code, $match)) {
            $includedFile = trim($match[2]);

            if (isset(self::$template_files[$includedFile])) {
                $replacement = self::emmitComment(" {$match[1]}: $includedFile ")
                    . self::includeFiles($includedFile, $dependencies)
                    . self::emmitComment(" end {$match[1]}: $includedFile ", '', false);
            } else {
                $replacement = self::emmitComment(" WARNING: template not found: $includedFile ");
            }

            // Replace only the FIRST occurrence
            $pos = strpos($code, $match[0]);
            $code = substr_replace($code, $replacement, $pos, strlen($match[0]));
        }

        $code = self::emmitComment(" end include file : $file", $code, true);

        return $code;
    }

    /**
     * Main code compilation
     *
     * @param string $code Template code
     * @return string Compiled PHP code
     */
    static function compileCode($code) {
        $code = self::compileComments($code);
        $code = self::compileBlock($code);
        $code = self::compileYield($code);
        $code = self::compileMacros($code);
        $code = self::compileSet($code);
        $code = self::compileForLoops($code);
        $code = self::compileConditionals($code);
        $code = self::compileEscapedEchos($code);
        $code = self::compileEchos($code);
        $code = self::compilePHP($code);
        return $code;
    }

    /**
     * Remove template comments {# ... #}
     *
     * @param string $code Template code
     * @return string Code without comments
     */
    static function compileComments($code) {
        return preg_replace('~\{#\s*(.+?)\s*#}~is', '', $code);
    }

    /**
     * Compile template blocks
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileBlock($code) {
        preg_match_all('/\{%\s*block\s*(\w+)\s*%}(.*?)\{%\s*endblock\s*%}/is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], self::$blocks)) self::$blocks[$value[1]] = '';
            self::$blocks[$value[1]] = strpos($value[2], '@parent') !== false
                ? str_replace('@parent', self::$blocks[$value[1]], $value[2])
                : $value[2];
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    /**
     * Compile yield statements
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileYield($code) {
        foreach (self::$blocks as $block => $value) {
            $code = preg_replace('/\{%\s*yield\s*' . $block . '\s*%}/i', $value, $code);
        }
        return preg_replace('/\{%\s*yield\s*(\w+)\s*%}/i', '', $code);
    }

    /**
     * Check if a variable name is scoped (macro arg, loop variable, or anonymous function parameter)
     *
     * @param string $varName Variable name without $
     * @return bool
     */
    static function isScopedVariable($varName) {
        return isset(self::$macroArgs[$varName]) || isset(self::$loopVars[$varName]) ||
               isset(self::$anonFuncParams[$varName]) ||
               strpos($varName, '_macro_') === 0 || strpos($varName, '_loop_') === 0 || strpos($varName, '_tloop_') === 0;
    }

    /**
     * Convert dot notation to array access notation
     * e.g., $user.profile.name becomes $variable_context['user']['profile']['name']
     *
     * @param string $expr Expression to convert
     * @return string Converted expression
     */
    static function convertDotNotation($expr) {
        return preg_replace_callback(
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\.([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*))?/',
            function($matches) {
                $varName = $matches[1];
                $keys = isset($matches[2]) ? $matches[2] : null;

                if ($varName === 'index') {
                    if ($keys !== null) {
                        return '$_index_stack[count($_index_stack) - 1 - ' . (int)$keys . ']';
                    }
                    return '$_index_stack[count($_index_stack) - 1]';
                }

                $keyChain = '';
                if ($keys !== null) {
                    foreach (explode('.', $keys) as $key) {
                        $keyChain .= is_numeric($key) ? "[$key]" : "['$key']";
                    }
                }

                if (self::isScopedVariable($varName)) {
                    return '$' . $varName . $keyChain;
                }
                return "\$variable_context['$varName']" . $keyChain;
            },
            $expr
        );
    }

    /**
     * Convert bracket notation to variable_context access
     * e.g., $var['key'] becomes $variable_context['var']['key']
     * Runs after convertDotNotation to catch bracket-style access
     *
     * @param string $expr Expression to convert
     * @return string Converted expression
     */
    static function convertArrayNotation($expr) {
        $pattern = '/\$([a-zA-Z_][a-zA-Z0-9_]*)(\[(?:[\'"][^\'"]*[\'"]|\d+)\])+/';

        return preg_replace_callback($pattern, function($matches) {
            $varName = $matches[1];
            $fullMatch = $matches[0];

            // Skip already-converted, index, and scoped variables
            if ($varName === 'variable_context' || $varName === 'index' ||
                $varName === '_index_stack' ||
                self::isScopedVariable($varName)) {
                return $fullMatch;
            }

            $accessPart = substr($fullMatch, strlen('$' . $varName));
            return '$variable_context[\'' . $varName . '\']' . $accessPart;
        }, $expr);
    }

    /**
     * Extract anonymous function parameters from an expression
     * Returns array of parameter names found in anonymous functions
     *
     * @param string $expr Expression to analyze
     * @return array Array of parameter names (without $)
     */
    static function extractAnonFuncParams($expr) {
        $params = [];

        // Match anonymous functions: function($param1, $param2) { ... }
        // Also match fn($param) => ... (arrow functions)
        if (preg_match_all('/(?:function|fn)\s*\(([^)]*)\)/', $expr, $matches)) {
            foreach ($matches[1] as $paramsList) {
                // Extract individual parameter names
                if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $paramsList, $paramMatches)) {
                    foreach ($paramMatches[1] as $param) {
                        $params[] = $param;
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Process a full expression through both dot and bracket notation conversion
     * Handles anonymous function parameters by temporarily marking them as scoped
     *
     * @param string $expr Expression to process
     * @return string Processed expression
     */
    static function processExpression($expr) {
        // Extract anonymous function parameters before processing
        $anonParams = self::extractAnonFuncParams($expr);

        // Temporarily mark anonymous function parameters as scoped
        $tempScoped = [];
        foreach ($anonParams as $param) {
            if (!isset(self::$anonFuncParams[$param])) {
                self::$anonFuncParams[$param] = true;
                $tempScoped[] = $param;
            }
        }

        // Process the expression (scoped params won't be converted to $variable_context)
        $expr = self::convertDotNotation($expr);
        $expr = self::convertArrayNotation($expr);

        // Clean up temporary scoped parameters
        foreach ($tempScoped as $param) {
            unset(self::$anonFuncParams[$param]);
        }

        return $expr;
    }

    /**
     * Compile macros with offset-based nesting support and default arguments
     * {% macro name($arg1, $arg2 = "default") %} ... {% endmacro %}
     * {% call name($val1, $val2) %}
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileMacros($code) {
        static $macroCounter = 0;

        $offset = 0;
        while (preg_match('~\{%\s*macro\s+(\w+)\s*\(([^)]*)\)\s*%}~is', $code, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $macroStart = $matches[0][1];
            $macroName = $matches[1][0];
            $argsStr = $matches[2][0];
            $headerLen = strlen($matches[0][0]);

            // Find matching endmacro using depth tracking (supports nested macros)
            $searchPos = $macroStart + $headerLen;
            $depth = 1;
            $bodyEnd = false;

            while ($depth > 0 && preg_match('~\{%\s*(macro|endmacro)\s*~is', $code, $tagMatch, PREG_OFFSET_CAPTURE, $searchPos)) {
                $tag = strtolower(trim($tagMatch[1][0]));
                $tagPos = $tagMatch[0][1];
                if ($tag === 'macro') $depth++;
                elseif ($tag === 'endmacro') { $depth--; if ($depth === 0) { $bodyEnd = $tagPos; break; } }
                $searchPos = $tagPos + strlen($tagMatch[0][0]);
            }

            if ($bodyEnd === false) { $offset = $macroStart + $headerLen; continue; }

            $body = substr($code, $macroStart + $headerLen, $bodyEnd - ($macroStart + $headerLen));
            preg_match('~\{%\s*endmacro\s*%}~is', $code, $endMatch, PREG_OFFSET_CAPTURE, $bodyEnd);
            $endLen = strlen($endMatch[0][0]);

            // Parse arguments with optional defaults: $arg1, $arg2 = "default"
            $argNames = [];
            $argDefaults = [];
            if (!empty(trim($argsStr))) {
                $argParts = preg_split('/\s*,\s*/', trim($argsStr));
                foreach ($argParts as $argPart) {
                    $argPart = trim($argPart);
                    if (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $argPart, $m)) {
                        $argNames[] = $m[1];
                        $argDefaults[$m[1]] = trim($m[2]);
                    } elseif (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/', $argPart, $m)) {
                        $argNames[] = $m[1];
                    }
                }
            }

            $macroCounter++;
            $previousMacroArgs = self::$macroArgs;
            foreach ($argNames as $argName) self::$macroArgs[$argName] = '_macro_' . $macroCounter . '_' . $argName;

            $processedBody = self::compileMacroBody($body, $macroCounter, $argNames);
            self::$macroArgs = $previousMacroArgs;

            // Build PHP function arguments with defaults
            $phpArgs = [];
            foreach ($argNames as $arg) {
                $phpArg = '$_macro_' . $macroCounter . '_' . $arg;
                if (isset($argDefaults[$arg])) {
                    $phpArg .= ' = ' . $argDefaults[$arg];
                }
                $phpArgs[] = $phpArg;
            }
            $phpArgsStr = implode(', ', $phpArgs);

            $replacement = "<?php if(!function_exists('$macroName')) { function $macroName($phpArgsStr) { global \$variable_context, \$_index_stack; ?>$processedBody<?php } } ?>";
            $code = substr_replace($code, $replacement, $macroStart, ($bodyEnd - $macroStart) + $endLen);
            $offset = $macroStart + strlen($replacement);
        }

        // Compile macro calls: {% call name($val1, $val2) %}
        $code = preg_replace_callback('~\{%\s*call\s+(\w+)\s*\(([^)]*)\)\s*%}~is', function($m) {
            $args = trim($m[2]);
            if (!empty($args)) $args = self::processExpression($args);
            return "<?php echo {$m[1]}($args); ?>";
        }, $code);

        return $code;
    }

    static function compileMacroBody($code, $macroId, $argNames) {
        foreach ($argNames as $argName) {
            $code = preg_replace('/\$' . preg_quote($argName, '/') . '\b/', '\$_macro_' . $macroId . '_' . $argName, $code);
        }
        $code = self::compileComments($code);
        $code = self::compileSet($code);
        $code = self::compileForLoops($code);
        $code = self::compileConditionals($code);
        $code = self::compileEscapedEchos($code);
        $code = self::compileEchos($code);
        $code = self::compilePHP($code);
        return $code;
    }

    /**
     * Compile set commands with dot-path target support
     * {% set $user.name = "John" %} -> $variable_context['user']['name'] = "John";
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileSet($code) {
        $offset = 0;
        while (preg_match('~\{%\s*set\s+\$([a-zA-Z_][a-zA-Z0-9_.]*)\s*=\s*(.+?)\s*%}~is', $code, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $varPath = $matches[1][0];
            $expr = self::processExpression($matches[2][0]);

            // Build target with dot-path support
            $keys = explode('.', $varPath);
            $target = '$variable_context';
            foreach ($keys as $key) {
                $target .= is_numeric($key) ? "[$key]" : "['$key']";
            }

            $replacement = "<?php $target = $expr; ?>";
            $code = substr_replace($code, $replacement, $matches[0][1], strlen($matches[0][0]));
            $offset = $matches[0][1] + strlen($replacement);
        }
        return $code;
    }

    /**
     * Compile for loops with scoped variables
     * {% for $item in $list %} or {% for $key, $item in $list %}
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileForLoops($code) {
        // Modern syntax: {% for $item in $list %}
        $offset = 0;
        while (preg_match('~\{%\s*for\s+(?:\$(\w+)\s*,\s*)?\$(\w+)\s+in\s+(.+?)\s*%}~is', $code, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $keyVar = isset($matches[1][0]) && $matches[1][0] !== '' ? $matches[1][0] : null;
            $itemVar = $matches[2][0];
            $listExpr = self::processExpression($matches[3][0]);

            self::$loopCounter++;
            $loopId = self::$loopCounter;
            $scopedItem = '_loop_' . $loopId . '_' . $itemVar;
            $scopedKey = $keyVar ? '_loop_' . $loopId . '_' . $keyVar : null;

            self::$loopVars[$itemVar] = $scopedItem;
            if ($keyVar) self::$loopVars[$keyVar] = $scopedKey;

            $replacement = $scopedKey
                ? "<?php \$_index_stack[] = 0; foreach ($listExpr as \$$scopedKey => \$$scopedItem): ?>"
                : "<?php \$_index_stack[] = 0; foreach ($listExpr as \$$scopedItem): ?>";

            $code = substr_replace($code, $replacement, $matches[0][1], strlen($matches[0][0]));
            $offset = $matches[0][1] + strlen($replacement);
        }

        $code = preg_replace('~\{%\s*endfor\s*%}~is', '<?php $_index_stack[count($_index_stack)-1]++; endforeach; array_pop($_index_stack); ?>', $code);

        // Traditional foreach: {% foreach($list as $key => $item): %}
        $offset = 0;
        while (preg_match('~\{%\s*foreach\s*\(\s*(.+?)\s+as\s+(?:\$(\w+)\s*=>\s*)?\$(\w+)\s*\)\s*:\s*%}~is', $code, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $listExpr = self::processExpression($matches[1][0]);
            $keyVar = isset($matches[2][0]) && $matches[2][0] !== '' ? $matches[2][0] : null;
            $itemVar = $matches[3][0];

            self::$tloopCounter++;
            $loopId = self::$tloopCounter;
            $scopedItem = '_tloop_' . $loopId . '_' . $itemVar;
            $scopedKey = $keyVar ? '_tloop_' . $loopId . '_' . $keyVar : null;

            self::$loopVars[$itemVar] = $scopedItem;
            if ($keyVar) self::$loopVars[$keyVar] = $scopedKey;

            $replacement = $scopedKey
                ? "<?php foreach ($listExpr as \$$scopedKey => \$$scopedItem): ?>"
                : "<?php foreach ($listExpr as \$$scopedItem): ?>";

            $code = substr_replace($code, $replacement, $matches[0][1], strlen($matches[0][0]));
            $offset = $matches[0][1] + strlen($replacement);
        }

        $code = preg_replace('~\{%\s*endforeach\s*%}~is', '<?php endforeach; ?>', $code);

        foreach (self::$loopVars as $original => $scoped) {
            $code = preg_replace('/\$' . preg_quote($original, '/') . '\b/', '\$' . $scoped, $code);
        }
        return $code;
    }

    /**
     * Compile conditionals with both Twig and traditional PHP styles
     * Supports: {% if condition %}, {% if (condition): %}, {% else %}, {% else: %}
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileConditionals($code) {
        // Twig-style: {% if condition %}
        $code = preg_replace_callback('~\{%\s*if\s+(?!\()(.+?)\s*%}~is', fn($m) => "<?php if (" . self::processExpression($m[1]) . "): ?>", $code);
        // Traditional PHP-style: {% if (condition): %}
        $code = preg_replace_callback('~\{%\s*if\s*\(\s*(.+?)\s*\)\s*:\s*%}~is', fn($m) => "<?php if (" . self::processExpression($m[1]) . "): ?>", $code);

        $code = preg_replace_callback('~\{%\s*elseif\s+(?!\()(.+?)\s*%}~is', fn($m) => "<?php elseif (" . self::processExpression($m[1]) . "): ?>", $code);
        $code = preg_replace_callback('~\{%\s*elseif\s*\(\s*(.+?)\s*\)\s*:\s*%}~is', fn($m) => "<?php elseif (" . self::processExpression($m[1]) . "): ?>", $code);

        // Support both {% else %} and {% else: %}
        $code = preg_replace('~\{%\s*else\s*:?\s*%}~is', '<?php else: ?>', $code);
        $code = preg_replace('~\{%\s*(endif|fi)\s*%}~is', '<?php endif; ?>', $code);
        return $code;
    }

    /**
     * Compile escaped echo statements {{{ $var }}}
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileEscapedEchos($code) {
        return preg_replace_callback('~\{{{\s*(.+?)\s*}}}~is', fn($m) => '<?php echo htmlspecialchars(' . self::processEchoExpression($m[1]) . ', ENT_QUOTES, \'UTF-8\'); ?>', $code);
    }

    /**
     * Compile echo statements {{ $var }} with filters
     *
     * @param string $code Template code
     * @return string Compiled code
     */
    static function compileEchos($code) {
        return preg_replace_callback('~\{{\s*(.+?)\s*}}~is', fn($m) => '<?php echo ' . self::processEchoExpression($m[1]) . '; ?>', $code);
    }

    static function processEchoExpression($expr) {
        return strpos($expr, '|') !== false ? self::compileFilters($expr) : self::processExpression($expr);
    }

    static function compileFilters($expr) {
        $parts = preg_split('/\s*\|\s*/', $expr);
        $value = self::processExpression(trim(array_shift($parts)));

        foreach ($parts as $filter) {
            $filter = trim($filter);
            if (preg_match('/^(\w+)\s*\((.+)\)$/s', $filter, $m)) {
                $args = self::parseFilterArgs($m[2]);
                $value = "TemplateFilter::apply('{$m[1]}', $value, [" . implode(', ', $args) . "])";
            } else {
                $value = "TemplateFilter::apply('$filter', $value)";
            }
        }
        return $value;
    }

    /**
     * Parse filter arguments string
     *
     * @param string $argsStr Arguments string
     * @return array Parsed arguments
     */
    static function parseFilterArgs($argsStr) {
        $args = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($argsStr); $i++) {
            $char = $argsStr[$i];
            if (!$inString && ($char === '"' || $char === "'")) { $inString = true; $stringChar = $char; $current .= $char; }
            elseif ($inString && $char === $stringChar && ($i === 0 || $argsStr[$i-1] !== '\\')) { $inString = false; $current .= $char; }
            elseif (!$inString && ($char === '(' || $char === '[')) { $depth++; $current .= $char; }
            elseif (!$inString && ($char === ')' || $char === ']')) { $depth--; $current .= $char; }
            elseif (!$inString && $char === ',' && $depth === 0) {
                $arg = trim($current);
                if ($arg !== '') $args[] = self::processExpression($arg);
                $current = '';
            }
            else { $current .= $char; }
        }
        $arg = trim($current);
        if ($arg !== '') $args[] = self::processExpression($arg);
        return $args;
    }

    static function compilePHP($code) {
        return preg_replace_callback('~\{%\s*(.+?)\s*%}~is', fn($m) => "<?php " . self::processExpression($m[1]) . " ?>", $code);
    }

}

}

if (!class_exists("ZETEMTemplate")) { class_alias('Renderer', 'ZETEMTemplate'); }
