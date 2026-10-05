<?php

// request class

class RequestClass {
    private $method;
    private $query;
    private $tokens;
    private $params = [];

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
        //
        // The route is worked out in three steps, most reliable first:
        //
        // 1. The request line itself. REQUEST_URI still holds the original
        //    address (`/patients/search/Smith%20%26%20Sons?page=2`), while
        //    QUERY_STRING holds the *decoded* path followed by `&` and the
        //    real query (`patients/search/Smith & Sons&page=2`). When
        //    QUERY_STRING starts with the request's own decoded path, that
        //    whole path is the route -- including a `&` or `?` that was
        //    percent-encoded inside it, which cutting at the first `&`
        //    (step 2) would wrongly chop off, and the real query is
        //    whatever follows. If a deployment rewrites QUERY_STRING itself
        //    (erweb's sub-path support), the two no longer line up and this
        //    step steps aside for step 2.
        // 2. The classic rule above: the route ends at the first `&`.
        // 3. Some servers/proxies pass the `?` itself through instead of
        //    appending with `&` (`patients?per_page=25`): cut there too, so
        //    the route is still `patients` rather than a 404.
        $qs = (string)($asrvr['QUERY_STRING'] ?? '');
        $uri = (string)($asrvr['REQUEST_URI'] ?? '');
        $uriPath = (string)parse_url($uri, PHP_URL_PATH);
        $uriQuery = (string)parse_url($uri, PHP_URL_QUERY);
        $uriRoute = ltrim(rawurldecode($uriPath), '/');

        $rest = '';
        if ($uriRoute !== ''
            && strpos($qs, $uriRoute) === 0
            && (strlen($qs) === strlen($uriRoute) || $qs[strlen($uriRoute)] === '&')) {
            $this->query = $uriRoute;
            $rest = (string)substr($qs, strlen($uriRoute) + 1);
        } elseif ($uriRoute === '' && $qs !== '' && $qs === $uriQuery) {
            // `/?x=1` on the home page: no path part was rewritten in, the
            // whole QUERY_STRING is the real query.
            $this->query = '';
            $rest = $qs;
        } else {
            $this->query = (string)strtok($qs, '&');
            $rest = (string)substr($qs, strlen($this->query) + 1);
            $q = strpos($this->query, '?');
            if ($q !== false) {
                $rest = substr($this->query, $q + 1) . ($rest !== '' ? '&' . $rest : '');
                $this->query = substr($this->query, 0, $q);
            }
        }

        // The query parameters the browser actually sent. The request
        // line's own query is the most direct source; otherwise whatever
        // followed the route in QUERY_STRING. (PHP's $_GET also carries them,
        // but with the route itself as a stray empty key.)
        parse_str($uriQuery !== '' ? $uriQuery : $rest, $this->params);

        $this->tokens = explode('/', '/'.$this->query);
        $this->tokens[0] = $this->query;

        $this->method = strtolower( $asrvr['REQUEST_METHOD'] );

        // print_r( $this );
    }

    function getQueryString() {
        return $this->query;
    }

    /**
     * The real query parameters of this request (`?per_page=25&page=2`),
     * as an array -- without the route itself, which PHP's own $_GET
     * carries as a stray empty key under the rewrite web/.htaccess does.
     */
    function getParams() {
        return $this->params;
    }

    /** One query parameter, or $default when it was not sent. */
    function getParam($name, $default = null) {
        return array_key_exists($name, $this->params) ? $this->params[$name] : $default;
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