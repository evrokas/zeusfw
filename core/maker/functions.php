<?php

function generateHTMLForm0($yamlData) {
    $method = $yamlData['form']['method'] ?? 'post';
    $action = $yamlData['form']['action'] ?? '';

    // Start the form
    $formHtml = "<form method=\"$method\" action=\"$action\">\n";

    foreach ($yamlData['form']['inputs'] as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? ucfirst($name);
        $formType = $field['type'] ?? 'text';
        $formRequired = $field['required'] ?? false;
        $formDefault = $field['default'] ?? '';
        $options = $field['options'] ?? [];

        // Generate the label
        $formHtml .= "  <label for=\"$name\">$label</label>\n";

        // Generate the input field based on type
        switch ($formType) {
            case 'text':
            case 'password':
            case 'email':
            case 'number':
            case 'date':
            case 'time':
            case 'url':
            case 'color':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <input type=\"$formType\" id=\"$name\" name=\"$name\" value=\"$formDefault\" $required>\n";
                break;

            case 'textarea':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <textarea id=\"$name\" name=\"$name\" $required>$formDefault</textarea>\n";
                break;

            case 'select':
                $formHtml .= "  <select id=\"$name\" name=\"$name\">\n";
                foreach ($options as $option) {
                    $selected = ($formDefault == $option) ? 'selected' : '';
                    $formHtml .= "    <option value=\"$option\" $selected>$option</option>\n";
                }
                $formHtml .= "  </select>\n";
                break;

            case 'radio':
                foreach ($options as $option) {
                    $checked = ($formDefault == $option) ? 'checked' : '';
                    $formHtml .= "  <input type=\"radio\" id=\"$name-$option\" name=\"$name\" value=\"$option\" $checked>\n";
                    $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                }
                break;

            case 'checkbox':
                foreach ($options as $option) {
                    $isChecked = is_array($formDefault) && in_array($option, $formDefault) ? 'checked' : '';
                    $formHtml .= "  <input type=\"checkbox\" id=\"$name-$option\" name=\"{$name}[]\" value=\"$option\" $isChecked>\n";
                    $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                }
                break;

            case 'file':
                $formHtml .= "  <input type=\"file\" id=\"$name\" name=\"$name\">\n";
                break;

            case 'hidden':
                $formHtml .= "  <input type=\"hidden\" id=\"$name\" name=\"$name\" value=\"$formDefault\">\n";
                break;

            default:
                // Unsupported form type
                $formHtml .= "  <!-- Unsupported form type: $formType -->\n";
        }

        $formHtml .= "  <br>\n"; // Add a line break after each field
    }

    // Add buttons
    if (!empty($yamlData['form']['buttons'])) {
        foreach ($yamlData['form']['buttons'] as $button) {
            $buttonType = $button['type'] ?? 'button';
            $buttonLabel = $button['label'] ?? 'Button';
            $buttonAction = $button['action'] ?? '';

            $actionAttribute = $buttonType === 'button' && !empty($buttonAction) ? "onclick=\"$buttonAction\"" : '';
            $formHtml .= "  <button type=\"$buttonType\" $actionAttribute>$buttonLabel</button>\n";
        }
    }

    // Close the form
    $formHtml .= "</form>\n";

    return $formHtml;
}

function generateHTMLFormArray($yamlData) {
    $formArray = []; // Super array to hold all form elements

    if(array_key_exists('name', $yamlData['form']))$formArray['name'] = $yamlData['form']['name'];
    if(array_key_exists('action', $yamlData['form']))$formArray['attributes']['action'] = $yamlData['form']['action'];
    if(array_key_exists('method', $yamlData['form']))$formArray['attributes']['method'] = $yamlData['form']['method'];

    foreach ($yamlData['form']['inputs'] as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? ucfirst($name);
        $formType = $field['type'] ?? 'text';
        $formRequired = $field['required'] ?? false;
        $formDefault = $field['default'] ?? '';
        $options = $field['options'] ?? [];

        $inputElement = [
            'label' => $label,
            'type' => $formType,
            'name' => $name,
            'required' => $formRequired,
            'default' => $formDefault,
            'options' => $options,
        ];

        $formArray['inputs'][] = $inputElement;
    }

    // Add buttons
    if (!empty($yamlData['form']['buttons'])) {
        foreach ($yamlData['form']['buttons'] as $button) {
            $buttonType = $button['type'] ?? 'button';
            $buttonLabel = $button['label'] ?? 'Button';
            $buttonAction = $button['action'] ?? '';

            $buttonElement = [
                'type' => $buttonType,
                'label' => $buttonLabel,
                'action' => $buttonAction,
            ];

            $formArray['buttons'][] = $buttonElement;
        }
    }

    return $formArray; // Return the array for further processing
}


