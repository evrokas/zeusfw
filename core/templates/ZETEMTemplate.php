<?php

/* Zeus Template System - Enhanced Version
 *
 * Original code from url: https://codeshack.io/lightweight-template-engine-php/
 * Thanks to David Adams
 * 
 * Used with modifications by Evangelos Rokas (c) 2024-2025
 * Enhanced version with:
 * - Dot notation support ($region.header.0)
 * - Variable context ($variable_context[])
 * - Set command {% set $variable = "text" %}
 * - Macro support {% macro name($args) %} ... {% endmacro %}
 * - Enhanced conditionals {% if test %} / {% elseif %} / {% else %} / {% fi %}
 * - For-loop with 'in' notation: {% for $item in $list %}
 * - Scoped iteration variables and macro arguments
 * - $index tracking ($index.0 for current, $index.1 for parent, etc.)
 * - Include dependency tracking for proper cache invalidation
 * - Integration with TemplateFilter and TemplateSuggestion classes
 */

require_once __DIR__ . '/TemplateFilter.php';
require_once __DIR__ . '/TemplateSuggestion.php';

if(!class_exists("Renderer")) {

class Renderer {
    static $comment_block = [
        "html" => ["start" => "<!--", "end" => "-->"],
        "php" => ['start' => "/*", "end" => "*/"],
        "js" => ["start" => "/*", "end" => "*/"],
    ];

    static $blocks = array();
    static $template_path = array();
    static $cache_path = 'cache/';
    static $cache_enabled = FALSE;
    static $enable_comments = TRUE;
    static $template_files = array();
    
    // Store file modification times collected during directory scan
    // This avoids calling filemtime() twice for the same files
    static $template_mtimes = array();
    
    // Store template dependencies (included files) for cache invalidation
    // Format: ['main.zetem' => ['/path/to/header.zetem', '/path/to/footer.zetem']]
    static $template_dependencies = array();
    
    static $cstart;
    static $cend;
    static $macros = [];
    static $scopedVars = [];

    static function init($template_path, $enable_cache, $cache_path, $enable_comments = false, $cblock_type = 'html') {
        if(!$enable_cache) {
            self::$cache_enabled = true;
            self::$cache_path = $cache_path;
        } else self::$cache_enabled = false;

        if(isset($template_path)) {
            if(is_array($template_path)) self::$template_path = $template_path;
            else array_push(self::$template_path, $template_path);
        }

        if(isset($enable_comments)) self::$enable_comments = $enable_comments;

        self::$cstart = self::$comment_block[$cblock_type]['start'];
        self::$cend = self::$comment_block[$cblock_type]['end'];

        self::scanTemplates();
        
        // Load persisted dependencies from disk cache
        self::loadDependencies();
        
        TemplateFilter::init();
        TemplateSuggestion::setTemplateFiles(self::$template_files);
        TemplateSuggestion::setExtension('.zetem');
    }

    /**
     * Scan template directories and collect both paths and modification times
     * This leverages the directory iteration to collect mtimes upfront,
     * avoiding redundant filesystem calls later during cache checks
     */
    static function scanTemplates() {
        self::$template_files = array();
        self::$template_mtimes = array();
        
        foreach(self::$template_path as $tpath) {
            self::findTemplates($tpath, self::$template_files, self::$template_mtimes);
        }
        
        TemplateSuggestion::setTemplateFiles(self::$template_files);
    }

    static function view($file, $data = array(), $stemplate = null) {
        $cached_file = self::cache($file, $stemplate);
        $variable_context = $data;
        $_index_stack = [];
        extract(['variable_context' => $variable_context, '_index_stack' => $_index_stack], EXTR_SKIP);
        require $cached_file;
    }

    static function render($file, $data = array(), $stemplates = null): string {
        // echopre("data: " . print_r($data, 1));
        ob_start();
        $cached_file = self::cache($file, $stemplates);
        $variable_context = $data;
        $_index_stack = [];
        // extract(['variable_context' => $variable_context, '_index_stack' => $_index_stack], EXTR_SKIP);
        // echopre("cached file: {$cached_file} variable_context[] = " . print_r($data, 1));

        require $cached_file;
        $buffer = ob_get_contents();
        ob_end_clean();
        return $buffer;
    }

    static function renderSafe($file, $data = array(), $stemplated = null): string {
        if(self::existsTemplate($file))
            return self::render($file, $data, $stemplated);
        else
            return error_404();
    }

    static function renderRaw($file, $data = array(), $stemplates = null): string {
        $comments = self::$enable_comments;
        self::$enable_comments = false;
        $result = self::render($file, $data, $stemplates);
        self::$enable_comments = $comments;
        return $result;
    }

    static function existsTemplate($tname) {
        return array_key_exists($tname, self::$template_files);
    }
    
    /**
     * Find templates and collect modification times during the same iteration
     * This is more efficient than calling filemtime() separately later
     */
    static function findTemplates($apath, &$farr, &$mtimes) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($apath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY);
            
        foreach($files as $fnam) {
            $f = explode('/', $fnam);
            $filename = $f[array_key_last($f)];
            $fullpath = $fnam->getPathName();
            
            $farr[$filename] = $fullpath;
            
            // Collect mtime during scan - avoids calling filemtime() again later
            $mtimes[$fullpath] = $fnam->getMTime();
        }
    }
    
    /**
     * Get modification time for a template file
     * Uses cached mtime from scan when available, falls back to filemtime()
     */
    static function getTemplateMtime($filepath) {
        if (isset(self::$template_mtimes[$filepath])) {
            return self::$template_mtimes[$filepath];
        }
        // Fallback for files not in scan (shouldn't happen normally)
        return filemtime($filepath);
    }
    
    static function getTemplateSuggestions($args, callable $callback, &$tsuggestions) {
        $callback($args, $tsuggestions);
    }

    static function getTemplateSuggestionsShuffle(array $keywords, string $prefix = ''): array {
        return TemplateSuggestion::fromKeywords($keywords, $prefix);
    }

    static function getTemplate($suggestions) {
        return TemplateSuggestion::findBestMatch($suggestions);
    }

    /**
     * Cache management with dependency tracking
     * 
     * The cache is invalidated if:
     * 1. Cache is disabled
     * 2. Cached file doesn't exist
     * 3. ANY dependency (main template or included files) is newer than cached file
     */
    static function cache($file, $stemplates) {
        if (!file_exists(self::$cache_path)) {
            mkdir(self::$cache_path, 0744, true);
        }
        
        $cached_file = self::$cache_path . str_replace(array('/', '.zetem'), array('_', ''), $file . '.php');

        // Determine if recompilation is needed
        $needs_recompile = !self::$cache_enabled || !file_exists($cached_file);
        
        if (!$needs_recompile) {
            // Check if we have stored dependencies for this template
            if (isset(self::$template_dependencies[$file])) {
                // Get cached file modification time
                $cached_time = filemtime($cached_file);
                
                // Check each dependency - if ANY is newer, we need to recompile
                foreach (self::$template_dependencies[$file] as $dep_file) {
                    $dep_mtime = self::getTemplateMtime($dep_file);
                    if ($dep_mtime > $cached_time) {
                        $needs_recompile = true;
                        break; // Early exit - no need to check more
                    }
                }
            } else {
                // No stored dependencies - force recompile to build them
                // This handles first-time compilation and cache recovery
                $needs_recompile = true;
            }
        }
        
        if ($needs_recompile) {
            // Build template suggestions comment if enabled
            if ($stemplates != null && self::$enable_comments) {
                $code = self::$cstart . " template suggestions: " . self::$cend;
                foreach($stemplates[0] as $sugg) {
                    $code .= self::$cstart;
                    if ($sugg . '.zetem' == $stemplates[1]) $code .= " * ";
                    else $code .= " - ";
                    $code .= $sugg . '.zetem' . self::$cend;
                }
            } else {
                $code = '';
            }

            // Reset compilation state
            self::$scopedVars = [];
            self::$macros = [];
            self::$blocks = [];

            // Collect dependencies during file inclusion (this is free - we're already reading files)
            $dependencies = array();
            $code .= self::includeFiles($file, $dependencies);
            
            // Store dependencies for future cache checks
            self::$template_dependencies[$file] = $dependencies;
            
            // Persist dependencies to disk for cross-request efficiency
            self::saveDependencies();
            
            // Compile the template
            $code = self::compileCode($code);
            
            // Write to cache
            file_put_contents($cached_file, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code);
        }
        
        return $cached_file;
    }
    
    /**
     * Save dependencies to disk for persistence across requests
     * This avoids rebuilding the dependency array on every request
     */
    static function saveDependencies() {
        $dep_file = self::$cache_path . '_dependencies.php';
        $content = '<?php return ' . var_export(self::$template_dependencies, true) . ';';
        file_put_contents($dep_file, $content);
    }
    
    /**
     * Load dependencies from disk cache
     */
    static function loadDependencies() {
        $dep_file = self::$cache_path . '_dependencies.php';
        if (file_exists($dep_file)) {
            self::$template_dependencies = include $dep_file;
        }
    }

    static function clearCache() {
        foreach(glob(self::$cache_path . '*') as $file) {
            unlink($file);
        }
        // Also clear in-memory dependencies
        self::$template_dependencies = array();
    }

    static function emmitComment($acomment, $acode = "", $anl = true) {
        if(!self::$enable_comments) return $acode;
        $acode = $acode . ($anl ? PHP_EOL : '') . self::$cstart . $acomment . self::$cend . ($anl ? PHP_EOL : '');
        return ($acode);
    }

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
     * Include files and track dependencies
     * Dependencies are collected during the normal include process - no extra I/O
     * 
     * @param string $file Template file name
     * @param array &$dependencies Reference to array collecting all dependencies
     * @return string Processed template code
     */
    static function includeFiles($file, &$dependencies = null) {
        // Initialize dependencies array for root call
        if ($dependencies === null) {
            $dependencies = array();
        }
        
        $filepath = self::$template_files[$file];
        
        // Track this file as a dependency
        if (!in_array($filepath, $dependencies)) {
            $dependencies[] = $filepath;
        }
        
        $code = self::emmitComment(" begin include file : $file from " . $filepath . " ", false);
        $code .= file_get_contents($filepath);
        
        // Find and process includes/extends
        preg_match_all('/\{%\s*(extends|include)\s*\'?(.*?)\'?\s*%\}/i', $code, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $value) {
            // Recursively include and track dependencies
            $code = str_replace($value[0], self::includeFiles($value[2], $dependencies), $code);
        }
        
        // Remove any remaining include/extends tags
        $code = preg_replace('/\{%\s*(extends|include)\s*\'?(.*?)\'?\s*%\}/i', '', $code);
        $code = self::emmitComment(" end include file : $file", $code, true);
        
        return $code;
    }

    static function compileComments($code) {
        return preg_replace('~\{#\s*(.+?)\s*\#}~is', '', $code);
    }

    static function compileMacros($code) {
        $macroPattern = '/\{%\s*macro\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(\s*(.*?)\s*\)\s*%\}(.*?)\{%\s*endmacro\s*%\}/is';
        $macroId = 0;
        
        return preg_replace_callback($macroPattern, function($matches) use (&$macroId) {
            $macroName = $matches[1];
            $argsStr = trim($matches[2]);
            $body = $matches[3];
            $currentMacroId = $macroId++;
            
            $args = [];
            $argDefaults = [];
            if (!empty($argsStr)) {
                $argParts = preg_split('/\s*,\s*/', $argsStr);
                foreach ($argParts as $argPart) {
                    if (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $argPart, $m)) {
                        $args[] = $m[1];
                        $argDefaults[$m[1]] = $m[2];
                    } elseif (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/', $argPart, $m)) {
                        $args[] = $m[1];
                    }
                }
            }
            
            self::$macros[$macroName] = ['id' => $currentMacroId, 'args' => $args, 'defaults' => $argDefaults];
            
            $scopedArgs = [];
            foreach ($args as $arg) {
                $scopedName = '_macro_' . $currentMacroId . '_' . $arg;
                $scopedArgs[$arg] = $scopedName;
                self::$scopedVars[$arg] = $scopedName;
            }
            
            $processedBody = $body;
            foreach ($scopedArgs as $original => $scoped) {
                $pattern = '/\$' . preg_quote($original, '/') . '(?![a-zA-Z0-9_])/';
                $processedBody = preg_replace($pattern, '$' . $scoped, $processedBody);
            }
            
            $phpArgs = [];
            foreach ($args as $arg) {
                $scopedName = $scopedArgs[$arg];
                if (isset($argDefaults[$arg])) {
                    $phpArgs[] = '$' . $scopedName . ' = ' . $argDefaults[$arg];
                } else {
                    $phpArgs[] = '$' . $scopedName;
                }
            }
            
            $phpCode = '<?php if (!function_exists(\'' . $macroName . '\')) { ';
            $phpCode .= 'function ' . $macroName . '(' . implode(', ', $phpArgs) . ') { ';
            $phpCode .= 'global $variable_context; $_index_stack = []; ?>';
            $phpCode .= $processedBody;
            $phpCode .= '<?php } } ?>';
            
            foreach ($args as $arg) {
                unset(self::$scopedVars[$arg]);
            }
            
            return $phpCode;
        }, $code);
    }

    static function isScopedVariable($varName) {
        return isset(self::$scopedVars[$varName]) || 
               strpos($varName, '_macro_') === 0 || 
               strpos($varName, '_loop_') === 0 ||
               strpos($varName, '_tloop_') === 0;
    }

    static function convertDotNotation($expr) {
        $pattern = '/\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\.([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*))?/';
        
        return preg_replace_callback($pattern, function($matches) {
            $varName = $matches[1];
            
            if ($varName === 'index') {
                if (isset($matches[2])) {
                    $depth = (int)$matches[2];
                    return '$_index_stack[count($_index_stack) - 1 - ' . $depth . ']';
                }
                return '$_index_stack[count($_index_stack) - 1]';
            }
            
            if (self::isScopedVariable($varName)) {
                if (isset($matches[2])) {
                    $keys = explode('.', $matches[2]);
                    $result = '$' . $varName;
                    foreach ($keys as $key) {
                        $result .= is_numeric($key) ? '[' . $key . ']' : '[\'' . $key . '\']';
                    }
                    return $result;
                }
                return '$' . $varName;
            }
            
            if (isset(self::$scopedVars[$varName])) {
                $scopedName = self::$scopedVars[$varName];
                if (isset($matches[2])) {
                    $keys = explode('.', $matches[2]);
                    $result = '$' . $scopedName;
                    foreach ($keys as $key) {
                        $result .= is_numeric($key) ? '[' . $key . ']' : '[\'' . $key . '\']';
                    }
                    return $result;
                }
                return '$' . $scopedName;
            }
            
            $result = '$variable_context[\'' . $varName . '\']';
            if (isset($matches[2])) {
                $keys = explode('.', $matches[2]);
                foreach ($keys as $key) {
                    $result .= is_numeric($key) ? '[' . $key . ']' : '[\'' . $key . '\']';
                }
            }
            return $result;
        }, $expr);
    }

    static function convertArrayNotation($expr) {
        $pattern = '/\$([a-zA-Z_][a-zA-Z0-9_]*)(\[(?:[\'"][^\'"]*[\'"]|\d+)\])+/';
        
        return preg_replace_callback($pattern, function($matches) {
            $varName = $matches[1];
            $fullMatch = $matches[0];
            
            // Skip variable_context (already converted), index, and scoped variables
            if ($varName === 'variable_context' || $varName === 'index' || 
                $varName === '_index_stack' ||
                self::isScopedVariable($varName) || isset(self::$scopedVars[$varName])) {
                return $fullMatch;
            }
            
            $accessPart = substr($fullMatch, strlen('$' . $varName));
            return '$variable_context[\'' . $varName . '\']' . $accessPart;
        }, $expr);
    }

    static function processExpression($expr) {
        $expr = self::convertDotNotation($expr);
        $expr = self::convertArrayNotation($expr);
        return $expr;
    }

    static function compileSet($code) {
        $pattern = '/\{%\s*set\s+\$([a-zA-Z_][a-zA-Z0-9_.]*)\s*=\s*(.+?)\s*%\}/is';
        
        return preg_replace_callback($pattern, function($matches) {
            $varPath = $matches[1];
            $value = trim($matches[2]);
            $value = self::processExpression($value);
            
            $keys = explode('.', $varPath);
            $result = '$variable_context';
            foreach ($keys as $key) {
                $result .= is_numeric($key) ? '[' . $key . ']' : '[\'' . $key . '\']';
            }
            
            return '<?php ' . $result . ' = ' . $value . '; ?>';
        }, $code);
    }

    static function compileForLoops($code) {
        $forPattern = '/\{%\s*for\s+(?:\$([a-zA-Z_][a-zA-Z0-9_]*)\s*,\s*)?\$([a-zA-Z_][a-zA-Z0-9_]*)\s+in\s+(.+?)\s*%\}/i';
        
        $loopId = 0;
        $loopStack = [];
        
        while (preg_match($forPattern, $code, $match)) {
            $keyVar = isset($match[1]) && $match[1] !== '' ? $match[1] : null;
            $itemVar = $match[2];
            $listExpr = $match[3];
            $currentLoopId = $loopId++;
            
            $processedList = self::processExpression($listExpr);
            $scopedItem = '_loop_' . $currentLoopId . '_' . $itemVar;
            $scopedKey = $keyVar ? '_loop_' . $currentLoopId . '_' . $keyVar : null;
            $indexVar = '_idx_' . $currentLoopId;
            
            $phpCode = '<?php $' . $indexVar . ' = 0; $_index_stack[] = 0; ';
            if ($scopedKey) {
                $phpCode .= 'foreach (' . $processedList . ' as $' . $scopedKey . ' => $' . $scopedItem . '): ';
            } else {
                $phpCode .= 'foreach (' . $processedList . ' as $' . $scopedItem . '): ';
            }
            $phpCode .= '?>';
            
            $loopStack[$currentLoopId] = [
                'item' => $itemVar, 'key' => $keyVar,
                'scopedItem' => $scopedItem, 'scopedKey' => $scopedKey,
                'indexVar' => $indexVar
            ];
            
            $code = preg_replace($forPattern, $phpCode . '<!--LOOP_START_' . $currentLoopId . '-->', $code, 1);
        }
        
        $endforPattern = '/\{%\s*endfor\s*%\}/i';
        
        for ($i = $loopId - 1; $i >= 0; $i--) {
            $loopInfo = $loopStack[$i] ?? null;
            if ($loopInfo) {
                $startMarker = '<!--LOOP_START_' . $i . '-->';
                $startPos = strpos($code, $startMarker);
                
                if ($startPos !== false) {
                    $searchStart = $startPos + strlen($startMarker);
                    $remaining = substr($code, $searchStart);
                    
                    if (preg_match($endforPattern, $remaining, $endMatch, PREG_OFFSET_CAPTURE)) {
                        $endPos = $searchStart + $endMatch[0][1];
                        $endLen = strlen($endMatch[0][0]);
                        
                        $endPhpCode = '<?php $' . $loopInfo['indexVar'] . '++; ';
                        $endPhpCode .= '$_index_stack[count($_index_stack) - 1] = $' . $loopInfo['indexVar'] . '; ';
                        $endPhpCode .= 'endforeach; array_pop($_index_stack); ?>';
                        
                        $code = substr_replace($code, $endPhpCode, $endPos, $endLen);
                    }
                    $code = str_replace($startMarker, '', $code);
                }
            }
        }
        
        foreach ($loopStack as $lid => $loopInfo) {
            $itemPattern = '/\$' . preg_quote($loopInfo['item'], '/') . '(?![a-zA-Z0-9_])/';
            $code = preg_replace($itemPattern, '$' . $loopInfo['scopedItem'], $code);
            
            if ($loopInfo['key']) {
                $keyPattern = '/\$' . preg_quote($loopInfo['key'], '/') . '(?![a-zA-Z0-9_])/';
                $code = preg_replace($keyPattern, '$' . $loopInfo['scopedKey'], $code);
            }
        }
        
        // Handle traditional foreach syntax: {% foreach($list as $key => $item): %}
        // Must also track these variables to prevent them being wrapped in variable_context
        $tradPattern = '/\{%\s*foreach\s*\(\s*(.+?)\s+as\s+(?:\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=>\s*)?\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)\s*:\s*%\}/is';
        
        $tradLoopId = 1000; // Start at high number to avoid collision with new-style loops
        $tradLoopStack = [];
        
        while (preg_match($tradPattern, $code, $match)) {
            $listExpr = $match[1];
            $keyVar = isset($match[2]) && $match[2] !== '' ? $match[2] : null;
            $itemVar = $match[3];
            $currentLoopId = $tradLoopId++;
            
            $processedList = self::processExpression($listExpr);
            $scopedItem = '_tloop_' . $currentLoopId . '_' . $itemVar;
            $scopedKey = $keyVar ? '_tloop_' . $currentLoopId . '_' . $keyVar : null;
            
            $tradLoopStack[$currentLoopId] = [
                'item' => $itemVar,
                'key' => $keyVar,
                'scopedItem' => $scopedItem,
                'scopedKey' => $scopedKey
            ];
            
            if ($scopedKey) {
                $phpCode = '<?php foreach (' . $processedList . ' as $' . $scopedKey . ' => $' . $scopedItem . '): ?><!--TLOOP_' . $currentLoopId . '-->';
            } else {
                $phpCode = '<?php foreach (' . $processedList . ' as $' . $scopedItem . '): ?><!--TLOOP_' . $currentLoopId . '-->';
            }
            
            $code = preg_replace($tradPattern, $phpCode, $code, 1);
        }
        
        // Replace endforeach and remove markers, replace variable references
        foreach ($tradLoopStack as $lid => $loopInfo) {
            $marker = '<!--TLOOP_' . $lid . '-->';
            $markerPos = strpos($code, $marker);
            
            if ($markerPos !== false) {
                // Find matching endforeach
                $afterMarker = substr($code, $markerPos + strlen($marker));
                if (preg_match('/\{%\s*endforeach\s*%\}/i', $afterMarker, $endMatch, PREG_OFFSET_CAPTURE)) {
                    $endPos = $markerPos + strlen($marker) + $endMatch[0][1];
                    $endLen = strlen($endMatch[0][0]);
                    
                    // Get the content between marker and endforeach
                    $loopContent = substr($code, $markerPos + strlen($marker), $endMatch[0][1]);
                    
                    // Replace variable references in loop content
                    $itemPattern = '/\$' . preg_quote($loopInfo['item'], '/') . '(?![a-zA-Z0-9_])/';
                    $loopContent = preg_replace($itemPattern, '$' . $loopInfo['scopedItem'], $loopContent);
                    
                    if ($loopInfo['key']) {
                        $keyPattern = '/\$' . preg_quote($loopInfo['key'], '/') . '(?![a-zA-Z0-9_])/';
                        $loopContent = preg_replace($keyPattern, '$' . $loopInfo['scopedKey'], $loopContent);
                    }
                    
                    // Rebuild the code
                    $before = substr($code, 0, $markerPos);
                    $after = substr($code, $endPos + $endLen);
                    $code = $before . $loopContent . '<?php endforeach; ?>' . $after;
                }
            }
        }
        
        // Clean up any remaining markers
        $code = preg_replace('/<!--TLOOP_\d+-->/', '', $code);
        
        return $code;
    }

    static function compileConditionals($code) {
        $code = preg_replace_callback('/\{%\s*if\s+(?!\()(.+?)\s*%\}/is', function($matches) {
            $condition = self::processExpression(trim($matches[1]));
            return '<?php if (' . $condition . '): ?>';
        }, $code);
        
        $code = preg_replace_callback('/\{%\s*if\s*\(\s*(.+?)\s*\)\s*:\s*%\}/is', function($matches) {
            $condition = self::processExpression($matches[1]);
            return '<?php if (' . $condition . '): ?>';
        }, $code);
        
        $code = preg_replace_callback('/\{%\s*elseif\s+(?!\()(.+?)\s*%\}/is', function($matches) {
            $condition = self::processExpression(trim($matches[1]));
            return '<?php elseif (' . $condition . '): ?>';
        }, $code);
        
        $code = preg_replace_callback('/\{%\s*elseif\s*\(\s*(.+?)\s*\)\s*:\s*%\}/is', function($matches) {
            $condition = self::processExpression($matches[1]);
            return '<?php elseif (' . $condition . '): ?>';
        }, $code);
        
        $code = preg_replace('/\{%\s*else\s*:?\s*%\}/i', '<?php else: ?>', $code);
        $code = preg_replace('/\{%\s*(?:endif|fi)\s*%\}/i', '<?php endif; ?>', $code);
        
        return $code;
    }

    static function compilePHP($code) {
        return preg_replace_callback('~\{%\s*(.+?)\s*\%}~is', function($matches) {
            $expr = self::processExpression($matches[1]);
            return '<?php ' . $expr . ' ?>';
        }, $code);
    }

    static function compileEchos($code) {
        return preg_replace_callback("~\{{\s*(.+?)\s*\}}~is", function ($matches0) {
            $parts = preg_split('/\s*\|\s*(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $matches0[1]);
            $mainContent = self::processExpression($parts[0]);
            $filterPart = array_slice($parts, 1);

            $filterFuncs = [];
            foreach($filterPart as $filter) {
                $filterFunc = preg_replace_callback(
                    "~\b(\w+)\s*(\(([^,()]*?(?:\s*,\s*[^,()]*?)*)\))?~is",
                    function ($matches) {
                        $functionName = $matches[1];
                        $args = (isset($matches[2]) && $matches[3] !== '') 
                            ? array_map('trim', explode(',', $matches[3])) 
                            : [];
                        return serialize(["filter"=> $functionName, "args" => $args]);
                    },
                    $filter);
                $filterFuncs[] = $filterFunc;
            }

            $tf = array_map('unserialize', $filterFuncs);
            $ttf = self::filterCallback($mainContent, $tf);
            return "<?php echo $ttf ?>";
        }, $code);
    }

    static function filterCallback(string $token, array $filters) {
        if(count($filters) > 0) {
            $ret = '';
            foreach($filters as $filt) {
                $ret = 'TemplateFilter::apply("' . $filt['filter'] . '", ' . $token;
                if(count($filt['args']) > 0) {
                    $ret .= ', [' . implode(", ", $filt['args']) . ']';
                }
                $ret .= ')';
                $token = $ret;
            }
            return $ret;
        }
        return $token;
    }

    static function compileEscapedEchos($code) {
        return preg_replace_callback('~\{{{\s*(.+?)\s*\}}}~is', function($matches) {
            $expr = self::processExpression($matches[1]);
            return '<?php echo htmlentities(' . $expr . ', ENT_QUOTES, \'UTF-8\') ?>';
        }, $code);
    }

    static function compileBlock($code) {
        preg_match_all('/\{%\s*block\s+(.*?)\s*%\}(.*?)\{%\s*endblock\s*%\}/is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], self::$blocks)) self::$blocks[$value[1]] = '';
            if (strpos($value[2], '@parent') === false) {
                self::$blocks[$value[1]] = self::emmitComment("begin block1 $value[1]", null, false);
                self::$blocks[$value[1]] .= $value[2];
                self::$blocks[$value[1]] .= self::emmitComment("end block: $value[1]", null, false);
            } else {
                self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], 
                    self::emmitComment("begin block2: $value[1]") . $value[2] . self::emmitComment("end block: $value[1]", false));
            }
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    static function compileYield($code) {
        foreach(self::$blocks as $block => $value) {
            $code = preg_replace('/\{%\s*yield\s+' . preg_quote($block, '/') . '\s*%\}/', $value, $code);
        }
        $code = preg_replace('/\{%\s*yield\s+(.*?)\s*%\}/i', '', $code);
        return $code;
    }
}

}
