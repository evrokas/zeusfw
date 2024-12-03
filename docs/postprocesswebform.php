<?php

// Updated PHP Script: Processing, Validating, Saving to Database, and Exporting as YAML

// Key Additions

//     Named Array for YAML Export:
//         The form data is stored in a named array ['form_submission' => $data].

//     Export to YAML:
//         The yaml_emit() function is used to convert the named array into a YAML file.
//         The file is saved with a timestamp-based name, e.g., form_submission_1700000000.yaml.

//     File Saving:
//         The YAML file is written to the same directory using file_put_contents().

// Ensure YAML extension is installed
if (!function_exists('yaml_parse_file') || !function_exists('yaml_emit')) {
    die("YAML extension is required to run this script. Install the PHP YAML extension.");
}

// Load YAML configuration
$yamlFile = 'form_config.yaml';
if (!file_exists($yamlFile)) {
    die("YAML file not found: $yamlFile");
}

$config = yaml_parse_file($yamlFile);
if (!$config || !isset($config['form'])) {
    die("Invalid YAML configuration.");
}

// Extract form inputs from the YAML file
$formInputs = $config['form']['inputs'] ?? [];

// Database connection (replace with your credentials)
$host = 'localhost';
$dbname = 'test_db';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $data = [];

    // Validate inputs based on YAML configuration
    foreach ($formInputs as $input) {
        $name = $input['name'] ?? null;
        $type = $input['type'] ?? 'text';
        $required = $input['required'] ?? false;

        if ($name) {
            $value = $_POST[$name] ?? null;

            // Validate required fields
            if ($required && empty($value)) {
                $errors[$name] = "$name is required.";
                continue;
            }

            // Type-specific validation
            if (!empty($value)) {
                switch ($type) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$name] = "Invalid email format.";
                        }
                        break;

                    case 'number':
                        if (!is_numeric($value)) {
                            $errors[$name] = "$name must be a number.";
                        }
                        break;

                    case 'date':
                        if (!DateTime::createFromFormat('Y-m-d', $value)) {
                            $errors[$name] = "Invalid date format (YYYY-MM-DD required).";
                        }
                        break;

                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$name] = "Invalid URL format.";
                        }
                        break;

                    case 'checkbox':
                        $value = is_array($value) ? implode(',', $value) : $value;
                        break;

                    default:
                        $value = htmlspecialchars($value); // Sanitize other inputs
                }
            }

            // Store sanitized value
            $data[$name] = $value;
        }
    }

    // If there are no errors, process the data
    if (empty($errors)) {
        try {
            // Insert data into the database
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $stmt = $pdo->prepare("INSERT INTO form_data ($columns) VALUES ($placeholders)");
            $stmt->execute(array_values($data));

            // Save data to a named array for YAML export
            $yamlExportData = ['form_submission' => $data];
            $yamlFileName = 'form_submission_' . time() . '.yaml';
            file_put_contents($yamlFileName, yaml_emit($yamlExportData));

            echo "<p>Data submitted successfully!</p>";
            echo "<p>YAML file exported as: $yamlFileName</p>";
        } catch (PDOException $e) {
            die("Failed to insert data: " . $e->getMessage());
        }
    } else {
        // Display errors
        echo "<h3>Validation Errors:</h3><ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
} else {
    echo "No form data submitted.";
}
?>