function generateHTMLFormTemplate($yamlData) {
    $method = $yamlData['table']['method'] ?? 'post';
    $action = $yamlData['table']['action'] ?? '';

    // Start the form
    $formHtml = "<form method=\"$method\" action=\"$action\">\n";

    foreach ($yamlData['table']['fields'] as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? ucfirst($name);
        $formType = $field['form_type'] ?? 'text';


        $formRequired = $field['form_required'] ?? false;
        $formDefault = $field['form_default'] ?? '';

        // ** give priority to form_required adn form_default, if not exist, fall back to required adn default fields
        // $formRequired = $field['form_required'] ?? $field['required'] ?? false ? 'required' : '';
        // $formDefault = $field['form_default'] ?? $field['default'] ?? '';

        $options = $field['options'] ?? [];

        // Generate the label
        $formHtml .= "  <label for=\"$name\">$label</label>\n";

        $elementTag = '';

        // Generate the input field based on type
        switch ($formType) {
            case 'text':
            case 'password':
            case 'email':
            case 'number':
            case 'date':
            case 'time':
            case 'url':
            case 'color':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <input type=\"$formType\" id=\"$name\" name=\"$name\" value=\"$formDefault\" $required>\n";
                $elementTag = "input";
                break;

            case 'textarea':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <textarea id=\"$name\" name=\"$name\" $required>$formDefault</textarea>\n";
                $elementTag = "textarea";
                break;

            case 'select':
                $formHtml .= "  <select id=\"$name\" name=\"$name\">\n";
                foreach ($options as $option) {
                    $selected = ($formDefault == $option) ? 'selected' : '';
                    $formHtml .= "    <option value=\"$option\" $selected>$option</option>\n";
                }
                $formHtml .= "  </select>\n";
                
                $elementTag = "select";

                break;

            case 'radio':
                foreach ($options as $option) {
                    $checked = ($formDefault == $option) ? 'checked' : '';
                    $formHtml .= "  <input type=\"radio\" id=\"$name-$option\" name=\"$name\" value=\"$option\" $checked>\n";
                    $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                }
                
                $elementTag = "radio";
                
                break;

            case 'checkbox':
                if(!empty($options)) {
                    foreach ($options as $option) {
                        $isChecked = is_array($formDefault) && in_array($option, $formDefault) ? 'checked' : '';
                        $formHtml .= "  <input type=\"checkbox\" id=\"$name-$option\" name=\"{$name}[]\" value=\"$option\" $isChecked>\n";
                        $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                    }
                } else {
                    $isChecked = ($formDefault === "true")? 'checked': '';
                    $formHtml .= "  <input type=\"checkbox\" id=\"$name\" name=\"{$name}\" $isChecked>\n";
                    // $formHtml .= "  <label for=\"$name\">$label</label>\n";
                }

                $elementTag = "checkbox";
                break;

            case 'file':
                $formHtml .= "  <input type=\"file\" id=\"$name\" name=\"$name\">\n";
                
                break;

            case 'hidden':
                $formHtml .= "  <input type=\"hidden\" id=\"$name\" name=\"$name\" value=\"$formDefault\">\n";
                break;

            default:
                // Unsupported form type
                $formHtml .= "  <!-- Unsupported form type: $formType -->\n";
        }

        $formHtml .= "  <br>\n"; // Add a line break after each field
    }

    // Close the form
    $formHtml .= "  <button type=\"submit\">Submit</button>\n";
    $formHtml .= "</form>\n";

    return $formHtml;
}

