<?php
/**
 * Zeus Template System (ZETEM) - 
 *
 * Provides a global context for variables
 * 
 * @author Evangelos Rokas
 * @version 1.0
 * @date February 2026
 */


if (!class_exists("TemplateGlobalContext")) {

class TemplateGlobalContext {
    static $globalContext = [];

    function __construct() {
        self::reset();
    }   

    public static function reset() {
        self::$globalContext = [];
    }

    public static function globalAssign($args) {
        self::$globalContext = $args;
    }
    
    public static function globalAdd($arg) {
        if(is_array($arg)) {
            array_push(self::$globalContext, $arg);
        } else {
            self::$globalContext[] = $arg;
        }
    }

    public static function getGlobals() {
        return self::$globalContext;
    }
}
}

function _dump_vars() {
    echopre('$variable_context[] = ' . print_r(TemplateGlobalContext::getGlobals(), 1));
}