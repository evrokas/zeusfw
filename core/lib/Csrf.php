<?php

/*
 * Session-bound CSRF token generation/verification. Framework-level
 * utility, purely additive -- nothing in core calls this by default, so
 * an app that never references csrfClass sees zero behavior change. An
 * app opts in per form by rendering csrf_field() inside it and calling
 * csrfClass::requireValid()/requireValidJson() at the top of the
 * corresponding POST handler (see zpms's own CLAUDE.md for the first
 * real adopter and its full endpoint-by-endpoint wiring).
 *
 * One token per session (not per-form/per-request) -- simpler, and
 * standard practice for a synchronizer-token pattern defending against
 * cross-site *origin* forgery, as opposed to same-site replay, which
 * this isn't attempting to solve. Requires a session to already be
 * started (zeusfw_session_start()/session_start() before Csrf.php's
 * functions are ever called) -- every app in this framework already
 * starts its session before routing, same as SecurityClass.
 */

class csrfClass {
    const SESSION_KEY = 'csrf_token';

    // Opt-in switch for the one shared entry point CSRF can't reach via a
    // per-app POST handler -- login_post() below lives in this same core
    // file's neighbour (UserLogin.php) and runs for every app on this
    // framework, not just the one that adds a token field to its
    // login.zetem. Defaulting to false keeps every other app's login flow
    // completely unchanged; only an app that has actually put csrf_field()
    // into its own login form should ever call enableLoginProtection().
    static $enforceLogin = false;

    static function enableLoginProtection(): void {
        self::$enforceLogin = true;
    }

    // Same opt-in reasoning as $enforceLogin above, for the other shared
    // entry point that isn't a per-app POST handler:
    // formsClass::processform() (WebForms.php) is the single dispatcher
    // for every DB-defined webform, across every app that uses this
    // subsystem. generateHTMLForm() (FormElement.php) always renders a
    // csrf_field() into its output regardless of this flag -- an extra
    // hidden input is inert markup for any app not checking it -- but
    // processform() only *enforces* a valid token once an app calls
    // enableWebformProtection() itself.
    static $enforceWebforms = false;

    static function enableWebformProtection(): void {
        self::$enforceWebforms = true;
    }

    // Returns the current session's token, minting one on first use.
    static function token(): string {
        if(empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    // Ready-to-echo hidden form field carrying the current token.
    static function field(): string {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    static function verify(?string $submitted): bool {
        if(empty($_SESSION[self::SESSION_KEY]) || empty($submitted)) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $submitted);
    }

    // Reads the submitted token from wherever the caller put it: a plain
    // form POST carries it as csrf_token; a fetch()/XHR call that isn't
    // building its FormData from the real <form> element (so the hidden
    // field never makes it into the body) sends it as the X-CSRF-Token
    // header instead -- same two-channel convention already used by
    // ernsauth, the other app on this account with CSRF wired up.
    static function verifyRequest(): bool {
        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return self::verify($submitted);
    }

    // Guard for a normal (redirect/HTML-rendering) POST handler. Returns
    // the 403 page content on failure, null on success -- deliberately the
    // same "return null when OK, return content when not" shape as
    // SecurityClass::require(), so existing call sites can chain it
    // identically: if($err = csrfClass::requireValid())return $err;
    static function requireValid(): ?string {
        if(self::verifyRequest())return null;
        http_response_code(403);
        return error_403('Μη έγκυρο token ασφαλείας (CSRF). Ανανεώστε τη σελίδα και προσπαθήστε ξανά.');
    }

    // Guard for a JSON/AJAX POST handler, which never renders a normal
    // page -- exits with a 403 JSON body immediately, matching the
    // {'success': false, 'error': ...} shape those handlers already use
    // for every other failure path.
    static function requireValidJson(): void {
        if(self::verifyRequest())return;
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'invalid csrf token']);
        exit();
    }
}

// Template-callable wrappers -- .zetem files call plain global functions
// for this kind of thing (t(), rel_url(), guid()), never a static class
// method directly, so these are the forms templates actually use:
// {{ csrf_field() }} inside a <form>, {{ csrf_token() }} for the raw
// value (the <meta name="csrf-token"> tag an app's page shell renders
// for its own JS to read -- see zpms's templates/page/main.zetem).
function csrf_field(): string {
    return csrfClass::field();
}

function csrf_token(): string {
    return csrfClass::token();
}
