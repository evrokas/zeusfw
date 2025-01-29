<?php

class Log {
    private static $logstring = null;

    static function log($str) {
        self::$logstring .= "<pre>$str</pre>";
    }

    static function get($flush = false): string {
        $s = self::$logstring;

        if($flush)self::$logstring = null;

        if($s === null)$s = "";
        return $s;
    }

    static function flush() {
        self::$logstring = null;
    }
}
