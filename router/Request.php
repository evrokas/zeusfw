<?php

// request class

class RequestClass {
    static private $method;
    static private $query;
    static private $tokens;

    function __construct($asrvr) {
        self::$query = $asrvr['QUERY_STRING'];
        self::$tokens = explode('/', '/'.self::$query);
        self::$tokens[0] = self::$query;

        self::$method = $asrvr['REQUEST_METHOD'];
    }

    function getQueryString() {
        return self::$query;
    }

    function getMethod() {
        return self::$method;
    }

    function getQueryRoute() {
        return (self::$tokens);
    }
}