function generateHTMLForm1($yamlData) {
    $method = $yamlData['table']['method'] ?? 'post';
    $action = $yamlData['table']['action'] ?? '';

    // Start the form
    $formHtml = "<form method=\"$method\" action=\"$action\">\n";

    foreach ($yamlData['table']['fields'] as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? ucfirst($name);
        $formType = $field['form_type'] ?? 'text';


        $formRequired = $field['form_required'] ?? false;
        $formDefault = $field['form_default'] ?? '';

        // ** give priority to form_required adn form_default, if not exist, fall back to required adn default fields
        // $formRequired = $field['form_required'] ?? $field['required'] ?? false ? 'required' : '';
        // $formDefault = $field['form_default'] ?? $field['default'] ?? '';

        $options = $field['options'] ?? [];

        // Generate the label
        $formHtml .= "  <label for=\"$name\">$label</label>\n";

        // Generate the input field based on type
        switch ($formType) {
            case 'text':
            case 'password':
            case 'email':
            case 'number':
            case 'date':
            case 'time':
            case 'url':
            case 'color':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <input type=\"$formType\" id=\"$name\" name=\"$name\" value=\"$formDefault\" $required>\n";
                break;

            case 'textarea':
                $required = $formRequired ? 'required' : '';
                $formHtml .= "  <textarea id=\"$name\" name=\"$name\" $required>$formDefault</textarea>\n";
                break;

            case 'select':
                $formHtml .= "  <select id=\"$name\" name=\"$name\">\n";
                foreach ($options as $option) {
                    $selected = ($formDefault == $option) ? 'selected' : '';
                    $formHtml .= "    <option value=\"$option\" $selected>$option</option>\n";
                }
                $formHtml .= "  </select>\n";
                break;

            case 'radio':
                foreach ($options as $option) {
                    $checked = ($formDefault == $option) ? 'checked' : '';
                    $formHtml .= "  <input type=\"radio\" id=\"$name-$option\" name=\"$name\" value=\"$option\" $checked>\n";
                    $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                }
                break;

            case 'checkbox':
                if(!empty($options)) {
                    foreach ($options as $option) {
                        $isChecked = is_array($formDefault) && in_array($option, $formDefault) ? 'checked' : '';
                        $formHtml .= "  <input type=\"checkbox\" id=\"$name-$option\" name=\"{$name}[]\" value=\"$option\" $isChecked>\n";
                        $formHtml .= "  <label for=\"$name-$option\">$option</label>\n";
                    }
                } else {
                    $isChecked = ($formDefault === "true")? 'checked': '';
                    $formHtml .= "  <input type=\"checkbox\" id=\"$name\" name=\"{$name}\" $isChecked>\n";
                    // $formHtml .= "  <label for=\"$name\">$label</label>\n";
                }
                break;

            case 'file':
                $formHtml .= "  <input type=\"file\" id=\"$name\" name=\"$name\">\n";
                break;

            case 'hidden':
                $formHtml .= "  <input type=\"hidden\" id=\"$name\" name=\"$name\" value=\"$formDefault\">\n";
                break;

            default:
                // Unsupported form type
                $formHtml .= "  <!-- Unsupported form type: $formType -->\n";
        }

        $formHtml .= "  <br>\n"; // Add a line break after each field
    }

    // Close the form
    $formHtml .= "  <button type=\"submit\">Submit</button>\n";
    $formHtml .= "</form>\n";

    return $formHtml;
}


// creeate field definition according to yaml table
// return string with SQL field definition
function createFieldDefinition($field, bool $includeFieldName = false) {
    // Predefined mapping for @ values
    $internalTypes = [
        "@cdate" => "DATETIME DEFAULT 'CURRENT_TIMESTAMP'",
        "@cuser" => "CHAR(32) NOT NULL",
        "@guid" => "CHAR(36) NOT NULL",
        "@delete" => "DATETIME DEFAULT NULL",
    ];
    
    $name = $field['name'];
    $type = $field['type'];

    $definition = null;

    $required = false;
    $default = '';
    $onUpdate = '';

    // echo ("Processing field: " . print_r($field, 1));

    // Check if the type starts with '@'
    if (str_starts_with($type, '@')) {
        $definition = $internalTypes[$type] ?? 'VARCHAR(255)';
    } else {
        // $dbType = $field['type'] ?? 'VARCHAR(255)';

        $required = $field['required'] ?? null;

        $default = $field['default'] ?? null;
        // check for 'default: null'
        // if(array_key_exists('default', $field) && is_null($default))$default = "null";

        // echo("def: $type default: <$default>\n");
        if((strtolower($type) === 'boolean') && (isset($field['default'])))
            $default = $field['default'] ? "1" : "0";

        $required = $required ? " NOT NULL " : '';
        if(!is_null($default))
            $default = " DEFAULT " . "'" . addslashes($default) . "'";
            // $default = " DEFAULT " . ($default==="null" ? $default : "'" . addslashes($default) . "'");

        $onUpdate = isset($field['on_update']) ? " ON UPDATE " . $field['on_update'] : null;

        $definition = "$type$required$default$onUpdate";
    }


    if($includeFieldName)$ret = "`$name` "; else $ret = "";
    $ret .= $definition;
    // $columns[] = "`$name` $dbType $required $dbDefault $dbOnUpdate";
    return($ret);
}


