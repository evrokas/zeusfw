<?php

/* common HTTP error handlers */

// 404
function error_404() {
    global $Renderer;

    return($Renderer->render('404.zetem', []));
}