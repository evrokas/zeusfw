<?php

// request class

class RequestClass {
    private $method;
    private $query;
    private $tokens;

    function __construct($asrvr) {
        $this->query = $asrvr['QUERY_STRING'];
        $this->tokens = explode('/', '/'.$this->query);
        $this->tokens[0] = $this->query;

        $this->method = strtolower( $asrvr['REQUEST_METHOD'] );

        // print_r( $this );
    }

    function getQueryString() {
        return $this->query;
    }

    function getMethod() {
        return $this->method;
    }

    function getQueryRoute() {
        return ($this->tokens);
    }

    function matchMethod($amethod) {
        return (strtolower( $amethod ) == strtolower( $this->method) );   
    }
}