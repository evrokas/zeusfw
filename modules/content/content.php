<?php
class contentModule extends moduleClass {
    function render($params = array()) {
        global $kernel;
        global $Request;
        global $content_response;

            return ($content_response);
    }
}

function register_content_module() {
    global $kernel;

    $kernel->registerModule( new contentModule(__DIR__, 'content', 'content.zetem') );
}