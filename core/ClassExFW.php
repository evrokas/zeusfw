<?php

class userTokensClassEx extends userTokensClass {

    static function generate_tokens(): array {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
    
        return [$selector, $validator, $selector . ':' . $validator];
    }
    
    static function parse_token(string $token): ?array {
        $parts = explode(':', $token);
        if($parts && count($parts) == 2) {
            return [$parts[0], $parts[1]];
        }
        return null;
    }
    
    static function insert_user_token($create, string $uname, string $selector, string $validator, string $remoteip, string $useragent, string $expiry): bool {
        $tok = new userTokensClass(
            ['cdate' => $create,
                'selector' => $selector, 
                "validator" => $validator, 
                'uname' => $uname, 
                'remoteip' => $remoteip,
                'useragent' => $useragent,
                'expiry' => $expiry
            ]);
        $tok->insert();
    
        return true;
    }
        
    static function getUserTokenBySelector(string $selector) {
        $sql = "SELECT * FROM user_tokens WHERE selector=:selector AND expiry>= now() LIMIT 1";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":selector", $selector, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            $rclass = new userTokensClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }


    static function delete_user_token(string $uname, string $remoteip, string $useragent): bool {
        $sql = "DELETE FROM user_tokens WHERE uname=:uname AND remoteip=:remoteip AND useragent=:useragent";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);
        $st->bindValue(":remoteip", $remoteip, PDO::PARAM_STR);
        $st->bindValue(":useragent", $useragent, PDO::PARAM_STR);

        return $st->execute();
    }

    // Neither remoteip nor useragent are used to filter this lookup --
    // both parameters are kept only so existing call sites don't need to
    // change shape. remoteip was already dropped from the WHERE clause
    // (see the commented-out line below, from that earlier fix); useragent
    // was missed at the time and stayed a real, silent bug: isUserLoggedin()
    // (Kernel.php) already validates the token itself via token_is_valid()
    // (selector+validator only, no device fingerprint at all) *before*
    // calling this -- so by the time this runs, the token is already known
    // cryptographically valid. Filtering this second, redundant lookup by
    // useragent meant a perfectly valid remember-me cookie silently failed
    // to log anyone in the moment the UA string differed even slightly from
    // whenever the cookie was first issued (an app update, a browser
    // update, a different WebView/browser context -- all realistic on
    // mobile, and none of them a sign the cookie was stolen or forged).
    // The failure was also completely silent: isUserLoggedin() doesn't
    // clear the cookie or show any message in that case, it just quietly
    // never logs the visitor in, which is exactly the reported symptom.
    static function getUserByToken(string $token, string $remoteip = '', string $useragent = '') {
        $tokens = userTokensClassEx::parse_token($token);
        prelog("getUserByToken: $token, $tokens[0]");

        // $sql = "SELECT * FROM users INNER JOIN user_tokens ON user_tokens.uname=users.uname WHERE selector=:selector AND remoteip=:remoteip AND useragent=:useragent AND expiry > now() LIMIT 1";

        // stop using ip (and useragent) for logging in -- see this
        // function's own docblock above for why useragent was removed too.
        $sql = "SELECT * FROM users INNER JOIN user_tokens ON user_tokens.uname=users.uname WHERE selector=:selector AND expiry > now() LIMIT 1";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":selector", $tokens[0], PDO::PARAM_STR);
        // $st->bindValue(":remoteip", $remoteip, PDO::PARAM_STR);
        // $st->bindValue(":useragent", $useragent, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            // prelog(print_r($row, 1));
            $rclass = new userTokensClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }

    static function token_is_valid(string $token): bool {
        global $kernel;

        [$selector, $validator] = self::parse_token($token);
        prelog("token_is_valid: selector: $selector, validator: $validator");
        
        $tokens = self::getUserTokenBySelector($selector);
        prelog("token_is_valid: getUserTokenBySelector -> tokens: " . print_r($tokens, 1));
        
        if(!$tokens) {
            prelog("getUserTokenBySelector returned null");
            return false;
        }

        prelog("Test for validator correctness: validator: $validator hashed: " 
                . $tokens->getvalidator()
                . " (hashed validator: " . password_hash($validator, PASSWORD_DEFAULT) . ")\n");
        prelog("verify: " . (password_verify('a'.$validator, $tokens->getvalidator()))?"yes":"no" );
        
        return password_verify($validator, $tokens->getvalidator());
    }
    
    static function get_expired_user_tokens(): null|array {
        $sql = 'SELECT * FROM user_tokens WHERE expiry < NOW()';
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->execute();

        $results = [];
        while( $row = $st->fetch(PDO::FETCH_ASSOC) ) {
            $results[] = new userTokensClass( $row );
        }

        return $results;
    }

    static function remove_expired_user_tokens() {
        $sql = 'DELETE FROM user_tokens WHERE expiry < NOW()';
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->execute();
    }

    // Rotates the validator on a remember-me row -- called on every
    // successful cookie-based login (Kernel::isUserLoggedin()) so a
    // leaked-but-unused cookie value goes stale the moment it's actually
    // used once by anyone. Same selector/expiry, only the validator half
    // and last_used_at change.
    static function rotate_token(userTokensClass $tokenRow, string $newValidator): bool {
        $tokenRow->setvalidator(password_hash($newValidator, PASSWORD_DEFAULT));
        $tokenRow->setlast_used_at(getDBtime());
        return $tokenRow->update();
    }

    // Deletes the one row matching a specific cookie's selector -- used by
    // logout() (current device only) and by isUserLoggedin() when a token
    // is valid but its user account no longer resolves.
    static function delete_by_selector(string $selector): bool {
        $sql = "DELETE FROM user_tokens WHERE selector=:selector";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":selector", $selector, PDO::PARAM_STR);
        return $st->execute();
    }

    // All remembered devices for a user, newest first -- powers the
    // Devices section on /profile.
    static function getUserTokens(string $uname): array {
        $sql = "SELECT * FROM user_tokens WHERE uname=:uname ORDER BY cdate DESC";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);
        $st->execute();

        $list = [];
        while( $row = $st->fetch() ) {
            $rclass = new userTokensClass();
            $rclass->loadFields( $row );
            $list[] = $rclass;
        }
        return $list;
    }

    // Revoke-by-id for the Devices UI -- WHERE id=:id AND uname=:uname is
    // an IDOR guard so a user can only ever revoke their own tokens, same
    // as DocArc's account.php "WHERE id=:id AND user_id=:user_id".
    static function delete_by_id_for_uname(int $id, string $uname): bool {
        $sql = "DELETE FROM user_tokens WHERE id=:id AND uname=:uname";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":id", $id, PDO::PARAM_INT);
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);
        return $st->execute();
    }
}
