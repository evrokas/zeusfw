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
    
    static function insert_user_token(string $uname, string $selector, string $validator, string $expiry): bool {
        $tok = new userTokensClass(
            ['selector' => $selector, 
                "validator" => $validator, 
                'uname' => $uname, 
                'expiry' => $expiry
            ]);
        $tok->insert();
    
        return true;
    }
        
    static function getUserTokenBySelector(string $selector) {
 
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }

        $sql = "SELECT * FROM user_tokens WHERE selector=:selector AND expiry>= now() LIMIT 1";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":selector", $selector, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            $rclass = new userTokensClass();
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }


    static function delete_user_token(string $uname): bool {
        global $AppDBConnection;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return false;
            }
        }

        $sql = "DELETE FROM user_tokens WHERE uname=:uname";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":uname", $uname, PDO::PARAM_STR);

        return $st->execute();
    }

    static function getUserByToken(string $token) {
        global $AppDBConnection;
        global $kernel;

        if(!$AppDBConnection->isConnected()) {
            if(!$AppDBConnection->Connect()) {
                echo 'Could not connect to database';
                return null;
            }
        }

        $tokens = userTokensClassEx::parse_token($token);
        prelog("getUserByToken: $token, $tokens[0]");

        $sql = "SELECT * FROM users INNER JOIN user_tokens ON user_tokens.uname=users.uname WHERE selector=:selector AND expiry > now() LIMIT 1";
        $st = $AppDBConnection->getConnection()->prepare( $sql );
        $st->bindValue(":selector", $tokens[0], PDO::PARAM_STR);
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

    
}
