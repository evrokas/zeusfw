<?php

// Supported HTML Input Types

// The script supports the following types:

//     Basic Inputs: text, password, email, tel, url, number, range, date, time, datetime-local, month, week, color, search, hidden.
//     Choice Inputs: radio, checkbox.
//     File Inputs: file.
//     Button Types: submit, reset, button.
//     Other Elements: textarea, select.

// YAML file:

// form:
//   method: "post"
//   action: "submit.php"
//   inputs:
//     - name: "username"
//       id: "username"
//       class: "form-control"
//       type: "text"
//       label: "Username"
//       default: "JohnDoe"
//     - name: "password"
//       id: "password"
//       class: "form-control"
//       type: "password"
//       label: "Password"
//     - name: "email"
//       id: "email"
//       class: "form-control"
//       type: "email"
//       label: "Email"
//     - name: "age"
//       id: "age"
//       class: "form-control"
//       type: "number"
//       label: "Age"
//       min: 18
//       max: 100
//     - name: "dob"
//       id: "dob"
//       class: "form-control"
//       type: "date"
//       label: "Date of Birth"
//     - name: "gender"
//       id: "gender"
//       class: "form-control"
//       type: "radio"
//       label: "Gender"
//       default: "male"
//       options:
//         - value: "male"
//           label: "Male"
//         - value: "female"
//           label: "Female"
//     - name: "hobbies"
//       id: "hobbies"
//       class: "form-control"
//       type: "checkbox"
//       label: "Hobbies"
//       default: ["reading", "sports"]
//       options:
//         - value: "reading"
//           label: "Reading"
//         - value: "sports"
//           label: "Sports"
//         - value: "traveling"
//           label: "Traveling"
//     - name: "profile_picture"
//       id: "profile_picture"
//       class: "form-control"
//       type: "file"
//       label: "Profile Picture"
//     - name: "bio"
//       id: "bio"
//       class: "form-control"
//       type: "textarea"
//       label: "Bio"
//     - name: "submit"
//       id: "submit"
//       class: "btn btn-primary"
//       type: "submit"
//       label: "Submit"


// Ensure YAML extension is installed or use a library like Symfony's YAML parser.
if (!function_exists('yaml_parse_file')) {
    die("YAML extension is required to run this script. Install the PHP YAML extension.");
}

// Load YAML file
$yamlFile = 'form_config.yaml';
if (!file_exists($yamlFile)) {
    die("YAML file not found: $yamlFile");
}

$config = yaml_parse_file($yamlFile);
if (!$config || !isset($config['form'])) {
    die("Invalid YAML configuration.");
}

// Extract form configuration
$formConfig = $config['form'];
$formMethod = $formConfig['method'] ?? 'post';
$formAction = $formConfig['action'] ?? '';
$formInputs = $formConfig['inputs'] ?? [];

// Generate HTML form
echo "<form method=\"$formMethod\" action=\"$formAction\" enctype=\"multipart/form-data\">\n";

foreach ($formInputs as $input) {
    $name = htmlspecialchars($input['name'] ?? '');
    $id = htmlspecialchars($input['id'] ?? '');
    $class = htmlspecialchars($input['class'] ?? '');
    $type = htmlspecialchars($input['type'] ?? 'text');
    $label = htmlspecialchars($input['label'] ?? ucfirst($name));
    $default = $input['default'] ?? null;
    $options = $input['options'] ?? [];
    $additionalAttributes = '';

    // Handle additional attributes
    foreach ($input as $attr => $value) {
        if (!in_array($attr, ['name', 'id', 'class', 'type', 'label', 'default', 'options'])) {
            $additionalAttributes .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
        }
    }

    if ($name && $id) {
        // Add label if applicable
        if ($type !== 'hidden' && $type !== 'submit' && $type !== 'reset' && $type !== 'button') {
            echo "<label for=\"$id\">$label</label>\n";
        }

        // Generate input based on type
        switch ($type) {
            case 'select':
                echo "<select name=\"$name\" id=\"$id\" class=\"$class\"$additionalAttributes>\n";
                foreach ($options as $option) {
                    $value = htmlspecialchars($option['value'] ?? '');
                    $optionLabel = htmlspecialchars($option['label'] ?? $value);
                    $selected = ($default == $value) ? 'selected' : '';
                    echo "<option value=\"$value\" $selected>$optionLabel</option>\n";
                }
                echo "</select>\n";
                break;

            case 'radio':
                foreach ($options as $option) {
                    $value = htmlspecialchars($option['value'] ?? '');
                    $optionLabel = htmlspecialchars($option['label'] ?? $value);
                    $inputId = $id . "_" . $value;
                    $checked = ($default == $value) ? 'checked' : '';
                    echo "<input type=\"$type\" name=\"$name\" id=\"$inputId\" value=\"$value\" class=\"$class\" $checked$additionalAttributes>\n";
                    echo "<label for=\"$inputId\">$optionLabel</label>\n";
                }
                break;

            case 'checkbox':
                foreach ($options as $option) {
                    $value = htmlspecialchars($option['value'] ?? '');
                    $optionLabel = htmlspecialchars($option['label'] ?? $value);
                    $inputId = $id . "_" . $value;
                    $checked = (is_array($default) && in_array($value, $default)) ? 'checked' : '';
                    echo "<input type=\"$type\" name=\"{$name}[]\" id=\"$inputId\" value=\"$value\" class=\"$class\" $checked$additionalAttributes>\n";
                    echo "<label for=\"$inputId\">$optionLabel</label>\n";
                }
                break;

            case 'textarea':
                $content = htmlspecialchars($default ?? '');
                echo "<textarea name=\"$name\" id=\"$id\" class=\"$class\"$additionalAttributes>$content</textarea>\n";
                break;

            default:
                $value = in_array($type, ['text', 'email', 'url', 'tel', 'search', 'password', 'hidden']) ? htmlspecialchars($default ?? '') : '';
                echo "<input type=\"$type\" name=\"$name\" id=\"$id\" class=\"$class\" value=\"$value\"$additionalAttributes>\n";
                break;
        }
    }
}

echo "</form>\n";
?>
