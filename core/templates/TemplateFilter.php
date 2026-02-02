<?php
/**
 * Template Filter System - Merged Version
 * 
 * Provides filters for template variable transformation.
 * Usage: {{ $variable | filter }} or {{ $variable | filter('arg1', 'arg2') }}
 * 
 * Features:
 * - 50+ built-in filters
 * - Multibyte string support (mb_* functions)
 * - Extensible filter registration
 * - Type-safe implementations
 * 
 * @author Evangelos Rokas
 * @version 2.0 (Merged)
 * @date January 2026
 */

if (!class_exists("TemplateFilter")) {

class TemplateFilter {
    
    /** @var array Registered filter callbacks */
    private static $filters = [];
    
    /** @var bool Whether built-in filters have been registered */
    private static $initialized = false;
    
    /**
     * Initialize built-in filters
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        // String filters
        self::register('upper', [self::class, 'filterUpper']);
        self::register('lower', [self::class, 'filterLower']);
        self::register('capitalize', [self::class, 'filterCapitalize']);
        self::register('title', [self::class, 'filterTitle']);
        self::register('trim', [self::class, 'filterTrim']);
        self::register('ltrim', [self::class, 'filterLtrim']);
        self::register('rtrim', [self::class, 'filterRtrim']);
        self::register('strip', [self::class, 'filterStrip']);
        self::register('nl2br', [self::class, 'filterNl2br']);
        self::register('striptags', [self::class, 'filterStriptags']);
        self::register('escape', [self::class, 'filterEscape']);
        self::register('e', [self::class, 'filterEscape']); // alias
        self::register('raw', [self::class, 'filterRaw']);
        self::register('slug', [self::class, 'filterSlug']);
        self::register('truncate', [self::class, 'filterTruncate']);
        self::register('wordwrap', [self::class, 'filterWordwrap']);
        self::register('replace', [self::class, 'filterReplace']);
        self::register('split', [self::class, 'filterSplit']);
        self::register('join', [self::class, 'filterJoin']);
        self::register('reverse', [self::class, 'filterReverse']);
        self::register('repeat', [self::class, 'filterRepeat']);
        self::register('pad', [self::class, 'filterPad']);
        self::register('substr', [self::class, 'filterSubstr']);
        self::register('wrap', [self::class, 'filterWrap']);
        
        // Number filters
        self::register('abs', [self::class, 'filterAbs']);
        self::register('round', [self::class, 'filterRound']);
        self::register('floor', [self::class, 'filterFloor']);
        self::register('ceil', [self::class, 'filterCeil']);
        self::register('number_format', [self::class, 'filterNumberFormat']);
        self::register('currency', [self::class, 'filterCurrency']);
        self::register('percent', [self::class, 'filterPercent']);
        self::register('ordinal', [self::class, 'filterOrdinal']);
        
        // Date/Time filters
        self::register('date', [self::class, 'filterDate']);
        self::register('date_modify', [self::class, 'filterDateModify']);
        self::register('time_ago', [self::class, 'filterTimeAgo']);
        self::register('timestamp', [self::class, 'filterTimestamp']);
        
        // Array filters
        self::register('first', [self::class, 'filterFirst']);
        self::register('last', [self::class, 'filterLast']);
        self::register('length', [self::class, 'filterLength']);
        self::register('count', [self::class, 'filterLength']); // alias
        self::register('keys', [self::class, 'filterKeys']);
        self::register('values', [self::class, 'filterValues']);
        self::register('merge', [self::class, 'filterMerge']);
        self::register('slice', [self::class, 'filterSlice']);
        self::register('sort', [self::class, 'filterSort']);
        self::register('rsort', [self::class, 'filterRsort']);
        self::register('ksort', [self::class, 'filterKsort']);
        self::register('unique', [self::class, 'filterUnique']);
        self::register('column', [self::class, 'filterColumn']);
        self::register('filter', [self::class, 'filterFilter']);
        self::register('map', [self::class, 'filterMap']);
        self::register('batch', [self::class, 'filterBatch']);
        self::register('shuffle', [self::class, 'filterShuffle']);
        self::register('chunk', [self::class, 'filterChunk']);
        
        // JSON filters
        self::register('json', [self::class, 'filterJsonEncode']); // alias
        self::register('json_encode', [self::class, 'filterJsonEncode']);
        self::register('json_decode', [self::class, 'filterJsonDecode']);
        
        // URL filters
        self::register('url_encode', [self::class, 'filterUrlEncode']);
        self::register('url_decode', [self::class, 'filterUrlDecode']);
        self::register('base64_encode', [self::class, 'filterBase64Encode']);
        self::register('base64_decode', [self::class, 'filterBase64Decode']);
        
        // Type conversion
        self::register('int', [self::class, 'filterInt']);
        self::register('float', [self::class, 'filterFloat']);
        self::register('string', [self::class, 'filterString']);
        self::register('bool', [self::class, 'filterBool']);
        self::register('array', [self::class, 'filterArray']);
        
        // Utility filters
        self::register('default', [self::class, 'filterDefault']);
        self::register('asset', [self::class, 'filterAsset']);
        self::register('cache_asset', [self::class, 'filterCacheAsset']);
        self::register('t', [self::class, 'filterTranslate']);
        self::register('translate', [self::class, 'filterTranslate']);
        self::register('dump', [self::class, 'filterDump']);
        self::register('debug', [self::class, 'filterDump']); // alias
        
        self::$initialized = true;
    }
    
    /**
     * Register a custom filter
     * 
     * @param string $name Filter name
     * @param callable $callback Callback function
     * @throws Exception if callback is not callable
     */
    public static function register($name, $callback) {
        if (!is_callable($callback)) {
            throw new Exception("Filter callback must be callable: $name");
        }
        self::$filters[$name] = $callback;
    }
    
    public static function unregister(string $name): bool {
        if (isset(self::$filters[$name])) {
            unset(self::$filters[$name]);
            return true;
        }
        return false;
    }
    
    /**
     * Check if filter exists
     * 
     * @param string $name Filter name
     * @return bool
     */
    public static function exists(string $name): bool {
        self::init();
        return isset(self::$filters[$name]);
    }
    
    /**
     * Get all registered filters
     * 
     * @return array Filter names
     */
    public static function getAll(): array {
        self::init();
        return array_keys(self::$filters);
    }
    
    public static function getCallback(string $name): ?callable {
        self::init();
        return self::$filters[$name] ?? null;
    }
    
    public static function apply(string $name, $value, array $args = []) {
        self::init();
        if (!isset(self::$filters[$name])) {
            return $value; // Graceful degradation
        }
        return call_user_func(self::$filters[$name], $value, $args);
    }
    
    // =========================================================================
    // STRING FILTERS (with mb_ multibyte support)
    // =========================================================================
    
    public static function filterUpper($value, array $args = []) {
        return mb_strtoupper((string)$value);
    }
    
    public static function filterLower($value, array $args = []) {
        return mb_strtolower((string)$value);
    }
    
    public static function filterCapitalize($value, array $args = []) {
        $str = (string)$value;
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_strtolower(mb_substr($str, 1));
    }
    
    public static function filterTitle($value, array $args = []) {
        return mb_convert_case((string)$value, MB_CASE_TITLE);
    }
    
    public static function filterTrim($value, array $args = []) {
        $chars = $args[0] ?? " \t\n\r\0\x0B";
        return trim((string)$value, $chars);
    }
    
    public static function filterLtrim($value, array $args = []) {
        $chars = $args[0] ?? " \t\n\r\0\x0B";
        return ltrim((string)$value, $chars);
    }
    
    public static function filterRtrim($value, array $args = []) {
        $chars = $args[0] ?? " \t\n\r\0\x0B";
        return rtrim((string)$value, $chars);
    }
    
    public static function filterStrip($value, array $args = []) {
        return preg_replace('/\s+/', '', (string)$value);
    }
    
    public static function filterNl2br($value, array $args = []) {
        return nl2br((string)$value);
    }
    
    public static function filterStriptags($value, array $args = []) {
        $allowed = $args[0] ?? '';
        return strip_tags((string)$value, $allowed);
    }
    
    public static function filterEscape($value, array $args = []) {
        $strategy = $args[0] ?? 'html';
        $value = (string)$value;
        
        switch ($strategy) {
            case 'html':
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'js':
            case 'javascript':
                return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            case 'url':
                return rawurlencode($value);
            case 'css':
                return addcslashes($value, "\x00..\x1f\\\"'");
            default:
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    public static function filterRaw($value, array $args = []) {
        return $value;
    }
    
    public static function filterSlug($value, array $args = []) {
        $separator = $args[0] ?? '-';
        $value = mb_strtolower((string)$value);
        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value);
        return trim($value, $separator);
    }
    
    public static function filterTruncate($value, array $args = []) {
        $length = (int)($args[0] ?? 100);
        $suffix = $args[1] ?? '...';
        $preserveWords = $args[2] ?? false;
        $value = (string)$value;
        
        if (mb_strlen($value) <= $length) return $value;
        
        if ($preserveWords) {
            $truncated = mb_substr($value, 0, $length);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false) $truncated = mb_substr($truncated, 0, $lastSpace);
            return $truncated . $suffix;
        }
        
        return mb_substr($value, 0, $length) . $suffix;
    }
    
    public static function filterWordwrap($value, array $args = []) {
        $width = (int)($args[0] ?? 75);
        $break = $args[1] ?? "\n";
        $cut = (bool)($args[2] ?? false);
        return wordwrap((string)$value, $width, $break, $cut);
    }
    
    public static function filterReplace($value, array $args = []) {
        $search = $args[0] ?? '';
        $replace = $args[1] ?? '';
        return str_replace($search, $replace, (string)$value);
    }
    
    public static function filterSplit($value, array $args = []) {
        $delimiter = $args[0] ?? '';
        $limit = (int)($args[1] ?? PHP_INT_MAX);
        if ($delimiter === '') return mb_str_split((string)$value);
        return explode($delimiter, (string)$value, $limit);
    }
    
    public static function filterJoin($value, array $args = []) {
        $glue = $args[0] ?? '';
        if (!is_array($value)) return $value;
        return implode($glue, $value);
    }
    
    public static function filterReverse($value, array $args = []) {
        if (is_array($value)) return array_reverse($value);
        // Multibyte-safe string reverse
        $str = (string)$value;
        $result = '';
        for ($i = mb_strlen($str) - 1; $i >= 0; $i--) {
            $result .= mb_substr($str, $i, 1);
        }
        return $result;
    }
    
    public static function filterRepeat($value, array $args = []) {
        return str_repeat((string)$value, max(0, (int)($args[0] ?? 1)));
    }
    
    public static function filterPad($value, array $args = []) {
        $length = (int)($args[0] ?? 0);
        $padStr = $args[1] ?? ' ';
        $type = $args[2] ?? 'right';
        $padType = $type === 'left' ? STR_PAD_LEFT : ($type === 'both' ? STR_PAD_BOTH : STR_PAD_RIGHT);
        return str_pad((string)$value, $length, $padStr, $padType);
    }
    
    public static function filterSubstr($value, array $args = []) {
        $start = (int)($args[0] ?? 0);
        $length = isset($args[1]) ? (int)$args[1] : null;
        return mb_substr((string)$value, $start, $length);
    }
    
    public static function filterWrap($value, array $args = []) {
        $before = $args[0] ?? '';
        $after = $args[1] ?? $before;
        return $before . $value . $after;
    }
    
    // =========================================================================
    // NUMBER FILTERS
    // =========================================================================
    
    public static function filterAbs($value, array $args = []) { return abs($value); }
    
    public static function filterRound($value, array $args = []) {
        $precision = (int)($args[0] ?? 0);
        return round($value, $precision);
    }
    
    public static function filterFloor($value, array $args = []) { return floor($value); }
    public static function filterCeil($value, array $args = []) { return ceil($value); }
    
    public static function filterNumberFormat($value, array $args = []) {
        $decimals = (int)($args[0] ?? 0);
        $decPoint = $args[1] ?? '.';
        $thousandsSep = $args[2] ?? ',';
        return number_format((float)$value, $decimals, $decPoint, $thousandsSep);
    }
    
    public static function filterCurrency($value, array $args = []) {
        $symbol = $args[0] ?? '$';
        $decimals = (int)($args[1] ?? 2);
        $symbolAfter = (bool)($args[2] ?? false);
        $formatted = number_format((float)$value, $decimals);
        return $symbolAfter ? $formatted . $symbol : $symbol . $formatted;
    }
    
    public static function filterPercent($value, array $args = []) {
        $decimals = (int)($args[0] ?? 0);
        return number_format((float)$value * 100, $decimals) . '%';
    }
    
    public static function filterOrdinal($value, array $args = []) {
        $n = (int)$value;
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
    
    // =========================================================================
    // DATE/TIME FILTERS
    // =========================================================================
    
    public static function filterDate($value, array $args = []) {
        $format = $args[0] ?? 'Y-m-d H:i:s';
        if ($value instanceof \DateTimeInterface) return $value->format($format);
        if (is_numeric($value)) return date($format, (int)$value);
        $timestamp = strtotime((string)$value);
        return $timestamp === false ? $value : date($format, $timestamp);
    }
    
    public static function filterDateModify($value, array $args = []) {
        $modifier = $args[0] ?? '';
        $format = $args[1] ?? 'Y-m-d H:i:s';
        try {
            $dt = $value instanceof \DateTimeInterface 
                ? \DateTime::createFromInterface($value)
                : (is_numeric($value) ? new \DateTime('@' . (int)$value) : new \DateTime((string)$value));
            $dt->modify($modifier);
            return $dt->format($format);
        } catch (\Exception $e) {
            return $value;
        }
    }
    
    public static function filterTimeAgo($value, array $args = []) {
        $timestamp = $value instanceof \DateTimeInterface ? $value->getTimestamp() : (is_numeric($value) ? (int)$value : strtotime((string)$value));
        if ($timestamp === false) return $value;
        
        $diff = time() - $timestamp;
        $future = $diff < 0;
        $diff = abs($diff);
        
        $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute', 1 => 'second'];
        foreach ($units as $seconds => $unit) {
            if ($diff >= $seconds) {
                $count = floor($diff / $seconds);
                $timeStr = $count . ' ' . $unit . ($count > 1 ? 's' : '');
                return $future ? 'in ' . $timeStr : $timeStr . ' ago';
            }
        }
        return 'just now';
    }
    
    public static function filterTimestamp($value, array $args = []) {
        if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
        if (is_numeric($value)) return (int)$value;
        return strtotime((string)$value) ?: null;
    }
    
    // =========================================================================
    // ARRAY FILTERS
    // =========================================================================
    
    public static function filterFirst($value, array $args = []) {
        if (is_array($value)) return reset($value) ?: null;
        if (is_string($value)) return mb_substr($value, 0, 1);
        return $value;
    }
    
    public static function filterLast($value, array $args = []) {
        if (is_array($value)) return end($value) ?: null;
        if (is_string($value)) return mb_substr($value, -1);
        return $value;
    }
    
    public static function filterLength($value, array $args = []) {
        if (is_array($value) || $value instanceof \Countable) return count($value);
        if (is_string($value)) return mb_strlen($value);
        return 0;
    }
    
    public static function filterKeys($value, array $args = []) {
        return is_array($value) ? array_keys($value) : [];
    }
    
    public static function filterValues($value, array $args = []) {
        return is_array($value) ? array_values($value) : [];
    }
    
    public static function filterMerge($value, array $args = []) {
        if (!is_array($value)) return $value;
        $arrays = [$value];
        foreach ($args as $arg) if (is_array($arg)) $arrays[] = $arg;
        return array_merge(...$arrays);
    }
    
    public static function filterSlice($value, array $args = []) {
        $start = (int)($args[0] ?? 0);
        $length = isset($args[1]) ? (int)$args[1] : null;
        if (is_array($value)) return array_slice($value, $start, $length);
        if (is_string($value)) return mb_substr($value, $start, $length);
        return $value;
    }
    
    public static function filterSort($value, array $args = []) {
        if (!is_array($value)) return $value;
        $copy = $value;
        $key = $args[0] ?? null;
        if ($key !== null) {
            usort($copy, function($a, $b) use ($key) {
                $va = is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null);
                $vb = is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null);
                return $va <=> $vb;
            });
        } else {
            sort($copy);
        }
        return $copy;
    }
    
    public static function filterRsort($value, array $args = []) {
        if (!is_array($value)) return $value;
        $copy = $value; rsort($copy); return $copy;
    }
    
    public static function filterKsort($value, array $args = []) {
        if (!is_array($value)) return $value;
        $copy = $value; ksort($copy); return $copy;
    }
    
    public static function filterUnique($value, array $args = []) {
        return is_array($value) ? array_unique($value) : $value;
    }
    
    public static function filterColumn($value, array $args = []) {
        if (!is_array($value)) return $value;
        return array_column($value, $args[0] ?? null, $args[1] ?? null);
    }
    
    public static function filterFilter($value, array $args = []) {
        return is_array($value) ? array_filter($value) : $value;
    }
    
    public static function filterMap($value, array $args = []) {
        $key = $args[0] ?? null;
        if (!is_array($value) || $key === null) return $value;
        return array_map(fn($item) => is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null), $value);
    }
    
    public static function filterBatch($value, array $args = []) {
        if (!is_array($value)) return $value;
        $size = max(1, (int)($args[0] ?? 1));
        $fill = $args[1] ?? null;
        $result = array_chunk($value, $size);
        if ($fill !== null && !empty($result)) {
            $lastIdx = count($result) - 1;
            $remaining = $size - count($result[$lastIdx]);
            if ($remaining > 0) {
                $result[$lastIdx] = array_merge($result[$lastIdx], array_fill(0, $remaining, $fill));
            }
        }
        return $result;
    }
    
    public static function filterShuffle($value, array $args = []) {
        if (!is_array($value)) return $value;
        $copy = $value; shuffle($copy); return $copy;
    }
    
    public static function filterChunk($value, array $args = []) {
        if (!is_array($value)) return $value;
        return array_chunk($value, max(1, (int)($args[0] ?? 1)), (bool)($args[1] ?? false));
    }
    
    // =========================================================================
    // JSON/URL/TYPE FILTERS
    // =========================================================================
    
    public static function filterJsonEncode($value, array $args = []) {
        $pretty = isset($args[0]) && ($args[0] === 'pretty' || $args[0] === true);
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) $options |= JSON_PRETTY_PRINT;
        return json_encode($value, $options);
    }
    
    public static function filterJsonDecode($value, array $args = []) {
        return json_decode((string)$value, (bool)($args[0] ?? true));
    }
    
    public static function filterUrlEncode($value, array $args = []) { return rawurlencode((string)$value); }
    public static function filterUrlDecode($value, array $args = []) { return rawurldecode((string)$value); }
    public static function filterBase64Encode($value, array $args = []) { return base64_encode((string)$value); }
    public static function filterBase64Decode($value, array $args = []) { return base64_decode((string)$value); }
    
    public static function filterInt($value, array $args = []) { return (int)$value; }
    public static function filterFloat($value, array $args = []) { return (float)$value; }
    public static function filterString($value, array $args = []) { return (string)$value; }
    public static function filterBool($value, array $args = []) { return (bool)$value; }
    public static function filterArray($value, array $args = []) { return (array)$value; }
    
    // =========================================================================
    // UTILITY FILTERS
    // =========================================================================
    
    public static function filterDefault($value, array $args = []) {
        $default = $args[0] ?? '';
        if ($value === null || $value === '' || $value === false || $value === []) return $default;
        return $value;
    }
    
    public static function filterAsset($value, array $args = []) {
        $basePath = $args[0] ?? '/assets/';
        return rtrim($basePath, '/') . '/' . ltrim((string)$value, '/');
    }
    
    public static function filterCacheAsset($value, array $args = []) {
        $basePath = $args[0] ?? '/assets/';
        $asset = rtrim($basePath, '/') . '/' . ltrim((string)$value, '/');
        if (isset($_SERVER['DOCUMENT_ROOT'])) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $asset;
            if (file_exists($filePath)) return $asset . '?v=' . filemtime($filePath);
        }
        return $asset;
    }
    
    public static function filterTranslate($value, array $args = []) {
        if (function_exists('__')) return __((string)$value);
        if (function_exists('t')) return t((string)$value);
        return $value;
    }
    
    public static function filterDump($value, array $args = []) {
        ob_start(); var_dump($value);
        return '<pre>' . htmlspecialchars(ob_get_clean()) . '</pre>';
    }
}

} // end class_exists check
