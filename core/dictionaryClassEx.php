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
        // echopre("langs: " . print_r($langs, 1));
        $columns = implode(',', array_keys($langs));
        $placeholders = implode(",", array_fill(0, count($langs), "?"));
        $flagColumn = implode(",", array_map(fn($lang) => $lang . "_set", array_keys($langs)));
        $flagPlaceholders = "1,".implode(",", array_fill(1, count($langs)-1, "0"));

        $sql = "INSERT INTO dictionary ($columns, $flagColumn) VALUES ($placeholders,$flagPlaceholders)";
        // echopre("sql b: $sql");


        $st = dbConnection::getConnection()->prepare( $sql );
        $st->execute(array_fill(0, count($langs), $token));

        // return requested token since no translation is found, yet!
        return $token;
    }

    /**
     * Get all dictionary entries
     *
     * @return array Array of all dictionary entries
     */
    static function getAllTokens() {
        global $kernel;

        $sql = "SELECT * FROM dictionary ORDER BY id DESC";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update a specific translation
     *
     * @param string $token The token to update
     * @param string $lang Language code
     * @param string $translation The translation text
     * @return bool Success status
     */
    static function updateTranslation($token, $lang, $translation) {
        global $kernel;

        // Get supported languages
        $langs = array_keys($kernel->getConfig('languages'));

        if (!in_array($lang, $langs)) {
            return false;
        }

        // Build the WHERE clause to find the token
        $placeholders = implode(" OR ", array_map(fn($l) => "($l = ?)", $langs));
        $sql = "SELECT id FROM dictionary WHERE $placeholders LIMIT 1";

        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute(array_fill(0, count($langs), $token));

        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            // Update existing entry
            $flagColumn = $lang . "_set";
            $updateSql = "UPDATE dictionary SET $lang = :translation, $flagColumn = 1 WHERE id = :id";

            $st = dbConnection::getConnection()->prepare($updateSql);
            return $st->execute([
                ':translation' => $translation,
                ':id' => $row['id']
            ]);
        }

        return false;
    }

    /**
     * Delete a dictionary token
     *
     * @param string $token The token to delete
     * @return bool Success status
     */
    static function deleteToken($token) {
        global $kernel;

        $langs = array_keys($kernel->getConfig('languages'));

        // Find the token
        $placeholders = implode(" OR ", array_map(fn($l) => "($l = ?)", $langs));
        $sql = "DELETE FROM dictionary WHERE $placeholders";

        $st = dbConnection::getConnection()->prepare($sql);
        return $st->execute(array_fill(0, count($langs), $token));
    }

    /**
     * Get untranslated tokens for a specific language
     *
     * @param string $lang Language code
     * @return array Array of untranslated entries
     */
    static function getUntranslated($lang) {
        global $kernel;

        $langs = array_keys($kernel->getConfig('languages'));

        if (!in_array($lang, $langs)) {
            return [];
        }

        $flagColumn = $lang . "_set";
        $sql = "SELECT * FROM dictionary WHERE $flagColumn = 0 OR $lang IS NULL OR $lang = ''";

        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export dictionary to YAML format
     *
     * @param string $lang Language code
     * @return string YAML formatted string
     */
    static function exportToYAML($lang) {
        global $kernel;

        $langs = array_keys($kernel->getConfig('languages'));

        if (!in_array($lang, $langs)) {
            return "# Invalid language: $lang\n";
        }

        $sql = "SELECT * FROM dictionary ORDER BY id ASC";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute();

        $entries = $st->fetchAll(PDO::FETCH_ASSOC);
        $output = "# Dictionary export for language: $lang\n";
        $output .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "# Total entries: " . count($entries) . "\n\n";
        $output .= "dictionary:\n";

        foreach ($entries as $entry) {
            if (!empty($entry[$lang])) {
                // Use the first non-empty language as the key
                $key = null;
                foreach ($langs as $l) {
                    if (!empty($entry[$l])) {
                        $key = $entry[$l];
                        break;
                    }
                }

                if ($key) {
                    // Escape special characters for YAML
                    $key = str_replace('"', '\\"', $key);
                    $value = str_replace('"', '\\"', $entry[$lang]);
                    $output .= "  \"$key\": \"$value\"\n";
                }
            }
        }

        return $output;
    }

    /**
     * Import YAML file to dictionary
     *
     * @param string $lang Language code
     * @param string $file Path to YAML file
     * @return array Status array with success/error counts
     */
    static function importFromYAML($lang, $file) {
        global $kernel;

        $langs = array_keys($kernel->getConfig('languages'));

        if (!in_array($lang, $langs)) {
            return ['error' => 'Invalid language', 'imported' => 0, 'failed' => 0];
        }

        if (!file_exists($file)) {
            return ['error' => 'File not found', 'imported' => 0, 'failed' => 0];
        }

        try {
            $data = yaml_parse_file($file);

            if (!is_array($data)) {
                return ['error' => 'Invalid YAML format', 'imported' => 0, 'failed' => 0];
            }

            // Handle nested dictionary structure
            $translations = $data['dictionary'] ?? $data;

            $imported = 0;
            $failed = 0;

            foreach ($translations as $key => $value) {
                // Check if token already exists
                $placeholders = implode(" OR ", array_map(fn($l) => "($l = ?)", $langs));
                $sql = "SELECT id FROM dictionary WHERE $placeholders LIMIT 1";

                $st = dbConnection::getConnection()->prepare($sql);
                $st->execute(array_fill(0, count($langs), $key));

                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    // Update existing
                    if (self::updateTranslation($key, $lang, $value)) {
                        $imported++;
                    } else {
                        $failed++;
                    }
                } else {
                    // Insert new entry
                    $columns = implode(',', $langs);
                    $placeholders = implode(",", array_fill(0, count($langs), "?"));
                    $flagColumns = implode(",", array_map(fn($l) => $l . "_set", $langs));

                    // Set flags: 1 for the imported language, 0 for others
                    $flags = array_map(fn($l) => $l === $lang ? 1 : 0, $langs);
                    $flagValues = implode(",", $flags);

                    $insertSql = "INSERT INTO dictionary ($columns, $flagColumns) VALUES ($placeholders, $flagValues)";

                    $values = array_map(fn($l) => $l === $lang ? $value : $key, $langs);

                    try {
                        $st = dbConnection::getConnection()->prepare($insertSql);
                        if ($st->execute($values)) {
                            $imported++;
                        } else {
                            $failed++;
                        }
                    } catch (Exception $e) {
                        $failed++;
                    }
                }
            }

            return [
                'imported' => $imported,
                'failed' => $failed,
                'total' => count($translations)
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'imported' => 0, 'failed' => 0];
        }
    }

    /**
     * Get translation statistics
     *
     * @return array Statistics per language
     */
    static function getTranslationStats() {
        global $kernel;

        $langs = array_keys($kernel->getConfig('languages'));
        $stats = [];

        $sql = "SELECT COUNT(*) as total FROM dictionary";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute();
        $total = $st->fetch(PDO::FETCH_ASSOC)['total'];

        foreach ($langs as $lang) {
            $flagColumn = $lang . "_set";

            // Count translated entries
            $translatedSql = "SELECT COUNT(*) as count FROM dictionary WHERE $flagColumn = 1 AND $lang IS NOT NULL AND $lang != ''";
            $st = dbConnection::getConnection()->prepare($translatedSql);
            $st->execute();
            $translated = $st->fetch(PDO::FETCH_ASSOC)['count'];

            // Count untranslated entries
            $untranslatedSql = "SELECT COUNT(*) as count FROM dictionary WHERE $flagColumn = 0 OR $lang IS NULL OR $lang = ''";
            $st = dbConnection::getConnection()->prepare($untranslatedSql);
            $st->execute();
            $untranslated = $st->fetch(PDO::FETCH_ASSOC)['count'];

            $stats[$lang] = [
                'total' => $total,
                'translated' => $translated,
                'untranslated' => $untranslated,
                'percentage' => $total > 0 ? round(($translated / $total) * 100, 2) : 0
            ];
        }

        return $stats;
    }

    /**
     * Get recently added tokens
     *
     * @param int $limit Number of tokens to return
     * @return array Recent dictionary entries
     */
    static function getRecentTokens($limit = 10) {
        $sql = "SELECT * FROM dictionary ORDER BY id DESC LIMIT :limit";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

}