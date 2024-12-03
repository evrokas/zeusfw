<?php

/**
 * Syncs a database table structure with a YAML configuration file.
 *
 * @param string $yamlFile Path to the YAML configuration file.
 * @param string $tableName Name of the database table to sync.
 * @param PDO $pdo PDO instance connected to the database.
 */
function syncTableWithYAML($yamlFile, $tableName, $pdo)
{
    // Check if YAML extension is available
    if (!function_exists('yaml_parse_file')) {
        die("YAML extension is required to run this function. Install the PHP YAML extension.");
    }

    // Load the YAML file
    if (!file_exists($yamlFile)) {
        die("YAML file not found: $yamlFile");
    }

    $config = yaml_parse_file($yamlFile);
    if (!$config || !isset($config['form']['inputs'])) {
        die("Invalid YAML configuration.");
    }

    $inputs = $config['form']['inputs'];

    // Map HTML input types to SQL data types
    $typeMapping = [
        'text' => 'VARCHAR(255)',
        'password' => 'VARCHAR(255)',
        'email' => 'VARCHAR(255)',
        'url' => 'VARCHAR(255)',
        'tel' => 'VARCHAR(15)',
        'number' => 'INT',
        'range' => 'INT',
        'date' => 'DATE',
        'time' => 'TIME',
        'datetime-local' => 'DATETIME',
        'month' => 'VARCHAR(7)',
        'week' => 'VARCHAR(7)',
        'color' => 'VARCHAR(7)',
        'textarea' => 'TEXT',
        'checkbox' => 'TEXT', // Store multiple values as CSV
        'radio' => 'VARCHAR(255)',
        'file' => 'VARCHAR(255)', // Store file paths
        'hidden' => 'VARCHAR(255)',
        'select' => 'VARCHAR(255)'
    ];

    // Get the current table structure
    $currentFields = [];
    $query = $pdo->query("DESCRIBE `$tableName`");
    if ($query) {
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $currentFields[$row['Field']] = $row;
        }
    } else {
        die("Table `$tableName` does not exist.");
    }

    // Track changes
    $changes = [];

    // Process YAML inputs
    foreach ($inputs as $input) {
        $name = $input['name'] ?? null;
        $type = $input['type'] ?? 'text';
        $required = $input['required'] ?? false;

        if ($name) {
            $sqlType = $typeMapping[$type] ?? 'VARCHAR(255)';
            $notNull = $required ? 'NOT NULL' : 'NULL';

            if (isset($currentFields[$name])) {
                // Check if the field needs to be modified
                $field = $currentFields[$name];
                $fieldType = strtoupper($field['Type']);
                $isNullable = $field['Null'] === 'YES' ? 'NULL' : 'NOT NULL';

                if ($fieldType !== strtoupper($sqlType) || $isNullable !== $notNull) {
                    $changes[] = "MODIFY `$name` $sqlType $notNull";
                }

                // Remove from currentFields (processed)
                unset($currentFields[$name]);
            } else {
                // Add new field
                $changes[] = "ADD `$name` $sqlType $notNull";
            }
        }
    }

    // Remove fields that are in the table but not in the YAML
    foreach ($currentFields as $fieldName => $field) {
        if ($fieldName !== 'id') { // Keep primary key
            $changes[] = "DROP `$fieldName`";
        }
    }

    // Execute changes if any
    if (!empty($changes)) {
        $alterSQL = "ALTER TABLE `$tableName` " . implode(", ", $changes);
        try {
            $pdo->exec($alterSQL);
            echo "Table `$tableName` has been successfully updated.\n";
        } catch (PDOException $e) {
            die("Failed to update table: " . $e->getMessage());
        }
    } else {
        echo "Table `$tableName` is already up to date.\n";
    }
}

// Example usage:
$yamlFile = 'form_config.yaml';
$tableName = 'form_data';
$host = 'localhost';
$dbname = 'test_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    syncTableWithYAML($yamlFile, $tableName, $pdo);
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