// Example usage
// $yamlData = yaml_parse_file('form_structure.yaml');
// echo generateHTMLForm($yamlData);

function generateSQLTable($yamlData) {
    // echo("generating table for: " . print_r($yamlData, 1));

    $tableName = $yamlData['table']['name'] ?? 'default_table';
    
    $engine = $yamlData['table']['engine'] ?? 'InnoDB';
    $charset = $yamlData['table']['charset'] ?? "utf8mb4";
    $collate = $yamlData['table']['collate'] ?? "utf8mb4_unicode_ci";
    
    
    $columns = [];

    $columns[] = "`id` INTEGER NOT NULL AUTO_INCREMENT UNIQUE";

    foreach ($yamlData['table']['fields'] as $field) {

        // use specialized function to create field definition
        $columns[] = createFieldDefinition( $field, true );
    }
    
    $columnsSql = implode(",\n  ", $columns);
    $sql = "CREATE TABLE `$tableName` (
  $columnsSql,

  PRIMARY KEY (id) 
) ENGINE=$engine DEFAULT CHARSET=$charset COLLATE=$collate;\n";
    
    return $sql;
}

// Example usage
// $yamlData = yaml_parse_file('form_structure.yaml');
// echo generateSQLTable($yamlData);




function syncTableWithYAML($yamlData, $pdo) {
    $tableName = $yamlData['table']['name'] ?? 'default_table';

    // Fetch existing table structure
    $existingColumns = [];
    $noexist = 0;

    try {
        $stmt = $pdo->query("DESCRIBE `$tableName`");

    } catch (PDOException $e){
        echo("/* Table `$tableName` does not exist in database */\n");
        if($e->errorInfo[1] === 1146)$noexist = 1;
    }

    if($noexist)return [];

    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Ignore DEFAULT_GENERATED token in the Extra field
            if (isset($row['Extra'])) {
                $row['Extra'] = str_replace('DEFAULT_GENERATED', '', $row['Extra']);
                if(!strlen($row['Extra']))unset($row['Extra']);
            }
            $existingColumns[$row['Field']] = $row;
        }
    }

    $sql = [];

    $fields = $yamlData['table']['fields'];

    foreach ($fields as $index => $field) {
        $name = $field['name'];
        $columnDefinition = createFieldDefinition($field, false);

        if (isset($existingColumns[$name])) {
            // Check if column needs an update
            $existing = $existingColumns[$name];

            // booleans are handled as tinyint(1)
            if($existing['Type'] === 'tinyint(1)') {
                $existing['Type'] = 'boolean';
                if(isset($existing['Default']) && ($existing['Default'] == 0))$existing['Default'] = "0";
            }

            $existingDefinition = trim($existing['Type']) . ($existing['Null'] === 'NO' ? ' NOT NULL' : '') .
                ($existing['Default'] !== null ? " DEFAULT '" . trim($existing['Default']) . "'" : '') .
                (isset($existing['Extra']) ?  " " . trim($existing['Extra']) : '');

/*
            $existingDefinition = trim($existing['Type']) . ' ' .
                ($existing['Null'] === 'NO' ? 'NOT NULL' : '') .
                ($existing['Default'] !== null ? " DEFAULT '" . $existing['Default'] . "'" : '') .
                (isset($existing['Extra']) ? " " . trim($existing['Extra']) : '');
*/

            if (strtolower(trim($existingDefinition)) !== strtolower(trim($columnDefinition))) {
                // Alter column
                $sql[] = "/* old definition $existingDefinition */";
                $sql[] = "/* new definition $columnDefinition */";

                $sql[] = "ALTER TABLE `$tableName` MODIFY `$name` $columnDefinition";
            }
        } else {
            // Add new column in correct position using AFTER
            $afterClause = $index > 0 ? "AFTER `" . $fields[$index - 1]['name'] . "`" : "FIRST";

            $sql[] = "ALTER TABLE `$tableName` ADD `$name` $columnDefinition $afterClause";
        }
    }

    // Drop columns not in YAML
    foreach ($existingColumns as $existingName => $existingColumn) {
        $found = false;
        foreach ($fields as $field) {
            if ($field['name'] === $existingName) {
                $found = true;
                break;
            }
        }

        if (!$found && ($existingName !== 'id')) {
            $sql[] = "ALTER TABLE `$tableName` DROP `$existingName`";
        }
    }

    return implode(";\n", $sql);
}




