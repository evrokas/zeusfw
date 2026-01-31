<?php
/**
 * TemplateFilter - Standalone filter management class for Zeus Template System
 * 
 * Manages template filters with registration, execution, and built-in filters.
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
        
        // Number filters
        self::register('abs', [self::class, 'filterAbs']);
        self::register('round', [self::class, 'filterRound']);
        self::register('floor', [self::class, 'filterFloor']);
        self::register('ceil', [self::class, 'filterCeil']);
        self::register('number_format', [self::class, 'filterNumberFormat']);
        self::register('currency', [self::class, 'filterCurrency']);
        self::register('percent', [self::class, 'filterPercent']);
        
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
        self::register('unique', [self::class, 'filterUnique']);
        self::register('column', [self::class, 'filterColumn']);
        self::register('filter', [self::class, 'filterFilter']);
        self::register('map', [self::class, 'filterMap']);
        self::register('batch', [self::class, 'filterBatch']);
        
        // JSON filters
        self::register('json_encode', [self::class, 'filterJsonEncode']);
        self::register('json_decode', [self::class, 'filterJsonDecode']);
        
        // URL filters
        self::register('url_encode', [self::class, 'filterUrlEncode']);
        self::register('url_decode', [self::class, 'filterUrlDecode']);
        
        // Asset/Path filters
        self::register('asset', [self::class, 'filterAsset']);
        self::register('cache_asset', [self::class, 'filterCacheAsset']);
        
        // Translation filter
        self::register('t', [self::class, 'filterTranslate']);
        self::register('trans', [self::class, 'filterTranslate']); // alias
        
        // Default filter
        self::register('default', [self::class, 'filterDefault']);
        self::register('d', [self::class, 'filterDefault']); // alias
        
        // Type conversion
        self::register('int', [self::class, 'filterInt']);
        self::register('float', [self::class, 'filterFloat']);
        self::register('string', [self::class, 'filterString']);
        self::register('bool', [self::class, 'filterBool']);
        self::register('array', [self::class, 'filterArray']);
        
        self::$initialized = true;
    }
    
    /**
     * Register a filter
     */
    public static function register(string $name, callable $callback): void {
        self::$filters[$name] = $callback;
    }
    
    /**
     * Unregister a filter
     */
    public static function unregister(string $name): bool {
        if (isset(self::$filters[$name])) {
            unset(self::$filters[$name]);
            return true;
        }
        return false;
    }
    
    /**
     * Check if filter exists
     */
    public static function exists(string $name): bool {
        return isset(self::$filters[$name]);
    }
    
    /**
     * Get all registered filter names
     */
    public static function getRegistered(): array {
        return array_keys(self::$filters);
    }
    
    /**
     * Apply a filter
     */
    public static function apply(string $name, $value, array $args = []) {
        if (!isset(self::$filters[$name])) {
            throw new \InvalidArgumentException("Unknown filter: {$name}");
        }
        
        return call_user_func(self::$filters[$name], $value, $args);
    }
    
    /**
     * Get filter callback for use in compiled templates
     */
    public static function getCallback(string $name): ?callable {
        return self::$filters[$name] ?? null;
    }
    
    // =========================================================================
    // STRING FILTERS
    // =========================================================================
    
    public static function filterUpper($value, array $args = []) {
        return mb_strtoupper((string)$value);
    }
    
    public static function filterLower($value, array $args = []) {
        return mb_strtolower((string)$value);
    }
    
    public static function filterCapitalize($value, array $args = []) {
        return ucfirst(mb_strtolower((string)$value));
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
        return preg_replace('/\s+/', ' ', trim((string)$value));
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
        
        switch ($strategy) {
            case 'html':
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'js':
            case 'javascript':
                return json_encode((string)$value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            case 'css':
                return addcslashes((string)$value, "\x00..\x1f\\\"'");
            case 'url':
                return rawurlencode((string)$value);
            case 'attr':
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            default:
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    public static function filterRaw($value, array $args = []) {
        return $value; // No escaping
    }
    
    public static function filterSlug($value, array $args = []) {
        $separator = $args[0] ?? '-';
        $slug = mb_strtolower((string)$value);
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $slug);
        $slug = trim($slug, $separator);
        return $slug;
    }
    
    public static function filterTruncate($value, array $args = []) {
        $length = (int)($args[0] ?? 80);
        $suffix = $args[1] ?? '...';
        $preserveWords = (bool)($args[2] ?? true);
        
        $str = (string)$value;
        
        if (mb_strlen($str) <= $length) {
            return $str;
        }
        
        if ($preserveWords) {
            $truncated = mb_substr($str, 0, $length);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
            return rtrim($truncated) . $suffix;
        }
        
        return mb_substr($str, 0, $length) . $suffix;
    }
    
    public static function filterWordwrap($value, array $args = []) {
        $width = (int)($args[0] ?? 75);
        $break = $args[1] ?? "\n";
        $cut = (bool)($args[2] ?? false);
        
        return wordwrap((string)$value, $width, $break, $cut);
    }
    
    public static function filterReplace($value, array $args = []) {
        if (count($args) < 2) {
            return $value;
        }
        return str_replace($args[0], $args[1], (string)$value);
    }
    
    public static function filterSplit($value, array $args = []) {
        $delimiter = $args[0] ?? '';
        $limit = isset($args[1]) ? (int)$args[1] : PHP_INT_MAX;
        
        if ($delimiter === '') {
            return mb_str_split((string)$value);
        }
        
        return explode($delimiter, (string)$value, $limit);
    }
    
    public static function filterJoin($value, array $args = []) {
        $glue = $args[0] ?? '';
        
        if (!is_array($value)) {
            return $value;
        }
        
        return implode($glue, $value);
    }
    
    public static function filterReverse($value, array $args = []) {
        if (is_array($value)) {
            return array_reverse($value);
        }
        return strrev((string)$value);
    }
    
    public static function filterRepeat($value, array $args = []) {
        $times = (int)($args[0] ?? 1);
        return str_repeat((string)$value, max(0, $times));
    }
    
    public static function filterPad($value, array $args = []) {
        $length = (int)($args[0] ?? 0);
        $padStr = $args[1] ?? ' ';
        $type = $args[2] ?? 'right';
        
        $padType = STR_PAD_RIGHT;
        if ($type === 'left') $padType = STR_PAD_LEFT;
        elseif ($type === 'both') $padType = STR_PAD_BOTH;
        
        return str_pad((string)$value, $length, $padStr, $padType);
    }
    
    // =========================================================================
    // NUMBER FILTERS
    // =========================================================================
    
    public static function filterAbs($value, array $args = []) {
        return abs($value);
    }
    
    public static function filterRound($value, array $args = []) {
        $precision = (int)($args[0] ?? 0);
        $mode = $args[1] ?? 'common';
        
        $roundMode = PHP_ROUND_HALF_UP;
        if ($mode === 'floor') $roundMode = PHP_ROUND_HALF_DOWN;
        elseif ($mode === 'ceil') $roundMode = PHP_ROUND_HALF_UP;
        
        return round($value, $precision, $roundMode);
    }
    
    public static function filterFloor($value, array $args = []) {
        return floor($value);
    }
    
    public static function filterCeil($value, array $args = []) {
        return ceil($value);
    }
    
    public static function filterNumberFormat($value, array $args = []) {
        $decimals = (int)($args[0] ?? 0);
        $decPoint = $args[1] ?? '.';
        $thousandsSep = $args[2] ?? ',';
        
        return number_format((float)$value, $decimals, $decPoint, $thousandsSep);
    }
    
    public static function filterCurrency($value, array $args = []) {
        $symbol = $args[0] ?? '$';
        $decimals = (int)($args[1] ?? 2);
        $position = $args[2] ?? 'before';
        
        $formatted = number_format((float)$value, $decimals);
        
        if ($position === 'after') {
            return $formatted . $symbol;
        }
        return $symbol . $formatted;
    }
    
    public static function filterPercent($value, array $args = []) {
        $decimals = (int)($args[0] ?? 0);
        return number_format((float)$value * 100, $decimals) . '%';
    }
    
    // =========================================================================
    // DATE/TIME FILTERS
    // =========================================================================
    
    public static function filterDate($value, array $args = []) {
        $format = $args[0] ?? 'Y-m-d H:i:s';
        
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }
        
        try {
            $dt = new \DateTime($value);
            return $dt->format($format);
        } catch (\Exception $e) {
            return $value;
        }
    }
    
    public static function filterDateModify($value, array $args = []) {
        $modifier = $args[0] ?? '+1 day';
        $format = $args[1] ?? 'Y-m-d H:i:s';
        
        try {
            $dt = $value instanceof \DateTimeInterface 
                ? \DateTime::createFromInterface($value)
                : new \DateTime($value);
            $dt->modify($modifier);
            return $dt->format($format);
        } catch (\Exception $e) {
            return $value;
        }
    }
    
    public static function filterTimeAgo($value, array $args = []) {
        try {
            $dt = $value instanceof \DateTimeInterface 
                ? $value 
                : new \DateTime($value);
            
            $now = new \DateTime();
            $diff = $now->diff($dt);
            
            if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
            if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
            if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
            if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            return 'just now';
        } catch (\Exception $e) {
            return $value;
        }
    }
    
    public static function filterTimestamp($value, array $args = []) {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        
        try {
            $dt = new \DateTime($value);
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return (int)$value;
        }
    }
    
    // =========================================================================
    // ARRAY FILTERS
    // =========================================================================
    
    public static function filterFirst($value, array $args = []) {
        if (is_array($value)) {
            return reset($value);
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 1);
        }
        return $value;
    }
    
    public static function filterLast($value, array $args = []) {
        if (is_array($value)) {
            return end($value);
        }
        if (is_string($value)) {
            return mb_substr($value, -1);
        }
        return $value;
    }
    
    public static function filterLength($value, array $args = []) {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        if (is_string($value)) {
            return mb_strlen($value);
        }
        return 0;
    }
    
    public static function filterKeys($value, array $args = []) {
        if (!is_array($value)) {
            return [];
        }
        return array_keys($value);
    }
    
    public static function filterValues($value, array $args = []) {
        if (!is_array($value)) {
            return [];
        }
        return array_values($value);
    }
    
    public static function filterMerge($value, array $args = []) {
        if (!is_array($value)) {
            return $value;
        }
        
        $result = $value;
        foreach ($args as $arr) {
            if (is_array($arr)) {
                $result = array_merge($result, $arr);
            }
        }
        return $result;
    }
    
    public static function filterSlice($value, array $args = []) {
        $start = (int)($args[0] ?? 0);
        $length = isset($args[1]) ? (int)$args[1] : null;
        
        if (is_array($value)) {
            return array_slice($value, $start, $length);
        }
        if (is_string($value)) {
            return mb_substr($value, $start, $length);
        }
        return $value;
    }
    
    public static function filterSort($value, array $args = []) {
        if (!is_array($value)) {
            return $value;
        }
        
        $key = $args[0] ?? null;
        
        if ($key !== null) {
            usort($value, function($a, $b) use ($key) {
                $va = is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null);
                $vb = is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null);
                return $va <=> $vb;
            });
        } else {
            sort($value);
        }
        
        return $value;
    }
    
    public static function filterRsort($value, array $args = []) {
        $sorted = self::filterSort($value, $args);
        return is_array($sorted) ? array_reverse($sorted) : $sorted;
    }
    
    public static function filterUnique($value, array $args = []) {
        if (!is_array($value)) {
            return $value;
        }
        return array_unique($value);
    }
    
    public static function filterColumn($value, array $args = []) {
        if (!is_array($value) || empty($args)) {
            return $value;
        }
        $key = $args[0];
        $indexKey = $args[1] ?? null;
        
        return array_column($value, $key, $indexKey);
    }
    
    public static function filterFilter($value, array $args = []) {
        if (!is_array($value)) {
            return $value;
        }
        
        return array_filter($value, function($item) {
            return !empty($item);
        });
    }
    
    public static function filterMap($value, array $args = []) {
        if (!is_array($value) || empty($args)) {
            return $value;
        }
        
        $key = $args[0];
        
        return array_map(function($item) use ($key) {
            if (is_array($item)) {
                return $item[$key] ?? null;
            }
            if (is_object($item)) {
                return $item->$key ?? null;
            }
            return null;
        }, $value);
    }
    
    public static function filterBatch($value, array $args = []) {
        if (!is_array($value)) {
            return $value;
        }
        
        $size = max(1, (int)($args[0] ?? 1));
        $fill = $args[1] ?? null;
        
        $result = array_chunk($value, $size);
        
        if ($fill !== null && !empty($result)) {
            $lastBatch = &$result[count($result) - 1];
            while (count($lastBatch) < $size) {
                $lastBatch[] = $fill;
            }
        }
        
        return $result;
    }
    
    // =========================================================================
    // JSON FILTERS
    // =========================================================================
    
    public static function filterJsonEncode($value, array $args = []) {
        $pretty = (bool)($args[0] ?? false);
        $flags = JSON_UNESCAPED_UNICODE;
        
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($value, $flags);
    }
    
    public static function filterJsonDecode($value, array $args = []) {
        $assoc = (bool)($args[0] ?? true);
        return json_decode((string)$value, $assoc);
    }
    
    // =========================================================================
    // URL FILTERS
    // =========================================================================
    
    public static function filterUrlEncode($value, array $args = []) {
        return urlencode((string)$value);
    }
    
    public static function filterUrlDecode($value, array $args = []) {
        return urldecode((string)$value);
    }
    
    // =========================================================================
    // ASSET/PATH FILTERS
    // =========================================================================
    
    public static function filterAsset($value, array $args = []) {
        if (function_exists('asset')) {
            return asset($value);
        }
        return '/assets/' . ltrim((string)$value, '/');
    }
    
    public static function filterCacheAsset($value, array $args = []) {
        $token = trim((string)$value, '/ ');
        if (function_exists('rel_url')) {
            return rel_url('/cache/' . $token);
        }
        return '/cache/' . $token;
    }
    
    // =========================================================================
    // TRANSLATION FILTER
    // =========================================================================
    
    public static function filterTranslate($value, array $args = []) {
        if (function_exists('t')) {
            return t($value, ...$args);
        }
        if (function_exists('__')) {
            return __($value, ...$args);
        }
        return $value;
    }
    
    // =========================================================================
    // DEFAULT FILTER
    // =========================================================================
    
    public static function filterDefault($value, array $args = []) {
        $default = $args[0] ?? '';
        
        if ($value === null || $value === '' || $value === false || 
            (is_array($value) && empty($value))) {
            return $default;
        }
        
        return $value;
    }
    
    // =========================================================================
    // TYPE CONVERSION FILTERS
    // =========================================================================
    
    public static function filterInt($value, array $args = []) {
        return (int)$value;
    }
    
    public static function filterFloat($value, array $args = []) {
        return (float)$value;
    }
    
    public static function filterString($value, array $args = []) {
        return (string)$value;
    }
    
    public static function filterBool($value, array $args = []) {
        return (bool)$value;
    }
    
    public static function filterArray($value, array $args = []) {
        return (array)$value;
    }
}

} // end class_exists check
