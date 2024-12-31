<?php
function convertStructureDToC($inputFile, $outputFile = null)
{
    // Load the YAML file
    $structureD = yaml_parse_file($inputFile);
    // print_r($structureD);

    if (!$structureD) {
        throw new Exception("Failed to parse the input YAML file.");
    }

    // Initialize the Structure C format
    $structureC = [
/*
        'form' => [
            'method' => 'post',
            'action' => 'submit.php',
            'inputs' => []
        ],
*/
        'table' => [
            'name' => '',
            // 'fields' => []
        ]
    ];

    // Check for table structure in both formats
    if (isset($structureD['table'])) {
        $tableName = is_array($structureD['table']) ? ($structureD['table']['name'] ?? '') : $structureD['table'];
        $tableClass = is_array($structureD['table']) ? ($structureD['table']['class'] ?? '') : $tableName . 'Class';    //$structureD['table'];

        $structureC['table']['name'] = $tableName;
        $structureC['table']['class'] = $tableClass;
        $tableExt = $structureD['table']['extention'] ?? null;
        $tableExtend = $structureD['table']['extends'] ?? null;

        if(!is_null($tableExt))$structureC['table']['extention'] = $tableExt;
        if(!is_null($tableExtend))$structureC['table']['extends'] = $tableExt;
    }

    // Iterate over fields
    $fields = $structureD['table']['fields'] ?? [];
    // echo("testing fields: " . print_r($fields, 1));
    foreach ($fields as $field) {
        // echo("testing field: " . print_r($field, 1));
        if (is_string($field)) {
            // Handle string-based fields
            $fieldParts = explode(':', $field, 2);
            $fieldName = trim($fieldParts[0]);
            $fieldAttributes = isset($fieldParts[1]) ? trim($fieldParts[1]) : '';
        } else {
            // Handle object-based fields
            $fieldName = $field['name'] ?? '';
            $fieldAttributes = $field['type'] ?? '';
            
            $required = null;

            if(isset($field['required']))$required = $field['required'];
            if(isset($field['db_required']))$required = $field['db_required'];   
        }

        // Parse field attributes
        $isInternalType = str_starts_with($fieldAttributes, '@');
        $type = $isInternalType ? $fieldAttributes : strtok(strtolower($fieldAttributes), ' ');
        // $required = !$isInternalType && strpos($fieldAttributes, 'not null') !== false;
        $default = null;

        if(isset($field['default']))$default = $field['default'];
        if(isset($field['db_default']))$default = $field['db_default'];


        if (preg_match("/default\s+(['\"]?)(.+?)\\1/i", $fieldAttributes, $matches)) {
            $default = $matches[2];
        }

        $r = [];
        $r['name'] = $fieldName;
        $r['type'] = $type;
        if(!is_null($required))$r['required'] = $required;
        if(!is_null($default))$r['default'] = $default;

        // Add to the database fields
        $structureC['table']['fields'][] = $r;
/*
        [
            'name' => $fieldName,
            'type' => $type,
            'required' => $required,
            'default' => $default
        ];
*/        
        // Add to the form inputs if applicable
/*
        if (!$isInternalType && !in_array($type, ['datetime', 'text', 'boolean'])) {
            $inputType = match ($type) {
                'char', 'varchar' => 'text',
                'boolean' => 'checkbox',
                default => 'text'
            };

            $structureC['form']['inputs'][] = [
                'name' => $fieldName,
                'type' => $inputType,
                'label' => ucfirst($fieldName),
                'required' => $required,
                'default' => $default
            ];
        }
*/
    }

    // print_r($structureC);
    // Save the converted structure to a new YAML file
    $yamlOutput = yaml_emit($structureC);

    if(is_null($outputFile)) {
        return $yamlOutput;
    }

    file_put_contents($outputFile, $yamlOutput);

    echo "Conversion completed. Output saved to $outputFile\n";
}

// Usage
try {
    if($argc < 2) {
        echo "Usage:\n\tconvert input_file output_file\n\n";
        exit(-1);
    }

    $inputFile = $argv[1];  //'structure_d.yaml'; // Path to your input YAML file in Structure D
    // $outputFile = $argv[2]; //'structure_c.yaml'; // Path to your output YAML file in Structure C
    $res = convertStructureDToC($inputFile);
    echo ($res );
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
