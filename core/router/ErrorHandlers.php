<?php

/* common HTTP error handlers */

// 404
function error_404(string $errmsg = null): string {
    return(Renderer::render('404.zetem', ['error' => $errmsg]));
}


function error_401(string $errmsg = null): string {
    return(Renderer::render('401.zetem', ['error' => $errmsg]));
}

function error_403(string $errmsg = null): string {
    return(Renderer::render('403.zetem', ['error' => $errmsg]));
}

function error_500(string $errmsg = null): string {
    return(Renderer::render('500.zetem', ['error' => $errmsg]));
}

/**
 * Opt-in catch-all for otherwise-uncaught PHP errors, so a real crash shows
 * a real 500 page instead of a raw PHP error dump or a blank response.
 * Not called automatically anywhere in core - an app calls this once from
 * its own index.php, after Renderer::init() (error_500() needs the
 * renderer ready), to opt in. Purely additive: nothing here runs unless an
 * app explicitly registers it.
 *
 * Deliberately narrow in what it catches:
 * - set_exception_handler() for any uncaught Throwable.
 * - register_shutdown_function() checking error_get_last() for the fatal
 *   error types only (E_ERROR/E_PARSE/E_CORE_ERROR/E_COMPILE_ERROR/
 *   E_USER_ERROR) - these already terminate the script regardless, a
 *   shutdown function is the standard way to notice and respond to them.
 * No set_error_handler() is installed - ordinary warnings/notices/
 * deprecations (some apps have a known, accepted baseline of these) are
 * left completely alone, exactly as before this function is called.
 *
 * Both handlers build a minimal, self-contained HTML document by hand
 * rather than going through the app's normal page-rendering pipeline
 * (Kernel::renderPage(), nav/footer modules, any DB query) - deliberately,
 * since whatever just crashed may have left DB/module state unreliable,
 * and the error page itself must not risk a second failure. error_500()
 * (Renderer::render() only, no DB access) supplies the body; a hand-built
 * <html> wrapper with the essential styling inlined (not linked, so it
 * doesn't depend on static-asset serving still working) supplies the rest.
 * $cssPath, if given, is inlined verbatim via file_get_contents() - pass a
 * small, self-sufficient stylesheet (or nothing, for framework-default
 * plain styling).
 */
function zeusfw_register_error_handlers(?string $cssPath = null): void
{
    $renderCrashPage = static function () use ($cssPath): void {
        http_response_code(500);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $inlineCss = ($cssPath !== null && is_readable($cssPath)) ? file_get_contents($cssPath) : '';
        echo '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . ($inlineCss !== '' ? '<style>' . $inlineCss . '</style>' : '')
            . '</head><body>' . error_500() . '</body></html>';
        exit;
    };

    set_exception_handler(static function (Throwable $e) use ($renderCrashPage): void {
        $renderCrashPage();
    });

    register_shutdown_function(static function () use ($renderCrashPage): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($error['type'], $fatalTypes, true)) {
            $renderCrashPage();
        }
    });
}