function syncTableWithYAML1($yamlData, $pdo) {
    $tableName = $yamlData['table']['name'] ?? 'default_table';

    // Fetch existing table structure
    $existingColumns = [];
    $noexist = 0;
    try {
        $stmt = $pdo->query("DESCRIBE `$tableName`");

    } catch (PDOException $e){
        echo("/* Table `$tableName` does not exist in database */\n");
        if($e->errorInfo[1] === 1146)$noexist = 1;
    }

    if($noexist)return [];

    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[$row['Field']] = $row;
        }
    }
    // print_r($existingColumns);

    $sql = [];

    foreach ($yamlData['table']['fields'] as $field) {
        $name = $field['name'];
/*

        $dbDefault = '';
        $dbOnUpdate = '';
        $dbRequired = false;
        switch($field['type']) {
            case "@guid":
                $dbType = 'CHAR(36)';
                $dbRequired = true;
                break;
            case "@cdate":
                $dbType = 'DATETIME';
                $dbDefault = "DEFAULT 'CURRENT_TIMESTAMP'";
                break;
            case "@cuser":
                $dbType = 'CHAR(32)';
                $dbRequired = true;
                break;
            default:
                $dbType = trim($field['db_type'] ?? $field['type'] ?? 'VARCHAR(255)');
                $dbRequired = trim($field['db_required'] ?? $field['required'] ?? false);
                $dbDefault = trim(isset($field['db_default']) ? "DEFAULT '" . $field['db_default'] . "'" : '');
                $dbOnUpdate = trim(isset($field['db_on_update']) ? "ON UPDATE " . $field['db_on_update'] : '');
                break;
        }

        if(($dbType === 'boolean')) {
            // echo("Default: <$dbDefault>  db_default: " . (isset($field['db_default']) ? "<".$field['db_default'].">" : "<not set>") ."\n");
            if(isset($field['db_default']) && (empty($field['db_default'])))
                $dbDefault = "DEFAULT '0'";
        } 

        $required = $dbRequired ? ' NOT NULL ' : ' ';
        $columnDefinition = "$dbType$required$dbDefault $dbOnUpdate";
*/
        $columnDefinition = createDBFieldDefinition( $field, false );

        if (isset($existingColumns[$name])) {
            // Check if column needs an update
            $existing = $existingColumns[$name];

            // booleans are handled as tinyint(1)
            if($existing['Type'] === 'tinyint(1)') {
                $existing['Type'] = 'boolean';
                if(isset($existing['Default']) && ($existing['Default'] == 0))$existing['Default'] = "0";
            }
            // remove DEFAULT_GENERATED flag
            if(isset($existing['Extra'])) {
                // echo("extra: " . $existing['Extra'] . "\n");
                $existing['Extra'] = trim(str_replace('DEFAULT_GENERATED', '', $existing['Extra']));
                if(!strlen($existing['Extra']))unset($existing['Extra']);
            }

            $existingDefinition = trim($existing['Type']) . ($existing['Null'] === 'NO' ? ' NOT NULL' : '') .
                ($existing['Default'] !== null ? " DEFAULT '" . trim($existing['Default']) . "'" : '') .
                (isset($existing['Extra']) ?  " " . trim($existing['Extra']) : '');

            // echo("existing  $name => $existingDefinition\n");

            if (strtolower(trim($existingDefinition)) !== strtolower(trim($columnDefinition))) {
                // Alter column
                // $pdo->exec("ALTER TABLE `$tableName` MODIFY `$name` $columnDefinition");
                $sql[] = "/* old definition $existingDefinition */";
                $sql[] = "/* new definition $columnDefinition */";
                $sql[] = "ALTER TABLE `$tableName` MODIFY `$name` $columnDefinition;";
            }
        } else {
            // Add new column
            // $pdo->exec("ALTER TABLE `$tableName` ADD `$name` $columnDefinition");
            $sql[] = "ALTER TABLE `$tableName` ADD `$name` $columnDefinition;";
        }
    }

    // Drop columns not in YAML
    foreach ($existingColumns as $existingName => $existingColumn) {
        $found = false;
        foreach ($yamlData['table']['fields'] as $field) {
            if ($field['name'] === $existingName) {
                $found = true;
                break;
            }
        }

        if (!$found && ($existingName !== 'id')) {
            // $pdo->exec("ALTER TABLE `$tableName` DROP `$existingName`");
            $sql[] = "ALTER TABLE `$tableName` DROP `$existingName`;";
        }
    }
    return implode(";\n", $sql);
}

