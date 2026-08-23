<?php

/*
 * takes this approach from the beginning, starting from the login page
 * and proceeding implementing cookie, selector&validateor hashes, storage
 * in database, retrieving the hases and validating selector&validator
 * implementing another approach into the code is difficult and unnecessary
 * 
 * based on web pages:
 * https://www.phptutorial.net/php-tutorial/php-remember-me/
 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
 *
 */

/*
 * Opt-in login hardening -- same reasoning and shape as
 * csrfClass::$enforceLogin/$enforceWebforms (Csrf.php): both default off,
 * so every app already on this framework keeps its exact current login
 * behavior unless it explicitly opts in. Split into two independent
 * switches because they carry very different rollout risk:
 *
 * - $enforceLockout (brute-force lockout via the existing wrongpasscount
 *   counter, already tracked -- just never enforced until now): safe to
 *   enable immediately on any app. Every account starts at
 *   wrongpasscount=0, so turning this on can't retroactively lock anyone
 *   out; it only starts counting failed attempts from here on.
 *
 * - $enforceAccountStatus (active/expired columns must both be correct):
 *   NOT safe to enable blindly. core/classes/yaml/users.yaml defaults a
 *   brand-new row to active=false, expired=true, so any account created
 *   by hand (raw SQL -- there's no user-management UI anywhere in this
 *   framework or any app on it yet) without explicitly setting both could
 *   be logging in successfully *today* purely because nothing has ever
 *   checked those columns before now. Enable this only after confirming
 *   every real, currently-working account's row actually has active=1
 *   and expired=0 -- otherwise this locks them out the moment it's
 *   deployed.
 */
class LoginSecurityClass {
    static $enforceLockout = false;
    static $enforceAccountStatus = false;
    static $maxWrongPassCount = 5;

    static function enableLockout(): void {
        self::$enforceLockout = true;
    }

    static function enableAccountStatusEnforcement(): void {
        self::$enforceAccountStatus = true;
    }
}

/*
 * users.upass historically stored an unsalted hash('sha256', $password) --
 * fast to brute-force offline and, with no per-user salt, vulnerable to a
 * single precomputed rainbow table across every account on every app on
 * this framework. login_post() now verifies against either format and
 * transparently rehashes to PASSWORD_DEFAULT (bcrypt) the moment a legacy
 * account logs in successfully -- no separate migration script, no
 * user-management UI needed (none exists in this framework yet), and no
 * account is ever forced to reset a password it still knows correctly.
 * Accounts that never log in again simply keep their old hash indefinitely,
 * same as before this existed.
 */
function password_hash_is_legacy_sha256(?string $storedHash): bool {
    return password_get_info((string)$storedHash)['algo'] === null;
}

function password_matches_stored_hash(string $rawPassword, ?string $storedHash): bool {
    if ($storedHash === null || $storedHash === '') return false;
    if (password_hash_is_legacy_sha256($storedHash)) {
        return hash_equals($storedHash, hash('sha256', $rawPassword));
    }
    return password_verify($rawPassword, $storedHash);
}


 function setup_rememberme(string $uname) {
    prelog("Setup selector:validator");
    [$selector, $validator, $token] = userTokensClassEx::generate_tokens();
    prelog("Tokens: $selector:$validator");

    $remoteip = $_SERVER['REMOTE_ADDR'];
    $useragent = $_SERVER['HTTP_USER_AGENT'];

    // Every remember-me login inserts a fresh row rather than
    // delete-then-insert keyed on (uname, remoteip, useragent) -- that old
    // keying lost a device's remembered session on an IP change (mobile
    // networks, dynamic IP) and prevented more than one row per device+IP
    // combo from existing at once. N logins now simply means N rows, each
    // independently listable/revocable from the Devices section of /profile.
    // Expired rows are still cleaned up proactively by
    // userTokensClassEx::remove_expired_user_tokens() (Maintenance, runs on
    // every request), so this doesn't accumulate indefinitely.

    $days = 10;
    $expired_seconds = time() + 60 * 60 * 24 * $days;
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    $create = getDBtime();
    // prelog("expiry: $expiry\texpired_seconds: $expired_seconds");

    if(userTokensClassEx::insert_user_token($create, $uname, $selector, $hash_validator, $remoteip, $useragent, $expiry)) {
//        setcookie('zeusfwrememberme', $token, $expired_seconds, '/', null, true, true);
        // setcookie('zeusfwrememberme', $token, ['samesite'=>'true']);
        zeusfw_set_rememberme_cookie($token, $expired_seconds);
        // prelog("setup cookie for future!");
    }
}

function login($params) {
    return (Renderer::render("login.zetem", ['action' => 'login']));
}

