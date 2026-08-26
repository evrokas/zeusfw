<?php

// Normalizes one view/group's ordered field-entry list (yaml: a plain
// field-name string, or a {name, label} map to override that field's
// display label for this view/group only) into a consistent
// ['name' => ..., 'label' => ...|null] shape. Shared by table_view and
// form_view -- both use the exact same field-entry convention. Field
// names are copied through as-is and validated later, at render time,
// against that form's own `inputs` list (WebForms.php/FormElement.php) --
// not here, since this function has no knowledge of rendering concerns.
function zeusfw_normalize_view_field_entries($entries) {
    $normalized = [];
    foreach ((array) $entries as $fieldEntry) {
        if (is_string($fieldEntry)) {
            $normalized[] = ['name' => $fieldEntry, 'label' => null];
        } else if (!empty($fieldEntry['name'])) {
            $normalized[] = [
                'name' => $fieldEntry['name'],
                'label' => $fieldEntry['label'] ?? null,
            ];
        }
    }
    return $normalized;
}

// Normalizes one form_view group (or a view's own top-level content, which
// shares the same shape minus `buttons`): optional `fields` (see above)
// and optional `groups`, an ordered list of nested sub-groups sharing this
// same shape -- recursive, so nesting depth is unbounded. Mixed content
// (both `fields` and `groups` on the same node) is preserved as-is; render
// time (FormElement.php) walks `fields` then `groups`, in that order.
function zeusfw_normalize_form_view_group($group) {
    $normalized = [
        'label' => $group['label'] ?? null,
        'fields' => zeusfw_normalize_view_field_entries($group['fields'] ?? []),
        'groups' => [],
    ];

    foreach ((array) ($group['groups'] ?? []) as $subGroup) {
        $normalized['groups'][] = zeusfw_normalize_form_view_group($subGroup);
    }

    return $normalized;
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
        $dynamicOptions = $field['dynamic_options'] ?? [];
        $staticOptions = $field['static_options'] ?? [];
        $attributes = $field['attributes'] ?? [];


        $inputElement = [
            'label' => $label,
            'type' => $formType,
            'name' => $name,
            'required' => $formRequired,
            'default' => $formDefault,
            'options' => $options,
            'dynamic_options' => $dynamicOptions,
            'static_options' => $staticOptions,
            'attributes' => $attributes
        ];

        $formArray['inputs'][] = $inputElement;
    }

    // Note: `form: -> buttons:` is no longer read here -- buttons now live
    // exclusively per-view, under the new document-root `form_view:` key
    // (below), since different views may reasonably want different
    // actions. There is deliberately no fallback to a form-level
    // `buttons:` any more (a form not yet migrated to `form_view` simply
    // renders with none -- see generateHTMLForm()'s own fallback comment
    // in FormElement.php).

    // Optional per-view results-table column lists -- a document-ROOT
    // `table_view:` key (promoted here from an earlier `form: -> table:`
    // location, and renamed, specifically to avoid any confusion with
    // this same yaml file's OWN unrelated document-root `table:` key,
    // which describes the DB schema and is never read here). Entirely
    // additive: a form with no `table_view:` block gets no 'table_view'
    // key in $formArray at all, and WebForms.php's renderFormResults()
    // falls back to its pre-existing "show every input" behavior whenever
    // that key is absent.
    //
    // `default` is a reserved key naming which view applies when a caller
    // doesn't request one explicitly -- every other key is a view name,
    // whose value is an ordered field-entry list (see
    // zeusfw_normalize_view_field_entries() above).
    if (!empty($yamlData['table_view'])) {
        foreach ($yamlData['table_view'] as $viewName => $viewValue) {
            if ($viewName === 'default') {
                $formArray['table_view']['default'] = $viewValue;
                continue;
            }

            $formArray['table_view'][$viewName] = zeusfw_normalize_view_field_entries($viewValue);
        }
    }

    // Optional named views of the CREATE/EDIT form itself -- a document-
    // ROOT `form_view:` key, mirroring `table_view:` above (default +
    // named views), but for the form rather than its results table. Each
    // view shares `zeusfw_normalize_form_view_group()`'s shape (`fields`/
    // `groups`, recursive/nestable) for its own top-level content, plus a
    // `buttons` list (the exact same per-button shape `form: -> buttons:`
    // used to carry, just relocated: a form with a `form_view` MUST
    // define buttons on (at least) its default view, or its create-form
    // renders with none at all -- see FormElement.php's generateHTMLForm()).
    if (!empty($yamlData['form_view'])) {
        foreach ($yamlData['form_view'] as $viewName => $viewValue) {
            if ($viewName === 'default') {
                $formArray['form_view']['default'] = $viewValue;
                continue;
            }

            $view = zeusfw_normalize_form_view_group($viewValue);

            $buttons = [];
            foreach ((array) ($viewValue['buttons'] ?? []) as $button) {
                $buttonType = $button['type'] ?? 'button';
                $buttonLabel = $button['label'] ?? 'Button';
                $buttonValue = $button['value'] ?? $buttonLabel;
                $buttonAction = $button['action'] ?? '';
                // button_type defaults to the button's own `type:` (not
                // hardcoded to "submit") -- see the historical note this
                // replaced, in git blame, for why that mattered: every
                // button (Submit, Reset, Cancel alike) used to render
                // type="submit" regardless of its own declared type.
                $buttonButType = $button['button_type'] ?? $buttonType;

                $buttons[] = [
                    'type' => $buttonType,
                    'label' => $buttonLabel,
                    'value' => $buttonValue,
                    'action' => $buttonAction,
                    'button_type' => $buttonButType,
                ];
            }
            $view['buttons'] = $buttons;

            $formArray['form_view'][$viewName] = $view;
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
        // echo("field: " . print_r($field, 1));

        $name = $field['name'];
        $label = $field['label'] ?? ucfirst($name);
        $formType = $field['form_type'] ?? 'text';


        $formRequired = $field['form_required'] ?? false;
        $formDefault = $field['form_default'] ?? '';

        // ** give priority to form_required adn form_default, if not exist, fall back to required adn default fields
        // $formRequired = $field['form_required'] ?? $field['required'] ?? false ? 'required' : '';
        // $formDefault = $field['form_default'] ?? $field['default'] ?? '';

        $options = $field['options'] ?? [];
        $dynamicOptions = $field['dynamic_options'] ?? [];
        $staticOptions = $field['static_options'] ?? [];


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
                if(!empty($options)) {
                    foreach ($options as $option) {
                        $selected = ($formDefault == $option) ? 'selected' : '';
                        $formHtml .= "    <option value=\"$option\" $selected>$option</option>\n";
                    }
                } else
                if(!empty($staticOptions)) {
                    $selected = ($formDefault == $option) ? 'selected' : '';
                    echopre("staticOptions: " . print_r($staticOptions, 1));
                    exit;
                } else
                if(!empty($dynamicOptions)) {
                    echopre("dynamicOptions: " . print_r($staticOptions, 1));
                    exit;
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


// creeate field definition according to yaml table
// return string with SQL field definition
function createFieldDefinition($field, bool $includeFieldName = false) {
    // Predefined mapping for @ values
    $internalTypes = [
        "@cdate" => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        "@cuser" => "CHAR(32) NOT NULL",
        "@guid" => "CHAR(36) NOT NULL",
        "@delete" => "DATETIME DEFAULT NULL",
        "@json" => "JSON NOT NULL DEFAULT json_array()",
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
            $default = " DEFAULT " . "" . addslashes($default) . "";
        else
            $default = (!$required) ? " DEFAULT NULL " : "";
            // $default = "";  //(!$required) ? " DEFAULT NULL " : "";

            /* CHECK FOR ERROR IN DEFINITION */

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

    // echo("yamlData : " . print_r($yamlData, 1));
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
            $existingDefinition = trim($existing['Type']) . 
                            ($existing['Null'] === 'NO' ? ' NOT NULL' : '') .
                // ($existing['Default'] !== null ? " DEFAULT " . trim($existing['Default']) . "b" : 'a') .
                ($existing['Default'] !== null ? " DEFAULT " . trim($existing['Default']) . "" : (($existing['Null'] === "YES")? " DEFAULT NULL ":'')) .

            // $existingDefinition = trim($existing['Type']) . ($existing['Null'] === 'NO' ? ' NOT NULL' : '') .
                // ($existing['Default'] !== null ? " DEFAULT " . trim($existing['Default']) . "" : ($existing['Null'] === 'YES'?" DEFAULT NULL ":'')) .

                (isset($existing['Extra']) ?  " " . trim($existing['Extra']) : '');

                /* CHECK FOR ERROR IN DEFINITION */
/*
            $existingDefinition = trim($existing['Type']) . ' ' .
                ($existing['Null'] === 'NO' ? 'NOT NULL' : '') .
                ($existing['Default'] !== null ? " DEFAULT '" . $existing['Default'] . "'" : '') .
                (isset($existing['Extra']) ? " " . trim($existing['Extra']) : '');
*/

            if (strtolower(trim($existingDefinition)) !== strtolower(trim($columnDefinition))) {
                // Alter column
                // echo("existing `$name`: " . print_r($existing, 1));

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
            $sql[] = "ALTER TABLE `$tableName` DROP `$existingName`;";
        }
    }

    return implode(";\n", $sql);
}


// use ZETEM templates to create code
function generateClassCode($yamlData) {
    global $options;
    $functionNames = [
        "constructor",
        "sgetById",
        "sgetAll",
        "table",
        "loadFields",
        "getFields",
        "getAllFields",
        "setget_functions",
        "insert",
        "update",
        "delete"
    ];

    // function names
    // constructor sgetById sgetAll table loadFields getFields getAllFields setput_functions insert update delete

    include __DIR__ . "/../templates/ZETEMTemplate.php";
    Renderer::init(__DIR__ . '/templates', false, __DIR__ . '/cache/', false, "php");


    $fldnames = array();
    $flddef = array();

    $table = $yamlData['table'];

    foreach($table['fields'] as $fld) {
        $fldnames[] = $fld['name'];
        $flddef[] = $fld['type'];
    }


    $args = [
            'className' => $table['class'],
            'tableName' => $table['name'],
            'fieldsList' => array_map(function($fld){return $fld['name'];}, $table['fields']),
            // 'templateName' => 'class.zetem',
            // 'templatePath' => 'class.zetem'
    ];

    $funcdefs = [];

    foreach($functionNames as $fname) {
        $funcdefs[ $fname ] = Renderer::render($fname.'.zetem', $args);
    }

    foreach($functionNames as $fname) {
        $args[ $fname ] = $funcdefs[ $fname ];
    }

    ob_start();

    $classText = Renderer::render('class.zetem',
        $args
        // [
        //     'className' => $table['class'],
        //     'tableName' => $table['name'],
        //     'fieldsList' => array_map(function($fld){return $fld['name'];}, $table['fields']),
        //     'templateName' => 'class.zetem',
        //     'templatePath' => 'class.zetem',
        // ]
    );

    mlog($classText);

    // echo($classText);
    return ob_get_clean();
}

// old (obsolete) code just dump class code
function generateClassCode0($yamlData) {
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
                      \$sql = \"SELECT * FROM " . $table['name'] . " WHERE id=:id\";
                      \$st = dbConnection::getConnection()->prepare( \$sql );
                      \$st->bindValue(\":id\", \$aid, PDO::PARAM_INT);
                      \$st->execute();
                      \$row = \$st->fetch();
              
                      if(\$row) {
                          \$rclass = new " . $table['class'] . "( \"" . $table['name'] . "\");
                          \$rclass->loadFields( \$row );
                          return \$rclass;
                      } else return (null);
              }");
  
              mlog("  static function sgetAll(\$whereClause = null, \$limit = null) {
                  \$sql = \"SELECT * FROM " . $table['name'] . "\";
                    if(\$whereClause)
                        \$sql = \$sql . \" WHERE \" . \$whereClause;

                    if(\$limit)
                        \$sql = \$sql . \" LIMIT \" . \$limit;

                  \$st = dbConnection::getConnection()->prepare( \$sql );
                  \$st->execute();
          
                  \$list = array();
          
                  while( \$row = \$st->fetch() ) {
                      \$rclass = new " . $table['class'] . "( \"" . $table['name'] . "\" );
                      \$rclass->loadFields( \$row );
                      \$list[] = \$rclass;
                  }
          
                  return (\$list);
          
              }");
  
                // static table()
                mlog('static function table() { return "' . $table['name'] . '";}');


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
  
          ");
              
          mlog("\$sql = \"INSERT INTO ".$table['name'] ." ( ", false);
  
          $str = implode(',', $fldnames);
          mlog($str . " ) VALUES ( " , false );
          
          $str = implode(',', preg_filter('/^/', ':', $fldnames));
          mlog($str . " );\";");
          
          // echo "SQL: $sql \n";
          mlog("\$st = \$this->getConnection()->prepare ( \$sql );");
  
          foreach($table['fields'] as $fld) {
              mlog("\$st->bindValue( \":".$fld['name']."\", \$this->".$fld['name'].", PDO::PARAM_STR );");
          }
          mlog("\$res = \$st->execute();");
          
  //         echo "Inserted record\n";
          mlog("\$this->setid( \$this->getConnection()->lastInsertId() );");
          mlog("return \$res;");
          mlog("}");
  
  
          // update()
          mlog("
          function update() {
              if(\$this->id == null) {
                  echo 'Trying to update() a record that does not exist';
                  return (null);
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
              \$st = \$this->getConnection()->prepare ( \$sql );
              ");
      
              foreach($table['fields'] as $fld) {
                  mlog("          \$st->bindValue( \":".$fld['name']."\", \$this->".$fld['name'].", PDO::PARAM_STR );");
              }
              mlog("          \$st->bindValue( \":"."id"."\", \$this->"."id".", PDO::PARAM_INT );");
              mlog("          return \$st->execute();
          }
              ");
  
  
          // delete()
  
          mlog("
          function delete() {
          if(\$this->id == null) {
              echo 'Trying to delete() an empty record';
              return (null);
          }
          
          if(!\$this->isdbConnected()) {
              if(!\$this->getConnection()->Connect()) {
                  echo 'Could not connect to database';
                  return (null);
              }
          }
          ");
      
          mlog("\$sql = \"DELETE FROM " . $table['name'] . " WHERE id = :id;\";");
          mlog("\$st = \$this->getConnection()->prepare(\$sql);");
          mlog("\$st->bindValue( \":"."id"."\", \$this->"."id".", PDO::PARAM_INT );");
          mlog(" return \$st->execute();");
          mlog("
          //return (true);
          }
      }
      ");
  
          return( ob_get_clean() );
      }


function form_load($yData) {
    echo("loading form `{$yData['form']['name']}`\n");
	print_r("yData: " . print_r($yData['form'], 1));

    $formArray = generateHTMLFormArray($yData);

    print_r($formArray);
    // $serializedArray = serialize( $formArray );

    // print_r($serializedArray);
    // echo("\n---\n");
    // $s = JsonSerializable()
    $jsonArray = json_encode($formArray);
    // print_r($jsonArray);
    
    if(isset($yData['table']['class'])) {
        // class is set
        $tableName = $yData['table']['name'];
        $className = $yData['table']['class'];
        $formName = $yData['form']['name'];

        // echo("class name $className\n");


        include_once(DIR::$app . '/config/db.php');
        // echo("DB_HOST " . DB_HOST . "\tDB_NAME: " . DB_NAME . "\n");
        // $host = DB_HOST;
        // $dbname = DB_NAME;
        // $user = DB_USER;
        // $pass = DB_PASS;

        // include webform sources
        
        require(DIR::$fw . "/bootstrap.php");
        
        
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        dbConnection::Connect();

        // try {
        //     $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        //     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // } catch (PDOException $e) {
        //     die("Database connection failed: " . $e->getMessage());
        // }

        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formName]);

        // echo("Found forms with name `$formName`\n");
        // print_r($dbForm);

        if(empty($dbForm)) {
            echo("No forms found in database\n");
            
            $formClass = new webFormsClass([
                'guid' => mguid(),
                'cdate' => date('Y-m-d H:i:s'),
                'cuser' => 'admin',
                'form_name' => $formName,
                'form_class' => $className,
                'data' => $jsonArray
            ]);

            $formClass->insert();

            print_r($formClass);
            echo("Form added to database\n");

        } else {
            if(count($dbForm)>1) {
                echo("Found more than 1 forms in database. You must fix it manually!\n");
                exit(-1);
            }

            echo("Found form in database\n");
            print_r($dbForm);

            // $formClass = new webFormsClass( $dbForm[0]->getAllFields() );
            $formClass = $dbForm[0];
            
            $formClass->setguid( mguid() );
            $formClass->setcdate( date('Y-m-d H:i:s'));
            $formClass->setcuser( 'admin' );
            $formClass->setform_name( $formName );
            $formClass->setform_class( $className );
            $formClass->setdata( $jsonArray );

            print_r($formClass);

            $formClass->update();

            echo ("Form in database is updated\n");
        }


    } else {
        echo("Database table has no matching class to it\n");
        exit(-1);
    }
}

function form_view($yData) {
    echo("loading form `{$yData['form']['name']}`\n");
	
    // $formArray = generateHTMLFormArray($yData);

    // print_r($formArray);
    // $serializedArray = serialize( $formArray );

    // print_r($serializedArray);
    // echo("\n---\n");
    // $s = JsonSerializable()
    // $jsonArray = json_encode( $formArray);
    // print_r($jsonArray);
    
    if(isset($yData['table']['class'])) {
        // class is set
        $tableName = $yData['table']['name'];
        $className = $yData['table']['class'];
        $formName = $yData['form']['name'];

        // echo("class name $className\n");


        include_once(DIR::$app . '/config/db.php');
        // echo("DB_HOST " . DB_HOST . "\tDB_NAME: " . DB_NAME . "\n");
        // $host = DB_HOST;
        // $dbname = DB_NAME;
        // $user = DB_USER;
        // $pass = DB_PASS;

        // include webform sources
        
        require(DIR::$fw . "/bootstrap.php");
        
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        dbConnection::Connect();

        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formName]);

        echo("Found forms with name `$formName`\n");
        print_r($dbForm);

        if(!empty($dbForm)) {
            $arr = json_decode( $dbForm[0]->getdata(), true );
            print_r($arr);
        }
    } else {
        echo("Database table has no matching class to it\n");
        exit(-1);
    }
}

function form_view_html($yData) {
    // echo("loading form `{$yData['form']['name']}`\n");
	
    // $formArray = generateHTMLFormArray($yData);

    // print_r($formArray);
    // $serializedArray = serialize( $formArray );

    // print_r($serializedArray);
    // echo("\n---\n");
    // $s = JsonSerializable()
    // $jsonArray = json_encode( $formArray);
    // print_r($jsonArray);
    
    if(isset($yData['table']['class'])) {
        // class is set
        $tableName = $yData['table']['name'];
        $className = $yData['table']['class'];
        $formName = $yData['form']['name'];

        // echo("class name $className\n");


        include_once(DIR::$app . '/config/db.php');
        // echo("DB_HOST " . DB_HOST . "\tDB_NAME: " . DB_NAME . "\n");
        // $host = DB_HOST;
        // $dbname = DB_NAME;
        // $user = DB_USER;
        // $pass = DB_PASS;

        // include webform sources
        
        require(DIR::$fw . "/bootstrap.php");
        
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        dbConnection::Connect();

        global $kernel;
        $kernel = new Kernel(['MAKER_INVOKE' => true, 'PHP_SELF' => __FILE__, 'SCRIPT_FILENAME' => __FILE__], DIR::$app . "/config/");
        Renderer::init([DIR::$fw. '/templates']);
        Renderer::$enable_comments = true;

        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formName]);

        // echo("Found forms with name `$formName`\n");
        // print_r($dbForm);

        if(!empty($dbForm)) {
            $arr = json_decode( $dbForm[0]->getdata(), true );
            // print_r($arr);


            $formArray = generateHTMLForm( $arr );

            print_r($formArray);

        }
    } else {
        echo("Database table has no matching class to it\n");
        exit(-1);
    }
}
