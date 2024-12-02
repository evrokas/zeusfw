<?php

/* common HTTP error handlers */

// 404
function error_404() {
    return(Renderer::render('404.zetem', []));
}


function error_401() {
    return(Renderer::render('401.zetem', []));
}