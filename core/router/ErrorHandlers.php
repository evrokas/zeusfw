<?php

/* common HTTP error handlers */

// 404
function error_404(string $errmsg = null): string {
    return(Renderer::render('404.zetem', ['error' => $errmsg]));
}


function error_401(string $errmsg = null): string {
    return(Renderer::render('401.zetem', ['error' => $errmsg]));
}