function login_post($params) {
    global $kernel;

    // Opt-in only -- see csrfClass::$enforceLogin's docblock. An app that
    // hasn't called enableLoginProtection() (and put csrf_field() into its
    // own login.zetem) reaches this unchanged, exactly as before this
    // check existed.
    if(csrfClass::$enforceLogin && !csrfClass::verifyRequest()) {
        $kernel->addStatus('error', 'Μη έγκυρο token ασφαλείας (CSRF). Παρακαλώ προσπαθήστε ξανά.');
        header('location: ' . rel_url('/login'));
        exit();
    }

    // Looked up by username alone now, not by SQL-matching a precomputed
    // hash -- see password_matches_stored_hash()'s docblock above for why:
    // the stored hash can be either format, and only that helper knows
    // which comparison applies.
    $us = UsersClassEx::getUserAccount($_POST['username']);
    $passwordOk = $us && password_matches_stored_hash((string)$_POST['password'], $us->getupass());
    // prelog("User: " . print_r( $us, 1 ));

    // Opt-in only -- see LoginSecurityClass's own docblock above. Checked
    // even when the submitted password is correct: that's the whole point
    // of a lockout (a correct password on a locked-out account must still
    // be refused), and an inactive/expired account must never be allowed
    // in regardless of password correctness.
    if($us) {
        $lockedOut = LoginSecurityClass::$enforceLockout
            && ((int)$us->getwrongpasscount() >= LoginSecurityClass::$maxWrongPassCount);
        $disabled = LoginSecurityClass::$enforceAccountStatus
            && (!$us->getactive() || $us->getexpired());
        if($lockedOut || $disabled) {
            $kernel->addStatus('warning', $lockedOut
                ? 'Ο λογαριασμός έχει κλειδωθεί λόγω πολλών αποτυχημένων προσπαθειών σύνδεσης. Επικοινωνήστε με τον διαχειριστή.'
                : 'Ο λογαριασμός δεν είναι ενεργός.');
            error_log('login refused for ' . $us->getuname() . ($lockedOut ? ' (locked out)' : ' (inactive/expired)'));
            header('location: ' . rel_url('/login'));
            exit();
        }
    }

    if($us && $passwordOk) {
        // user exists and password matches

        // Opt-in extension point: an app can define
        // zeusfw_app_resolve_user_roles(usersClass $user): ?string to
        // supply the login role list from its own storage instead of the
        // legacy users.roles column -- same function_exists() pattern
        // already used for csrf_field() (core/lib/FormElement.php). An
        // app that doesn't define it (i.e. every app except zpms as of
        // this writing) gets $us->getroles() exactly as before; zpms
        // defines it in web/rbac.php, returning null (falls back to
        // getroles()) only for an account with no rows in its own
        // user_roles table yet.
        $uroles = function_exists('zeusfw_app_resolve_user_roles')
            ? (zeusfw_app_resolve_user_roles($us) ?? $us->getroles())
            : $us->getroles();
        echopre("try to login user ". $us->getuname() . " with rules: " . print_r($uroles));

        // ask kernel to login user
        $kernel->loginUser($us->getuname(), $uroles);

        if(isset($_POST['rememberme'])) {
            setup_rememberme($us->getuname());
        }

        // Transparent upgrade: an account still on the legacy unsalted
        // sha256 format gets rehashed to PASSWORD_DEFAULT right here, the
        // one place the plaintext password is ever available. See
        // password_matches_stored_hash()'s docblock above.
        if(password_hash_is_legacy_sha256($us->getupass())) {
            $us->setupass(password_hash((string)$_POST['password'], PASSWORD_DEFAULT));
        }

        // reset wrong password counter
        $us->setwrongpasscount(0);
        $us->update();

        header('location: '.rel_url('/profile'));
        exit();
    } else {
        // check for account with 'username' exists, if yes, then increase wrong password counter
        // for future reference
        if($us) {
            $us->setwrongpasscount( $us->getwrongpasscount() + 1);
            $us->update();
        }

        $kernel->addStatus('warning', 'Username and password does not match, or do not exist');
        error_log('login failed for ' . $_POST["username"]);
        header('location: ' . rel_url('/login'));
        exit();
    }
}

function logout($params) {
    global $kernel;

    // echo "Logout user<br>";

    $us = $kernel->getUserName();
    if($us) {
        // Deletes only the row for *this* device's cookie (matched by
        // selector, not the old uname+remoteip+useragent guess) -- logging
        // out on one device must not revoke other devices' remember-me
        // tokens. Use the Devices list on /profile to revoke other devices.
        $cookie = filter_input(INPUT_COOKIE, 'zeusfwrememberme');
        if($cookie) {
            $parsed = userTokensClassEx::parse_token($cookie);
            if($parsed) {
                userTokensClassEx::delete_by_selector($parsed[0]);
            }
        }
        zeusfw_clear_rememberme_cookie();

        $kernel->logoutUser();
    }
    header('location: '.rel_url('/'));
    exit();
}
