<?php
    function mlog($s, $nl = true) {
        echo $s . ($nl?"\n":"");
    }


    // $inline_options[] = array();
    $inline_options['add-id'] = 'true';
    // $inline_options['extend-class'] = null;


class Database {
    private $name;
    private $classname;
    private $fields;

    function __construct($ayaml) {
        foreach($ayaml as $table) {
            if(isset($table)) {
                // mlog("table set");
                
                $this->name = $table['table'];
                // mlog("table name: " . $this->name);
                
                $this->classname = $table['class'];

                if(isset($table['fields'])) {
                    // mlog("Fields set");
                    
                    // $f = array();
                    // $f['name'] = 'id';
                    // $f['definition'] = 'integer not null auto_increment unique';
                    // $this->fields[] = $f;

                    foreach($table['fields'] as $fieldname => $fielddef) {
                        foreach($fielddef as $fname => $fdef) {
                            // mlog(" - Field  [" . $fname . "] = " . $fdef);
                            $f = array();
                            $f['name'] = $fname;
                            $f['definition'] = $fdef;
                            $this->fields[] = $f;
                        }
                    }
                }
            }
        }
    }

    function emitDescription() {
        ob_start();
        mlog("/* DBAL_TABLENAME_BEGIN " . $this->name . " */");
        foreach($this->fields as $fld) {
            mlog("/* DBAL_FIELD " . $fld['name'] . "|" . $fld['definition'] . " */" );
        }
        mlog("/* DBAL_TABLENAME_END */");
        mlog("DROP TABLE IF EXISTS " . $this->name . ";");
        mlog("CREATE TABLE " . $this->name . " (");
        mlog("  id INTEGER NOT NULL AUTO_INCREMENT UNIQUE,");

        foreach($this->fields as $fld) {
            mlog("  " . $fld['name'] . " " . $fld['definition'] . "," );
        }
        mlog("  PRIMARY KEY (id)");
        mlog(") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        return( ob_get_clean() );
    }

    function emitClass() {
      global $options;
        $fldnames = array();
        $flddef = array();

        foreach($this->fields as $fld) {
            $fldnames[] = $fld['name'];
            $flddef[] = $fld['definition'];
        }

        ob_start();
        mlog('<?php');
        mlog('// class ' . $this->classname);
        mlog("require_once('../db/dbal.php');\n");

        mlog('class ' . $this->classname . (isset($options['extends-class'])?' extends ' . $options['extends-class']:"") . ' {');
        

            foreach($this->fields as $fld) {
                mlog('  private $' . $fld['name'] . ';');
            }

            
            mlog("  function __construct(\$adata = array() ) {
      parent::__construct('".$this->name."', \$adata);
      \$this->loadFields( \$adata );
  }");

            mlog("  function loadFields(\$adata) {
      parent::loadFields(\$adata);");

            foreach($this->fields as $fld) {
                mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
            }

            mlog("  }");

            // emit setters and getters
            foreach($this->fields as $fld) {
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
            
        mlog("\$sql = \"INSERT INTO ".$this->name ." ( ", false);

        $str = implode(',', $fldnames);
        mlog($str . " ) VALUES ( " , false );
        
        $str = implode(',', preg_filter('/^/', ':', $fldnames));
        mlog($str . " );\";");
        
        // echo "SQL: $sql \n";
        mlog("\$st = \$this->getConnection()->getConnection()->prepare ( \$sql );");

        foreach($this->fields as $fld) {
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
                
            \$sql = \"UPDATE ".$this->name ." SET ", false);
            

            $eq = array();
            foreach($this->fields as $fld) {
                $eq[] = $fld['name'] . '=:' . $fld['name'];
                    // mlog($fld['name'] . "=:".$fld['name'].",", false);
            }
            $str = implode(',', $eq);
            mlog($str , false );

            mlog(" WHERE id=:id\";");
                
            mlog("
            \$st = \$this->getConnection()->getConnection()->prepare ( \$sql );
            ");
    
            foreach($this->fields as $fld) {
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
    
        mlog("\$sql = \"DELETE FROM " . $this->name . " WHERE id = :id;\";");
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

}

function get_dir_files($dir) {
    echo "# Listing of ".$dir . '/' . " folder.\n";
    // list YAML files
    $folder= scandir($dir . '/');
    $files = array();
    foreach($folder as $f)
        if($f[0] != '.')$files[] = $f;
    // foreach($files as $f)
        // echo "$f\n";

    return ($files);
}

function get_yaml_files($ydir) {
    $yaml_files = get_dir_files( $ydir );
    foreach($yaml_files as $f)
        echo "$f\n";

    return ($yaml_files);
}

function get_sql_files($sqdir) {
    $sql_files = get_dir_files( $sqdir );
    foreach($sql_files as $f)
        echo "$f\n";

    return ($sql_files);
}

function get_class_files($classdir, $ydir) {
    $class_files = get_dir_files( $classdir );
    $yaml_files = get_yaml_files( $ydir );


    array_walk($class_files, function(&$value, $key) {
        $value = explode('.', $value)[0];
    });
    
    array_walk($yaml_files, function(&$value, $key) {
        $value = explode('.', $value)[0];
    });

    print_r( $class_files);
    print_r( $yaml_files );

    $files = array_intersect($class_files, $yaml_files );

    array_walk($files, function(&$value, $key){
        $value = $value . '.php';
    });

    // print_r( $files );

    return( $files );
}

function spill_sql($ymlfile, $sqldir) {
    $dbinfo = yaml_parse_file( $ymlfile );
        
    // print_r( $dbinfo );

    $db = new Database( $dbinfo );
    $s = $db->emitDescription();
    
    // echo $s;
    echo "emit SQL data in ". $sqldir . '/' . $dbinfo[0]['table']. ".sql\n";
    file_put_contents($sqldir . '/' . $dbinfo[0]['table'].'.sql',$s);
}


function spill_class($sqlfile, $classdir) {
    $dbinfo = yaml_parse_file( $sqlfile );
    // print_r( $dbinfo );
        
    $db = new Database( $dbinfo );
    $s = $db->emitClass();

    echo "emit CLASS data in ". $classdir . '/' . $dbinfo[0]['table']. ".php\n";
    file_put_contents($classdir . '/' . $dbinfo[0]['table'].'.php', $s);

}

function spill_bootstrap($files) {
    ob_start();
    mlog("<?php");
    mlog("");
    foreach($files as $cl) {
        mlog("require_once(\"".$cl."\");");
    }
    return ( ob_get_clean() );
}


// execute various components of the framework

    $getopt_options_short = "f:";
    $getopt_options_long = ['add-id', 'extends-class:'];
    $i=0;
    $optparams = array();
    
    $cmdline_options = getopt($getopt_options_short, $getopt_options_long, $i);
    $options = array_merge($inline_options, $cmdline_options);
    // print_r($options);

    if(!isset($options['extends-class'])) {
        $options['extends-class'] = 'dbAbstractEntityClass';
    }

    while($i < $argc) {
        $optparams[] = $argv[$i++];
    }
    // print_r( $opts );
    // mlog( 'argc: ' . $argc );
    // mlog( 'i: ' . $i );
    // print_r( $optparams );

    if(!$optparams[0]) {
        mlog('Please specify a command to execute.');
        exit;
    }

    $yaml_dir = "yaml";
    $sql_dir = "sql";
    $class_dir = ".";
    $bootstrap_file = 'bootstrap_classes.php';

    switch($optparams[0]) {
        case 'help':
            $commands = [
                'help' => 'This help message',
                'list:yaml' => 'list YAML files in the yaml folder',
                'list:sql' => 'list SQL files in the SQL folder',
                'spill:sql' => 'spill SQL code for specific YAML file',
                'spill:sql:all' => 'spill SQL code for all YAML files in yaml folder',
                'spill:class' => 'spill PHP CLASS code for specific YAML file',
                'spill:class:all' => 'spill PHP CLASS code for all YAML files in yaml folder',
                'update:bootstrap' => 'update bootstrap for classes PHP file'
            ];

            foreach($commands as $key => $value) {
                mlog(str_pad($key,20) . $value);
            }
            break;

            
        case 'list:yaml':
            get_yaml_files($yaml_dir);
            break;

        case 'spill:sql':
            if(!$optparams[1]) {
                mlog('Please specify a file to process.');
                exit;
            }
            $file = $optparams[1];
            
            spill_sql($yaml_dir . '/' . $file, $sql_dir );
            break;

        case 'spill:sql:all':
            $files = get_yaml_files( $yaml_dir );
            foreach( $files as $yfile ) {
                spill_sql( $yaml_dir . '/' . $yfile, $sql_dir );
            }
            break;


        case 'list:sql':
            get_sql_files( $sql_dir );
            break;

        case 'spill:class':
            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            
            spill_class( $yaml_dir . '/' . $file, $class_dir);
            break;
        case 'spill:class:all':
            $files = get_yaml_files( $yaml_dir );
            foreach($files as $yfile ) {
                spill_class($yaml_dir . '/' . $yfile, $class_dir);
            }
            break;
        case 'update:bootstrap':
            $files = get_class_files( $class_dir, $yaml_dir );
            $s = spill_bootstrap( $files );

            echo "Updating bootstrap file " . $bootstrap_file . "\n";
            file_put_contents($bootstrap_file, $s);

            // echo $s."\n";

            break;
    }