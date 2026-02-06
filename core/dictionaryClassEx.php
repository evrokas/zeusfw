<?php


/*
 * extentions for dictionaryClass
 * 
 */

class dictionaryClassEx extends dictionaryClass {

    static function getTokenLanguage($token) {
      global $kernel;

        $sql = "SELECT * from dictionary WHERE ";

        $langs = $kernel->getConfig('languages');

        $results = [];
        // echopre("Available languages: " . implode(',', $langs));
        foreach($langs as $lng) {
            $sql0 = $sql . "$lng = :lng";
            // echopre("sql0: $sql0 [ ':lng' = $token ]");

            $st = dbConnection::getConnection()->prepare($sql0);
            $st->execute([":lng" => $token]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            
            // echopre("row: " . print_r($row, 1));
            if($row && count($row))$results[] = $row;
        }

        return $results;
    }

    static function translateToken($token) {
      global $kernel;

        if(empty($token))return "";
        $multilingual = $kernel->getConfig('multilingual');
        if(!$multilingual)return $token;

        // echopre("token: " . print_r($token, 1));

        $langs = $kernel->getConfig('languages');

        $current_language = $kernel->getCurrentLanguage();
        // echopre("lang = " . print_r($langs, 1));
        // echopre("lang = " . print_r(array_keys($langs)[0], 1));

        $placeholders = implode(" OR ", array_map(fn($lang) => "($lang = ?)", array_keys($langs)));
        $sql = "SELECT * FROM dictionary WHERE $placeholders LIMIT 1";
        // echopre("sql a: ".print_r($sql,1));

        $st = dbConnection::getConnection()->prepare( $sql );
        $st->execute(array_fill(0, count($langs), $token));

        if($row = $st->fetch(PDO::FETCH_ASSOC)) {

            // echopre("Found translation: "  . print_r($row, 1));
            // check if translation exists for current language,
            // if not, set as translated the requested token
            $translation = $row[ $current_language ] ?? $token;
            $flagColumn = $current_language . "_set";

            // check if translation flag is set, if yes return translation
            // if($row[$flagColumn]) {
                return $translation;
            // }
        }

        // echopre("Translation was not found, add token in dictionary");

        // the $token is not found in the dictionary
        // add a new entry
        $columns = implode(',', $langs);
        $placeholders = implode(",", array_fill(0, count($langs), "?"));
        $flagColumn = implode(",", array_map(fn($lang) => $lang . "_set", $langs));
        $flagPlaceholders = "1,".implode(",", array_fill(1, count($langs)-1, "0"));

        $sql = "INSERT INTO dictionary ($columns, $flagColumn) VALUES ($placeholders,$flagPlaceholders)";
        // echopre("sql b: $sql");


        $st = dbConnection::getConnection()->prepare( $sql );
        $st->execute(array_fill(0, count($langs), $token));

        // return requested token since no translation is found, yet!
        return $token;
    }


}