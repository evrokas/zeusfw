<?php

// request class

class RequestClass {
    private $method;
    private $query;
    private $tokens;

    function __construct($asrvr) {
        // web/.htaccess's RewriteRule ^(.*)$ index.php?$1 [QSA,L,PT] puts
        // the requested *path* into QUERY_STRING itself ($1) -- that's
        // the whole mechanism Router::matchRoute() relies on. But `QSA`
        // (query string append) means a request that also carries real
        // query params (e.g. `?device_id=X&key=Y`) arrives here as
        // QUERY_STRING = "api/overland&device_id=X&key=Y", not just
        // "api/overland" -- confirmed against a real Apache-shaped
        // rewrite, not assumed. Every existing route in this framework's
        // history never depended on a query param to match (dynamic
        // segments are always path tokens, `/patient/{id}/edit`, never
        // `?id=`), so this was never triggered before; a route that
        // *does* need one (core/router/Router.php's own url matching
        // splits purely on '/') silently 404s the instant a caller adds
        // so much as one `&key=...`. Real query params never legitimately
        // appear in $1 itself (that's a path from the original request
        // line, before any `?`), so anything from the first '&' onward
        // here is always the browser's own appended query string, never
        // part of the route -- safe to drop for routing purposes; it's
        // already available correctly to application code via PHP's own
        // native $_GET (parsed independently, upstream of this class).
        // (string) cast: strtok() returns false, not '', for an empty
        // input (the homepage's own QUERY_STRING once $1 is empty) --
        // every existing caller of getQueryString() expects a string.
        $this->query = (string)strtok($asrvr['QUERY_STRING'], '&');
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