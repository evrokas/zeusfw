<?php
    function mlog($s, $nl = true, $li=false) {
        if($li)echo __FILE__."(".__FUNCTION__."):".__LINE__.": ";
        echo $s . ($nl?"\n":"");
    }


    // $inline_options[] = array();
    $inline_options['add-id'] = 'true';
    // $inline_options['extend-class'] = null;

    $yaml_dir = "yaml";
    $sql_dir = "sql";
    $data_dir = "../../web/files/database";
    $class_dir = ".";
    $bootstrap_file = 'bootstrap_classes.php';




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
        mlog("require_once(__DIR__ . \"/../../fw/db/dbal.php\");\n");

        mlog('class ' . $this->classname . (isset($options['extends-class'])?' extends ' . $options['extends-class']:"") . ' {');
        

            foreach($this->fields as $fld) {
                mlog('  private $' . $fld['name'] . ';');
            }

            
            mlog("  function __construct(\$adata = array() ) {
      parent::__construct('".$this->name."', \$adata);
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
            
                    \$sql = \"SELECT * FROM " . $this->name . " WHERE id=:id\";
                    \$st = \$AppDBConnection->getConnection()->prepare( \$sql );
                    \$st->bindValue(\":id\", \$aid, PDO::PARAM_INT);
                    \$st->execute();
                    \$row = \$st->fetch();
            
                    if(\$row) {
                        \$rclass = new " . $this->classname . "( \"" . $this->name . "\");
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
        
                \$sql = \"SELECT * FROM " . $this->name . ";\";
                \$st = \$AppDBConnection->getConnection()->prepare( \$sql );
                \$st->execute();
        
                \$list = array();
        
                while( \$row = \$st->fetch() ) {
                    \$rclass = new " . $this->classname . "( \"" . $this->name . "\" );
                    \$rclass->loadFields( \$row );
                    \$list[] = \$rclass;
                }
        
                return (\$list);
        
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


    function emitSqlDiff() {
        $temp = tempnam('/tmp', 'maker-');
        // echo "Temporary file $temp\n";
    
        // print_r( $tableinfo );
    
        // $tbfields holds data from yaml file
        $tbfields = array();
        // echo "Table: ".$this-> name;
        foreach($this->fields as $fld) {
            // print_r( $fld );
            $tbfields[ $fld['name'] ] = [
                'name' => $fld['name'],
                'def' => $fld['definition']
            ];
            // mlog('Fields: ' . $fld['name'] . " ==> " . $fld['definition']);
            // echo "Fields: " . $fld['name']. "\n";
            // echo "Field: " .$field['name'] . "\n";
        }

        $tbfields2 = $tbfields;
        // print_r( $tbfields );

        // [0] => Array
        // (
        //     [0] => Field
        //     [1] => Type
        //     [2] => Null
        //     [3] => Key
        //     [4] => Default
        //     [5] => Extra
        // )


        $s = 'DESCRIBE ' . $this->name . ";";
        file_put_contents($temp, $s);
        $res = shell_exec("../../sql/msql.sh < " . $temp);
        // print_r( $res );

        $res = str_replace("\t\t", "\t", $res);
        $resl = explode("\n", $res);


        // $sqlfields holds the fields data from the database
        $sqlfields = array();
        foreach($resl as $r) {
            // print_r( str_replace("\t", "|", $r));
            $r = str_replace("\t", "|", $r);
            $exp = explode('|', $r);
            // print_r( $exp );
            $sqlfields[] = $exp;
        }

        // remove the initial line
        unset( $sqlfields[0] );
        // print_r( $sqlfields );
        // unlink($temp);

        // remove field if it has less than 4 elements
        foreach($sqlfields as $fk => $fv) {
            if(count($fv)< 4) {
                unset( $sqlfields[ $fk ]);
                continue;
            }

            // remove if field is 'id'
            if( $fv[0] == 'id') {
                unset( $sqlfields[ $fk ] );
                continue;
            }
            // print_r( $fv[0] );
        }
        // print_r( $sqlfields );

        // so we have prepared the SQL fields
        // transform $sqlfields to $sqlkeys, that holds data from the database also
        $sqlkeys = array();
        foreach($sqlfields as $fld) {
            $sqlkeys[ $fld[0] ] = [
                'name' => $fld[0],
                'definition' => $fld[1] . (($fld[2] == 'NO')?" not null":"") . (($fld[3] != 'NULL')?(" default " . $fld[3]):"")
            ];
        }

        // print_r( $sqlkeys );

        // here put definitions that one can use, and are mapped to the similat that MySQL server sends in describe
        // array key is the MySQL syntax, equal to the uer syntax
        // $definitions_equivalents[ 'boolean' ] = 'tinyint(1)';
        $definitions_equivalents[ 'tinyint(1)' ] = 'boolean';
        $definitions_equivalents[ 'tinyint(1) default 0' ] = 'boolean default false';
        $definitions_equivalents[ 'tinyint(1) default 1' ] = 'boolean default true';

        $common = array();
        $alter = array();
        foreach($sqlkeys as $skey => $sval) {
            if(array_key_exists($skey, $tbfields)) {
                if(strtolower( $sval[ 'definition'] ) == strtolower( $tbfields[$skey]['def'] ) ||
                (array_key_exists($sval[ 'definition' ], $definitions_equivalents) && $definitions_equivalents[ $sval['definition'] ] == strtolower( $tbfields[$skey]['def']))) {
                    $common[ $skey ] = $sval;
                    unset( $sqlkeys[ $skey ]);
                    unset( $tbfields[ $skey ]);
                } else {
                    $alter[ $skey ]['name'] = $skey;
                    $alter[ $skey ]['definition'] = $tbfields[ $skey ]['def'];
                    $alter[ $skey ]['olddef'] = $sval['definition'];
                    unset( $sqlkeys[ $skey ]);
                    unset( $tbfields[ $skey ]);
                }
            }
        }

        // echo "old fields\n";
        // print_r( $sqlkeys );
        
        // echo "new fields\n";
        // print_r( $tbfields );

        // echo "remain fields\n";
        // print_r( $common );

        // echo "alter fields\n";
        // print_r( $alter );

        // echo "SQL code\n";
        foreach($tbfields as $fld) {

            // when adding a field, make sure to place it in the correct position in order for
            // correct synchronization of different databases
            $k = array_keys($tbfields2);
            $v = array_values($tbfields2);

            // print_r( $k );
            // print_r( $v );

            $i = array_search($fld['name'], $k);
            // print_r( $i );
            if($i>0) {
                // echo "Key Found: $i\tprevious key: " . $k[ $i - 1 ]. "\n";
                $position = " AFTER " . $k[$i-1];
            } else
                $position = " AFTER id";    // 'id' is always the first field

            echo "ALTER TABLE " . $this->name . " ADD " . $fld['name'] . ' ' . $fld['def'] . $position . ";\n";
        }        

        foreach($sqlkeys as $fld) {
            echo "ALTER TABLE " . $this->name . " DROP " . $fld['name'] . ";\n";
        }

        foreach($alter as $fld) {
            echo "# old definition: " . $fld['name'] . " => " . $fld['olddef'] . "\n";
            echo "ALTER TABLE " . $this->name . " MODIFY " . $fld['name'] . ' ' . $fld['definition'] .";\n";
        }
    }

    function viewContent($afile) {

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
    // foreach($yaml_files as $f)
        // echo "$f\n";

    return ($yaml_files);
}

function get_sql_files($sqdir) {
    $sql_files = get_dir_files( $sqdir );
    // foreach($sql_files as $f)
        // echo "$f\n";

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
        mlog("require_once(__DIR__ . \"/".$cl."\");");
    }
    return ( ob_get_clean() );
}

function diff_sql($file) {
    $tableinfo =  yaml_parse_file( $file );
    
    $db = new Database($tableinfo);
    $s = $db->emitSqlDiff();

    echo $s;
}

function export_data($afile) {
    global $yaml_dir;

    $temp = tempnam('/tmp', 'export-');

    $yfile = $yaml_dir . '/' . $afile . '.yaml';
    echo "yaml file: $yfile\n";

    $yinfo = yaml_parse_file($yfile)[0];

    // print_r( $yinfo );

    $args = "-y --compact --skip-extended-insert --no-create-info --skip-comments ";
    $cmd = "../../sql/msqldump.sh " . $args . $yinfo['table'];
    $res = shell_exec( $cmd );
    // $res2 = array();
    // $res = exec( $cmd , $res2);
    print_r( $res );
    // print_r( $res2 );
}

function content_view($afile) {
    mlog("processing file $afile", true, true);

    // echo __FILE__.":".__LINE__."(".__FUNCTION__."): processing file $afile\n";

}


function makesure_dir_exists($dir) {
    if(!file_exists($dir)) {
        mkdir($dir);

        if(!file_exists($dir)) {
            echo "Could not create folder: $dir\n";
            exit();
        }
    }
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

    // $yaml_dir = "yaml";
    // $sql_dir = "sql";
    // $data_dir = "../../web/files/database";
    // $class_dir = ".";
    // $bootstrap_file = 'bootstrap_classes.php';


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
                'update:bootstrap' => 'update bootstrap for classes PHP file',
                'diff:sql' => 'show differences between YAML files and MySQL tables',
                'diff:sql:all' => 'show differences for all YAML files',
                'data:export' => 'export data from SQL database table to data folder',
                'content:view' => 'show content data',
            ];

            foreach($commands as $key => $value) {
                mlog(str_pad($key,20) . $value);
            }
            break;

            
        case 'list:yaml':
            $yf = get_yaml_files($yaml_dir);
            foreach($yf as $f) {
                echo "$f\n";
            }
            break;

        case 'spill:sql':
            // be sure the sql directory exists, otherwise create it
            makesure_dir_exists( $sql_dir );
            
            if(!$optparams[1]) {
                mlog('Please specify a file to process.');
                exit;
            }
            $file = $optparams[1];
            
            spill_sql($yaml_dir . '/' . $file, $sql_dir );
            break;

        case 'spill:sql:all':

            // be sure the sql directory exists, otherwise create it
            makesure_dir_exists( $sql_dir );

            $files = get_yaml_files( $yaml_dir );
            foreach( $files as $yfile ) {
                spill_sql( $yaml_dir . '/' . $yfile, $sql_dir );
            }
            break;


        case 'list:sql':
            $sqlf = get_sql_files( $sql_dir );
            foreach($sqlf as $f) {
                echo "$f\n";
            }
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
            break;

            // echo $s."\n";
        case 'diff:sql':
            $file = $optparams[1];
            // echo "Processing file: $file\n";
            diff_sql( $file );
            break;

        case 'diff:sql:all':
            $files = get_yaml_files( $yaml_dir );
            foreach($files as $yfile) {
                // echo "yfile: $yfile\n";
                diff_sql( $yaml_dir . '/' . $yfile );
            }
            break;

        case 'content:view':
            $file = $optparams[1];
            echo "Showing content for file: $file\n";
            content_view( $file );
            break;


        case 'data:export':

            // be sure the sql directory exists, otherwise create it
            makesure_dir_exists( $data_dir );
            $file =  $optparams[1];
            export_data($file);
            break;

        default:
            echo "Unknown command\n";
            exit;
    }