<?php

// Example Output

// For the YAML file:

// form:
//   method: "post"
//   action: "submit.php"
//   inputs:
//     - name: "username"
//       type: "text"
//       required: true
//     - name: "email"
//       type: "email"
//       required: true
//     - name: "age"
//       type: "number"
//     - name: "dob"
//       type: "date"
//     - name: "hobbies"
//       type: "checkbox"
//     - name: "bio"
//       type: "textarea"

// The generated SQL would be:

// CREATE TABLE `form_data` (
//   id INT AUTO_INCREMENT PRIMARY KEY,
//   `username` VARCHAR(255) NOT NULL,
//   `email` VARCHAR(255) NOT NULL,
//   `age` INT,
//   `dob` DATE,
//   `hobbies` TEXT,
//   `bio` TEXT
// );


// Customization

// Modify the $typeMapping array to adjust the SQL types for specific inputs.
// Add extra constraints, like UNIQUE or DEFAULT values, based on YAML attributes.

// Let me know if you need additional features, like handling indexes or foreign keys!


/**
 * Generate SQL CREATE TABLE command based on a YAML form configuration.
 *
 * @param string $yamlFile Path to the YAML configuration file.
 * @param string $tableName Name of the table to be created.
 * @return string SQL command to create the table.
 */
function generateCreateTableSQL($yamlFile, $tableName)
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
    $columns = ["id INT AUTO_INCREMENT PRIMARY KEY"]; // Default primary key

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

    // Loop through each input and define its SQL column
    foreach ($inputs as $input) {
        $name = $input['name'] ?? null;
        $type = $input['type'] ?? 'text';
        $required = $input['required'] ?? false;

        if ($name) {
            $sqlType = $typeMapping[$type] ?? 'VARCHAR(255)'; // Default to VARCHAR if type not mapped
            $notNull = $required ? 'NOT NULL' : '';
            $columns[] = "`$name` $sqlType $notNull";
        }
    }

    // Generate the full CREATE TABLE SQL command
    $columnsSQL = implode(",\n  ", $columns);
    $createTableSQL = "CREATE TABLE `$tableName` (\n  $columnsSQL\n);";

    return $createTableSQL;
}

// Example usage:
$yamlFile = 'form_config.yaml';
$tableName = 'form_data';

try {
    $sql = generateCreateTableSQL($yamlFile, $tableName);
    echo "<pre>$sql</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