function generateClassCode($yamlData) {
    global $options;
    $fldnames = array();
    $flddef = array();
  
    $table = $yamlData['table'];

    foreach($table['fields'] as $fld) {
        $fldnames[] = $fld['name'];
        $flddef[] = $fld['type'];
    }
  
    // print_r($fldnames);
    // print_r($flddef);
    // exit();

          ob_start();
          mlog('<?php');
          mlog('// class ' . $table['class']);
          // mlog("require_once(__DIR__ . \"/../../fw/db/dbal.php\");\n");
          $ext = 'dbAbstractEntityClass';
          if(isset($table['extends']))$ext = $table['extends'];
          if(isset($options['extends-class']))$ext = $options['extends-class'];
  
          mlog('class ' . $table['class'] . ' extends ' . $ext . ' {');
          
  
              foreach($table['fields'] as $fld) {
                  mlog('  private $' . $fld['name'] . ';');
              }
  
              
              mlog("  function __construct(\$adata = array() ) {
                          parent::__construct('".$table['name']."', \$adata);
                          \$this->loadFields( \$adata );
                  }");
  
              mlog("  static function sgetById(int \$aid) {
                  global \$AppDBConnection;
  
                      if(!\$AppDBConnection->isConnected()) {
                          if(!\$AppDBConnection->Connect()) {
                              echo 'Could not connect to database';
                              return (null);
                          }
                      }
              
                      \$sql = \"SELECT * FROM " . $table['name'] . " WHERE id=:id\";
                      \$st = \$AppDBConnection->getConnection()->prepare( \$sql );
                      \$st->bindValue(\":id\", \$aid, PDO::PARAM_INT);
                      \$st->execute();
                      \$row = \$st->fetch();
              
                      if(\$row) {
                          \$rclass = new " . $table['class'] . "( \"" . $table['name'] . "\");
                          \$rclass->loadFields( \$row );
                          return \$rclass;
                      } else return (null);
              }");
  
              mlog("  static function sgetAll() {
                  global \$AppDBConnection;
  
                  if(!\$AppDBConnection->isConnected()) {
                      if(!\$AppDBConnection->Connect()) {
                          echo 'Could not connect to database';
                          return (null);
                      }
                  }
          
                  \$sql = \"SELECT * FROM " . $table['name'] . ";\";
                  \$st = \$AppDBConnection->getConnection()->prepare( \$sql );
                  \$st->execute();
          
                  \$list = array();
          
                  while( \$row = \$st->fetch() ) {
                      \$rclass = new " . $table['class'] . "( \"" . $table['name'] . "\" );
                      \$rclass->loadFields( \$row );
                      \$list[] = \$rclass;
                  }
          
                  return (\$list);
          
              }");
  
              mlog("function loadFields(\$adata) {
        parent::loadFields(\$adata);");
  
              foreach($table['fields'] as $fld) {
                  mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
              }
  
              mlog("}\n");
  
              mlog("function getFields() {
                \$resp = array();
                \$resp = array_merge(\$resp, parent::getFields());");
  
  
              foreach($table['fields'] as $fld) {
                  if($fld['name'] != 'id') {
                      mlog("              \$resp = array_merge(\$resp, ['" .$fld['name']. "' => \$this->".$fld['name']."]);");
                  }
              }
              // foreach($this->fields as $fld) {
                  // mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
              // }
  
              mlog("      return \$resp;\n}\n");
  
              mlog("function getAllFields() {
                \$resp = array();
                \$resp = array_merge(\$resp, parent::getAllFields());");
  
  
              foreach($table['fields'] as $fld) {
                  if($fld['name'] != 'id') {
                      mlog("              \$resp = array_merge(\$resp, ['" .$fld['name']. "' => \$this->".$fld['name']."]);");
                  }
              }
              // foreach($this->fields as $fld) {
                  // mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
              // }
  
              mlog("      return \$resp;\n}\n");
  
  
  
  
              // emit setters and getters
              foreach($table['fields'] as $fld) {
                  mlog('  function set'.$fld['name'].'( $a'.$fld['name'].' ) { $this->'.$fld['name'].' = $a'.$fld['name'].'; }');
                  mlog('  function get'.$fld['name'].'() { return ( $this->'.$fld['name']. '); }');
  
              }
  
              // insert()
              mlog("    function insert() {
          if(\$this->id != null) {
              echo 'Trying to insert() a record that already exists';
              return (null);
          }
  
          if(!\$this->getConnection()->isConnected()) {
              if(!\$this->getConnection()->Connect()) {
                  echo 'Could not connect to database';
                  return (null);
              }
          }
          ");
              
          mlog("\$sql = \"INSERT INTO ".$table['name'] ." ( ", false);
  
          $str = implode(',', $fldnames);
          mlog($str . " ) VALUES ( " , false );
          
          $str = implode(',', preg_filter('/^/', ':', $fldnames));
          mlog($str . " );\";");
          
          // echo "SQL: $sql \n";
          mlog("\$st = \$this->getConnection()->getConnection()->prepare ( \$sql );");
  
          foreach($table['fields'] as $fld) {
              mlog("\$st->bindValue( \":".$fld['name']."\", \$this->".$fld['name'].", PDO::PARAM_STR );");
          }
          mlog("\$st->execute();");
          
  //         echo "Inserted record\n";
          mlog("\$this->setid( \$this->getConnection()->getConnection()->lastInsertId() );");
          mlog("}");
  
  
          // update()
          mlog("
          function update() {
              if(\$this->id == null) {
                  echo 'Trying to update() a record that does not exist';
                  return (null);
              }
      
              if(!\$this->getConnection()->isConnected()) {
                  if(!\$this->getConnection()->Connect()) {
                      echo 'Could not connect to database';
                      return (null);
                  }
              }
                  
              \$sql = \"UPDATE ".$table['name'] ." SET ", false);
              
  
              $eq = array();
              foreach($table['fields'] as $fld) {
                  $eq[] = $fld['name'] . '=:' . $fld['name'];
                      // mlog($fld['name'] . "=:".$fld['name'].",", false);
              }
              $str = implode(',', $eq);
              mlog($str , false );
  
              mlog(" WHERE id=:id\";");
                  
              mlog("
              \$st = \$this->getConnection()->getConnection()->prepare ( \$sql );
              ");
      
              foreach($table['fields'] as $fld) {
                  mlog("          \$st->bindValue( \":".$fld['name']."\", \$this->".$fld['name'].", PDO::PARAM_STR );");
              }
              mlog("          \$st->bindValue( \":"."id"."\", \$this->"."id".", PDO::PARAM_INT );");
              mlog("          \$st->execute();
          }
              ");
  
  
          // delete()
  
          mlog("
          function delete() {
          if(\$this->id == null) {
              echo 'Trying to delete() an empty record';
              return (null);
          }
          
          if(!\$this->getConnection()->isConnected()) {
              if(!\$this->getConnection()->Connect()) {
                  echo 'Could not connect to database';
                  return (null);
              }
          }
          ");
      
          mlog("\$sql = \"DELETE FROM " . $table['name'] . " WHERE id = :id;\";");
          mlog("\$st = \$this->getConnection()->getConnection()->prepare(\$sql);");
          mlog("\$st->bindValue( \":"."id"."\", \$this->"."id".", PDO::PARAM_INT );");
          mlog("\$st->execute();");
          mlog("
          return (true);
          }
      }
      ");
  
          return( ob_get_clean() );
  
      }




// function init() {

    // $yml = yaml_parse_file('table-structure.yml');
    // $html = generateHTMLForm($yml);
    // echo("$html\n");

    // $sql = generateSQLTable($yml);
    // echo("$sql\n");



    // Database connection (replace with your credentials)
    // require('../config/db.php');
    // $host = DB_HOST;
    // $dbname = DB_NAME;
    // $user = DB_USER;
    // $pass = DB_PASS;
    // try {
    //     $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    //     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // } catch (PDOException $e) {
    //     die("Database connection failed: " . $e->getMessage());
    // }


    // $sqldiff = syncTableWithYAML($yml, $pdo);
    // echo("$sqldiff\n");
// }
