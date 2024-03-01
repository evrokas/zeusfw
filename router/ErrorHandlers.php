<?php

/* common HTTP error handlers */

// 404
function error_404() {
    global $Renderer;

    return($Renderer->render('404.zetem', []));
}


function error_401() {
    global $Renderer;
    return($Renderer->render('401.zetem', []));
}