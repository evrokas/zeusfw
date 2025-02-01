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

    static function getUserByToken(string $token, string $remoteip, string $useragent) {
        $tokens = userTokensClassEx::parse_token($token);
        prelog("getUserByToken: $token, $tokens[0]");

        // $sql = "SELECT * FROM users INNER JOIN user_tokens ON user_tokens.uname=users.uname WHERE selector=:selector AND remoteip=:remoteip AND useragent=:useragent AND expiry > now() LIMIT 1";
        
        // stop using ip for logging in
        $sql = "SELECT * FROM users INNER JOIN user_tokens ON user_tokens.uname=users.uname WHERE selector=:selector AND useragent=:useragent AND expiry > now() LIMIT 1";
        $st = dbConnection::getConnection()->prepare( $sql );
        $st->bindValue(":selector", $tokens[0], PDO::PARAM_STR);
        // $st->bindValue(":remoteip", $remoteip, PDO::PARAM_STR);
        $st->bindValue(":useragent", $useragent, PDO::PARAM_STR);
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
        // prelog("token_is_valid: selector: $selector, validator: $validator");
        
        $tokens = self::getUserTokenBySelector($selector);
        // prelog("token_is_valid: tokens: " . print_r($tokens, 1));
        
        if(!$tokens)return false;
        
        prelog("Test for validator correctness: validator: $validator hashed: " . $tokens->getvalidator() . " (hashed validator: " . password_hash($validator, PASSWORD_DEFAULT) . ")\n");
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
}
