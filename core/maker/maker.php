<?php

class DIR {
    static $app = null;
    static $fw = null;
};



    $getopt_options_short = "f:";
    $getopt_options_long = array(
        'app-dir:',         // application directory
        'add-id',           // add 'is' field (default: yes)
        'extends-class:',   // class name to extend
        'name:',            // class name to create
        'type:', 'author:', 'title:', 'desc:', 'viewmode:',     // content fields
        'template:',        // YAML template to create feeder
        'dir:',             // YAML templates folder
        'update:'           // when 'feed:gen:yaml' force updating fields field1:field2:field3:...
    );
    $options = array();

    // $inline_options[] = array();
    $inline_options['add-id'] = 'true';
    // $inline_options['extend-class'] = null;

    $yaml_dir = "yaml";
    $sql_dir = "sql";
    $data_dir = "../../web/files/database";
    $class_dir = ".";
    $bootstrap_file = 'bootstrap_classes.php';


    // $getopt_options_short = "f:";
    // $getopt_options_long = array('app-dir:', 'add-id', 'extends-class:',
    //     'name:', 'type:', 'author:', 'title:', 'desc:', 'viewmode:',
    //     'template:', 'dir:');
    $i=0;
    $optparams = array();
    
    $cmdline_options = getopt($getopt_options_short, $getopt_options_long, $i);
    $options = array_merge($inline_options, $cmdline_options);
    // print_r($options);

    // if(!isset($options['extends-class'])) {
        // $options['extends-class'] = 'dbAbstractEntityClass';
    // }

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


    if(isset($options['app-dir'])) {
        DIR::$app = $options['app-dir'];
        // define('__APPDIR__', $options['app-dir']);
    }




    if(!DIR::$app) {

        // include( getcwd() . "/../../config/db.php");
        // echo "cwd(): " . getcwd() . "\n";
        $dnames = explode('/' , getcwd());
        // print_r( $dnames );
        
        for($i=count($dnames);$i>0;$i--) {
            $dir = implode('/',array_slice($dnames, 0, $i));
            // echo("searching in folder: $dir\n");
            if(file_exists($dir . '/config/db.php')) {
                // echo "$dir/config exists!";
                DIR::$app = $dir;
                DIR::$fw = $dir . '/web/core';
                
                define('__APPDIR__', $dir);
                define('__FWDIR__', $dir . '/web/core');
                break;
            } else
            if(file_exists($dir . '/bootstrap.php')) {
                // echo "$dir/bootstrap.php exists!";
                DIR::$fw = $dir;
                
                define('__FWDIR__', $dir);
                break;
            }
        }
    }

    // echo("app-dir: " . DIR::$app . "\tfw-dir: " . DIR::$fw . "\n");
    // if(defined('__APPDIR__'))echo 'app dir: ' . __APPDIR__ . "\n";
    // if(defined('__FWDIR__'))echo 'fw dir: ' . __FWDIR__ . "\n";


    

    require('functions.php');

    function mlog($s, $nl = true, $li=false) {
        if($li)echo __FILE__."(".__FUNCTION__."):".__LINE__.": ";
        echo $s . ($nl?"\n":"");
    }


    function mguid() {
        return (trim(file_get_contents('/proc/sys/kernel/random/uuid')));
    }
    


    include(__DIR__ . '/../db/dbal.php');
    

    if(!defined('__FWDIR__'))
        define('__FWDIR__', DIR::$fw);
    
    if(file_exists(DIR::$fw . '/classes/feed_hashes.php'))
      include(DIR::$fw . '/classes/feed_hashes.php');
    
    if(file_exists(DIR::$fw . '/classes/feed_class.php'))
      include(DIR::$fw . '/classes/feed_class.php');
    


    // $host = DB_HOST;
    // $dbname = DB_NAME;
    // $user = DB_USER;
    // $pass = DB_PASS;
    // echo("DIR::app folder: " . print_r(DIR::$app, 1));
    if(file_exists(DIR::$app . '/config/db.php')) {

        include_once(DIR::$app . '/config/db.php');

        // echo("Connecting to database\n");
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if(!dbConnection::Connect()) {
            echo "Could not connect to the database. Fatal error. Please contact administrator!";
            exit(-1);
        }
    }





    function require_option($opt) {
        global $options;

            if(!isset($options[ $opt ])) {
                echo "Please set option `$opt` because is required for action.\n";
                exit;
            }
            
        return true;
    }


class Database {
    private $name;
    private $classname;
    private $extends;
    private $fields;

    function __construct($ayaml, $ayaml_file) {
        // print_r($ayaml);

        // load table structure from .yaml file
        if(array_key_exists('table', $ayaml)) {
            $table = $ayaml['table'];

                // mlog("table set");
                
                // $this->name = $table['table'];
                if(!array_key_exists('name', $table)) {
                    echo("`name` of table is not set in $ayaml_file\n");
                    exit();
                }
                $this->name = $table['name'];
                // mlog("table name: " . $this->name);
                
                if(!array_key_exists('class', $table)) {
                    echo("`class` name for table handling is not set in $ayaml_file\n");
                    exit();
                }
                $this->classname = $table['class'];

                if(isset($table['extends']))
                    $this->extends = $table['extends'];
                else $this->extends = null;

                if(!array_key_exists('fields', $table)) {
                    echo("`fields` is not set for table in $ayaml_file\n");
                    exit();
                }
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
        } else {
            echo "`table` is not set in file $ayaml_file\n";
            exit();
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
        // mlog("require_once(__DIR__ . \"/../../fw/db/dbal.php\");\n");
        $ext = 'dbAbstractEntityClass';
        if($this->extends)$ext = $this->extends;
        if(isset($options['extends-class']))$ext = $options['extends-class'];

        mlog('class ' . $this->classname . ' extends ' . $ext . ' {');
        
            foreach($this->fields as $fld) {
                mlog('  private $' . $fld['name'] . ';');
            }

            
            mlog("  function __construct(\$adata = array() ) {
                        parent::__construct('".$this->name."', \$adata);
                        \$this->loadFields( \$adata );
                }");

            mlog("  static function sgetById(int \$aid) {
                    \$sql = \"SELECT * FROM " . $this->name . " WHERE id=:id\";
                    \$st = dbConnection::getConnection()->prepare( \$sql );
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
                \$sql = \"SELECT * FROM " . $this->name . ";\";
                \$st = dbConnection::getConnection()->prepare( \$sql );
                \$st->execute();
        
                \$list = array();
        
                while( \$row = \$st->fetch() ) {
                    \$rclass = new " . $this->classname . "( \"" . $this->name . "\" );
                    \$rclass->loadFields( \$row );
                    \$list[] = \$rclass;
                }
        
                return (\$list);
        
            }");

            mlog("function loadFields(\$adata) {
      parent::loadFields(\$adata);");

            foreach($this->fields as $fld) {
                mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
            }

            mlog("}\n");

            mlog("function getFields() {
              \$resp = array();
              \$resp = array_merge(\$resp, parent::getFields());");


            foreach($this->fields as $fld) {
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


            foreach($this->fields as $fld) {
                if($fld['name'] != 'id') {
                    mlog("              \$resp = array_merge(\$resp, ['" .$fld['name']. "' => \$this->".$fld['name']."]);");
                }
            }
            // foreach($this->fields as $fld) {
                // mlog("      if(isset(\$adata['".$fld['name']."']))\$this->".$fld['name']." = \$adata['".$fld['name']."'];");
            // }

            mlog("      return \$resp;\n}\n");




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

        ");
            
        mlog("\$sql = \"INSERT INTO ".$this->name ." ( ", false);

        $str = implode(',', $fldnames);
        mlog($str . " ) VALUES ( " , false );
        
        $str = implode(',', preg_filter('/^/', ':', $fldnames));
        mlog($str . " );\";");
        
        // echo "SQL: $sql \n";
        mlog("\$st = \$this->getConnection()->prepare ( \$sql );");

        foreach($this->fields as $fld) {
            mlog("\$st->bindValue( \":".$fld['name']."\", \$this->".$fld['name'].", PDO::PARAM_STR );");
        }
        mlog("\$st->execute();");
        
//         echo "Inserted record\n";
        mlog("\$this->setid( \$this->getConnection()->lastInsertId() );");
        mlog("}");


        // update()
        mlog("
        function update() {
            if(\$this->id == null) {
                echo 'Trying to update() a record that does not exist';
                return (null);
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
            \$st = \$this->getConnection()->prepare ( \$sql );
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
        ");
    
        mlog("\$sql = \"DELETE FROM " . $this->name . " WHERE id = :id;\";");
        mlog("\$st = \$this->getConnection()->prepare(\$sql);");
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

        if(!DIR::$app)require_option('app-dir');
        $res = shell_exec(DIR::$app . "/sql/msql.sh < " . $temp);
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

function get_dir_files($dir, $ext = null) {
    // echo "# Listing of ".$dir . '/' . " folder.\n";
    // list YAML files
    $folder= scandir($dir . '/');
    // $folder= glob($dir . '/' . '*.' . $ext); 
        // scandir($dir . '/');
    $files = array();
    foreach($folder as $f)
        if(($f[0] != '.') &&
           ((($ext!=null) && pathinfo($f, PATHINFO_EXTENSION) === $ext))
            || ($ext === null))
           $files[] = $f;

    // foreach($files as $f)
        // echo "$f\n";

    return ($files);
}

function get_yaml_files($ydir) {
    $yaml_files = get_dir_files( $ydir, 'yaml' );
    // foreach($yaml_files as $f)
        // echo "$f\n";

    return ($yaml_files);
}

function get_sql_files($sqdir) {
    $sql_files = get_dir_files( $sqdir, 'sql' );
    // foreach($sql_files as $f)
        // echo "$f\n";

    return ($sql_files);
}

function get_class_files($classdir, $ydir) {
    $class_files = get_dir_files( $classdir );
    $yaml_files = get_yaml_files( $ydir );


    array_walk($class_files, function(&$value, $key) {
        // echo("value: $value\n");
        $value = explode('.', $value)[0];
    });
    
    array_walk($yaml_files, function(&$value, $key) {
        // echo("processing yaml file: $value\t");
        $y = yaml_parse_file("yaml/$value");
        // echo("table: " . $y[0]['table'] . "\n");

        // the following sets the .php file name to the same as the
        // yaml file
        // $value = explode('.', $value)[0];

        // but sometimes the table name in the yaml file can be
        // different from the name of the yaml file, so name the .php
        // file as the table name plus .php
        $value = $y['table']['name'];
    });

    // echo("class files: " . print_r( $class_files, 1));
    // echo("YAML files: " . print_r( $yaml_files, 1));

    $files = array_intersect($class_files, $yaml_files );

    array_walk($files, function(&$value, $key){
        $value = $value . '.php';
    });

    // print_r( $files );

    return( $files );
}

function spill_sql($ymlfile, $sqldir) {
    $dbinfo = yaml_parse_file( $ymlfile );
    
    // echo("spill_sql(): $sqldir / $ymlfile");
    // echo("info: " . print_r($dbinfo,1));

    // $db = new Database( $dbinfo );
    // $s = $db->emitDescription();
    
    $s = generateSQLTable($dbinfo);



    // echo "$s\n";
    // echo "emit SQL data in ". $sqldir . '/' . $dbinfo['table']['name']. ".sql\n";
    file_put_contents($sqldir . '/' . $dbinfo['table']['name'].'.sql',$s);
}


function spill_class($sqlfile, $classdir) {
    $dbinfo = yaml_parse_file( $sqlfile );
    // print_r( $dbinfo );

    // $db = new Database( $dbinfo );
    // $s = $db->emitClass();

    $s = generateClassCode($dbinfo);

    if(isset($dbinfo['table']['extention'])) {
        $s .= "\n\n";
        $s .= "if(file_exists( ". $dbinfo['table']['extention'] . ")) {\n";
        $s .= "require_once( " . $dbinfo['table']['extention'] . " );\n}\n";

    }
        
    // echo "emit CLASS data in ". $classdir . '/' . $dbinfo[0]['table']. ".php\n";
    file_put_contents($classdir . '/' . $dbinfo['table']['name'].'.php', $s);

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

    if(!DIR::$app)require_option('app-dir');

    $tableinfo =  yaml_parse_file( $file );
    
    // echo(">>> DIR::app: {{" . print_r(DIR::$app,1) . "}}\n");
    // $db = new Database($tableinfo);
    // $s = $db->emitSqlDiff();
    $s = syncTableWithYAML($tableinfo, dbConnection::getConnection());

    if(!empty($s))echo("$s\n");
}

function generate_content($author, $type, $name, $title, $desc, $viewmode) {

    $s = '';
    $s .= "guid: " . mguid() . "\n";
    $s .= "name: $name\n";
    $s .= "contenttype: $type\n";
    $s .= "title: $title\n";
    $s .= "description: $desc\n";
    $s .= "author: $author\n";
    // $s .= "date: " . (new DateTime("now", new DateTimeZone('Europe/Athens')))->format('d-m-Y H:i:s T') . "\n";
    $s .= "date: " . date ('Y-m-d H:i:s T') . "\n";
    $s .= "viewmode: $viewmode\n";
    $s .= "---\n";
    $s .= "This is test content\n";

    echo(print_r($s, 1));
}

function generate_feed($template, $name, $key, $output = null, $specials_list = array(), $leaf_path = array()) {

    $leaf_path = array_reverse($leaf_path);
    // echo $template;
    $arr = array();

    $arr += ['cmd' => '"' . implode(' ', $GLOBALS['argv'])  . '"' ];
    // $arr += ['directory' => getcwd()];
    $arr += ['createdate' => date ('d-m-Y H:i:s')];

    // $arr += ['schema2' => realpath( $template )];
    $arr += ['schema' => $template];

    $tem = yaml_parse_file($template);
    // print_r($tem);

    if(!isset($key)){
        $key = array('name' => 'default', 'value' => array('default'));
    }
    $arr += ['key' => $key];
    $arr['data'] = array();
    
    foreach($tem as $temrec) {
        if(isset($temrec['fields'])) {

            // print_r($temrec['fields']);
            
            include_once( dirname(dirname(realpath($template))).'/'.$tem['table']['name'].'.php');
            $cl = new $tem['table']['class']();
            $fields = $cl->getFields();
            // print_r($fields);

            foreach($key['value'] as $optval) {
                $arr['data'][$optval] = array();

                foreach($fields as $fkey => $fval) {
                    // echo("processing field: $fkey\n");
                    if((isset($specials_list['guid'])) && in_array($fkey, $specials_list['guid'])) {
                            $g = mguid();
                            $arr['data'][$optval] += [$fkey => $g];
                    } else
                    if((isset($specials_list['date'])) && in_array($fkey, $specials_list['date'])) {
                        $arr['data'][$optval] += [$fkey => date('Y-m-d H:i:s')];
                    } else
                    if((isset($specials_list['sequential'])) && in_array($fkey, $specials_list['sequential'])) {

                        echo "sequential index: " . $specials_list['__index'] . "\n";
                        
                        // $arr['data'][$optval] += [$fkey => $specials_list['sequential'][$fkey]['seq']];
                        $arr['data'][$optval] += [$fkey => $specials_list['__index'] ];
                    } else
                    if((isset($specials_list['prefeed'])) && array_key_exists($fkey, $specials_list['prefeed'])) {
                        echo "prepopulate `$fkey` with `" . print_r($specials_list['prefeed'][$fkey], 1) . "`\n";
                        
                        // put prefeed template to $stemplate
                        $stemplate = $specials_list['prefeed'][$fkey];
                        // if(is_array($stemplate))$stemplate = serialize($stemplate);
                        $field_is_json = false;
                        if(is_array($stemplate)) {
                            $stemplate = json_encode($stemplate, JSON_UNESCAPED_UNICODE);
                            $field_is_json = true;
                        }
                        echo("prefeed default value: `$stemplate`\n");
                        // now do field replacement on $stemplate
                        $stemplate = str_replace("{{key}}", $optval, $stemplate);
                        $stemplate = str_replace("{{name}}", $name, $stemplate);
                        if(count($leaf_path)>0)
                            $stemplate = str_replace(["{{leaf}}"],  $leaf_path[0], $stemplate);
                        if(count($leaf_path)>1)
                            $stemplate = str_replace("{{leaf^}}",  $leaf_path[1], $stemplate);
                        if(count($leaf_path)>2)
                        $stemplate = str_replace("{{leaf^^}}",  $leaf_path[2], $stemplate);
                        if(count($leaf_path)>3)
                            $stemplate = str_replace("{{leaf^^^}}",  $leaf_path[3], $stemplate);

                            if($field_is_json) {
                                $arr['data'][$optval] += [$fkey => json_decode($stemplate, true)];
                            } else {
                                $arr['data'][$optval] += [$fkey => $stemplate];
                            }

                        echo "\tequals `$fkey` with `" . $stemplate . "`\n";

                        // $arr['data'][$optval] += [$fkey => $specials_list['prefeed'][$fkey]];
                    } else
                    if((isset($specials_list['name'])) && in_array($fkey, $specials_list['name'])) {
                        echo "update name of entry $name\n";
                        $arr['data'][$optval] += [$fkey => $name];
                    } else
                    if($fkey === $key['name']) {
                        $arr['data'][$optval] += [$fkey => $optval];
                    } else {
                        $arr['data'][$optval] += [$fkey => null];
                    }
                    // }
                }

                // echo "opt key val: $optval\n";
            }
        }
    }
    // echo "DEFAULT values: ";    print_r($arr);

    // print_r( yaml_emit($arr));


    if(!$output) {

        echo "\n# output: $name.yml\n";
    } else {

        if(file_exists($output)) {
            $inn = yaml_parse_file($output);
            // echo "READ from $output: "; print_r($inn);

            // remeove data entries from input array, when must be updated by default values
            // echo(print_r($specials_list['__update'], 1));
            foreach($specials_list['__update'] as $upd) {
                echo "default update field: $upd\n";

                foreach($inn['data'] as $earr => $earrval) {
                    // echo "trying to reset default value for field $earr: "; print_r($earrval);
                    echo "value of $upd field: >>" . print_r($earrval[$upd],1) . "<<\n";
                    if(key_exists($upd, $earrval)) {
                        echo "must remove key $upd  from array $earr\n";
                        // echo "field : " . $inn['data'][$earr][$upd] . "\n";
                        unset($inn['data'][$earr][$upd]);
                    }
                }
                echo "unsetted force update: " . print_r($earrval, 1);
            }
            // print_r($inn);

            // unset schema to set new path for schema file
            if(key_exists('schema', $inn))
                unset($inn['schema']);

            // remove some deprecated keys, from older versions
            $deprecated = ['directory', 'schema2'];
            foreach($deprecated as $token) {
                echo("check for deprecated key $token\n");
                if(key_exists($token, $inn)) {
                    echo("  removed\n");
                    unset($inn[$token]);
                }
            }

            $out = array_replace_recursive($arr, $inn);
            // echo "IN: ";    print_r($inn);

            // echo "OUT: ";   print_r($out);
        } else $out = $arr;
        // file_put_contents($output, $s);
        yaml_emit_file($output, $out, YAML_UTF8_ENCODING);
        echo "output: $output\n";
    }
}

function sections_iterate($item, $path = array(), $depth = 0) {
    $results = [];
    foreach($item as $key => $el) {
        $temppath = $path;
        $temppath[] = $key;
        // echo("path: " . print_r($temppath, 1));
        // echo(str_repeat("\t", $depth) . "section iterate: $key (path: " . implode(' | ', $temppath).")\n");
        if(is_array($el)) {
            // $temppath[] = $
            $res = sections_iterate($el, $temppath, $depth+1);
            foreach($res as $r)$results[] = $r;
        } else
            $results[] = $temppath; //implode(' | ', $temppath);
        
    }
    return $results;
}

function generate_feed_from_yaml($name, $dir=null, $update = array()) {
    // print_r($name);
    $yfeed = yaml_parse_file($name);
    // print_r( $yfeed );
    // echo "yaml path " . $dir. "\n";
    // echo "Real path: " . realpath($name) . "\n";
    // $dir = trim($dir, " /\\");

    if(!$dir && !isset($yfeed['schemadir'])) {
        echo("ERROR: Please set schema class directory (either with --dir directoive of `schemadir` field in yaml file.\n");
        exit;
    }

    if(!$dir) {
        $dir = $yfeed['schemadir'];
        $dir = str_replace(['@core', '@app'], [DIR::$fw, DIR::$app], $dir);
        // $dir = str_replace('@app', DIR::$app, $dir);
        echo("Using schema file: $dir\n");
    }

     
    
    $ytemplate = rtrim($dir, '/') . '/' . $yfeed['schema'];

    if(isset($yfeed['key'])) {
        $key = $yfeed['key'];
    } else $key = null;

    // echo "generate upon key: ";
    // print_r($key);;

    if(isset($yfeed['sections'])) {
        $section = $yfeed['sections'];
        // echo("Generating sections: " . print_r($yfeed['sections'], 1));
        
        $fullSections = sections_iterate($section);
        echo("Results: " . print_r($fullSections, 1));
    }

    // exit;
    
    $specials_list = array();
    $specials_keys = ['guid', 'date', 'prefeed', 'sequential', 'name'];
    foreach($specials_keys as $skey) {
        // echo "key prepopulates for $skey\n";
        if(isset($yfeed[ $skey ])) {
            $specials_list[ $skey ] = $yfeed[ $skey ];
            // echo("Set prepopulated field $skey as " .print_r($yfeed[$skey],1) ."\n");
        }
    }

    $specials_list['__update'] = $update;

    $idx = 0;
    // if(isset($yfeed['order'])) {
        // foreach($yfeed['order'] as $feeder) {
    if(isset($yfeed['sections'])) {
        foreach($fullSections as $leaves) {
            $feeder = implode('-', $leaves);

            $specials_list['__index'] = $idx++;
            echo "#Feeder $feeder ... \n";
            generate_feed($ytemplate, $feeder,
                    $key,
                    $yfeed['source'][0] . '/' . $feeder . '.yml',
                    $specials_list,
                    $leaves);
        }
    }
}

function clean_feed_data($name, $dir=null) {

    if(!DIR::$app)require_option('app-dir');

    $yfeed = yaml_parse_file($name);
    if(!$yfeed) {
        echo("ERROR: YAML `$name` file could not be parsed.\n");
        exit;
    }


    if(!$dir && !isset($yfeed['schemadir'])) {
        echo("ERROR: Please set schema class directory (either with --dir directoive of `schemadir` field in yaml file.\n");
        exit;
    }

    if(!$dir) {
        $dir = $yfeed['schemadir'];
        $dir = str_replace('@core', DIR::$fw, $dir);
        $dir = str_replace('@app', DIR::$app, $dir);

     
        echo("Using schema file: $dir\n");
    }



    $schemaFile = $yfeed['schema'];
    // echo("schema file: $schemaFile\n");

    if(!file_exists($dir . '/' . $schemaFile)) {
        echo("ERROR: Class yaml description file `$dir/$schemaFile` is not available.\n");
        exit;
    }

    $schema = yaml_parse_file($dir . '/' . $schemaFile);
    if(!$schema) {
        echo("ERROR: Schema YAML `$schemaFile` file is not a YAML file.\n");
        exit;
    }

    // echo("Schema: " . print_r($schema, 1));
    $schemaName = $schema['table']['name'];

    DIR::$fw = DIR::$app . '/web/core';
    
    // echo("DIR::app: " . DIR::$app . "\n");
    // echo("DIR::fw: " . DIR::$fw . "\n");
    include_once(DIR::$app . '/config/db.php');
    require(DIR::$fw . "/bootstrap.php");
    
    
    dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    dbConnection::Connect();

    $pdo = dbConnection::getConnection();

    $cmd = "DELETE feed_hashes, $schemaName FROM feed_hashes JOIN $schemaName ON feed_hashes.guid = $schemaName.guid";
    // echo("SQL delete command: $cmd\n");

    $results = $pdo->query( $cmd, );
    if(!$results) {
        echo("ERROR: SQL query for delete failed.\n");
        exit;
    }

    echo("Table `$schemaName` and corresponding `feed_hashes` data are cleared succsufully!\n");
    exit(0);
}

function load_feed_data($name) {
    // echo("load yaml feeds to database\n");
    // echo "yfile: " . $name;

    $yfeed = yaml_parse_file($name);
    // print_r( $yfeed );


    // find source files
    $files = array();
    foreach($yfeed['source'] as $src) {
        // echo "source dir: " . $src . "\n";
        $files= array_merge($files, glob($src . '/*.yml'));
    }
    // print_r('yml files: ' . print_r($files, 1));

    
    foreach($files as $yfile) {
        $ydata = yaml_parse_file($yfile);
        // echo "schema: " . $ydata['schema'] . "\n";
        // print_r($ydata);
        // print_r($ydata['data']);
        // print_r( pathinfo($ydata['schema']));
        $yschema = yaml_parse_file( $ydata['schema'])['table'];
        $schemaClass = $yschema['class'];

        // echo('schema y-file: '. print_r($yschema, 1));
        // echo('schema class: ' . $schemaClass . "\n");

        $pinfo = pathinfo($ydata['schema']);
        $phpfile = dirname($pinfo['dirname']) . '/' . $pinfo['filename'] . '.php';
        // echo "basename: " . $phpfile . "\n";

        include_once( $phpfile );

        $feeder_class = array();
        $feeder_hash = array();

        if(array_key_exists('key', $yfeed)) {
            if(!array_key_exists('value', $yfeed['key'])) {
                $yfeed['key']['value'] = array('default');
            } 
        } else {
            $yfeed['key'] = ['value'=>array('default')];
        }
        // print_r($yfeed);

        foreach($yfeed['key']['value'] as $fkey) { 
            
            // echo "generating class `$schemaClass`\n";
            
            // check to see if a data field is an array, if it is
            // converit to json data
            foreach($ydata['data'][$fkey] as $fldkey => $fld) {
                if(is_array($fld)) {
                    // echo("Loading array in key: $fkey => " . print_r($fld,1));
                    $ydata['data'][$fkey][$fldkey] = json_encode($fld, JSON_UNESCAPED_UNICODE);
                    // echo(" ==> converted to json ==> " . $ydata['data'][$fkey][$fldkey] . "\n");
                }
            }

            $feeder_class[$fkey] = new $schemaClass( $ydata['data'][$fkey] );


            // print_r($feeder_class[$fkey]);

            ksort($ydata['data'][$fkey]);
            // echo"ordered ydata['data']\n";
            // print_r($ydata['data'][array_key_first($ydata['data'])]);
            
            // incoming data hash
            $feeder_hash[$fkey] = hash('sha256', 
                                    serialize( $ydata['data'][$fkey] )
            );
        }

        DIR::$fw = DIR::$app . '/web/core';
        include_once(DIR::$app . '/config/db.php');
        require(DIR::$fw . "/bootstrap.php");
        // echo("including bootstrap from " . DIR::$fw . "\n");
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        dbConnection::Connect();

        // echo("Classes: " . print_r(get_declared_classes(), 1));

        foreach($yfeed['key']['value'] as $fkey) { 
            // echo("Feeder key: $fkey  name: " . $feeder_class[$fkey]->getname() . "   GUID: " . $feeder_class[$fkey]->getguid() . " HASH: " . $feeder_hash[$fkey] . "\n");
            // echo(print_r($feeder_class, 1));
        
            if(($fh=feedhashesClassEx::gethashByGuid($feeder_class[$fkey]->getguid()))) {
                echo "$name: " .$feeder_class[$fkey]->getname() . "\t[$fkey]\tGUID exists!";
                // print_r($fh);
                if($fh->gethash() != $feeder_hash[$fkey]) {
                    echo "\thashes differ!\n";

                    // echo "new data\n";
                    // print_r( $feeder_class[$fkey] );

                    $old_feeder_class = $schemaClass::sgetById($fh->getfeedid());
                    // echo "found old class: " . print_r($old_feeder_class, 1);

                    // echo "old data\n";
                    // print_r($old_feeder_class);

                    $fld = $feeder_class[$fkey]->getFields();
                    // print_r( $fld );

                    $old_feeder_class->loadFields( $fld );
                    $old_feeder_class->update();

                    // remove 'id' key
                    if(isset($fld['id']))unset($fld['id']);
                    ksort($fld);
                    // print_r($fld);

                    $hash2 = hash('sha256', serialize( $fld ));
                    echo("new hash: " . print_r($hash2, 1) . "\n");

                    $fh->sethash( $hash2 );

                    $fh->update();

                    // $old_feeder_class->loadFields($feeder_class)
                } else {
                    echo "\thashes same!\n";
                }

            } else {
                echo("adding " . $feeder_class[$fkey]->getname() . "\t[$fkey]\n" );
                
                // echo("Inserting feeder_class[ $fkey ] = " . print_r($feeder_class[$fkey], 1));
                $feeder_class[$fkey]->insert();
                $fexp = time() + 1 * 24 * 60 * 60;
                
                $fhash = new feedhashesClass(['guid' => $feeder_class[$fkey]->getguid(),
                    'hash' => $feeder_hash[$fkey], 
                    'feedclass' => $schemaClass,
                    'feedid' => $feeder_class[$fkey]->getid(),
                    'expiry' => date('Y-m-d H:i:s', $fexp)]
                );
                $fhash->insert();
            }
        }

    }
}




function export_data($afile) {
    global $yaml_dir;

    if(!DIR::$app)require_option('app-dir');

    $temp = tempnam('/tmp', 'export-');

    $yfile = $yaml_dir . '/' . $afile . '.yaml';
    echo "yaml file: $yfile\n";

    $yinfo = yaml_parse_file($yfile)[0];

    // print_r( $yinfo );

    $args = "-y --compact --skip-extended-insert --no-create-info --skip-comments ";
    $cmd = DIR::$app . "/sql/msqldump.sh " . $args . $yinfo['table'];
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


// $dir can be fw/ or web/
function tables_action($action, $target = null) {
    if(!DIR::$app)require_option('app-dir');
    $cwd = posix_getcwd();
    // echo "Current dir: $cwd\n";
    
    chdir(DIR::$app);
    // echo "Current dir: " . posix_getcwd() . "\n";


    $args = "| grep CREATE - | cut -d\` -f2 -";
    $cmd = "./sql/msqldump.sh " . $args;
    // echo "$cmd\n";
    $res = shell_exec( $cmd );

    $tables0 = explode("\n", $res);
    $tables = array();
    foreach($tables0 as $el)
        if(strlen($el))$tables[] = $el;

    // print_r($tables);


    $fw_sql0 = glob('./web/core/classes/sql/*.sql');
    $web_sql0 = glob('./web/classes/sql/*.sql');

    // print_r($fw_sql0);
    // print_r($web_sql0);
    
    $fw_sql = array();
    array_walk($fw_sql0, function(&$v, $k)  {
        // print_r($v);
        if(strlen($v)) {
            $t = explode('/', $v);
            // print_r($t);
            $tt = $t[array_key_last($t)];
            $ttt = explode('.', $tt);
            // print_r($ttt);
            if(count($ttt)>0)
                $v = $ttt[0];
            // explode('.', $tt)[0];
        }
        // return explode('.', $tt)[0];
    });
    // print_r($fw_sql0);

    $web_sql = array();
    array_walk($web_sql0, function(&$v, $k)  {
        // print_r($v);
        if(strlen($v)) {
            $t = explode('/', $v);
            // print_r($t);
            $tt = $t[array_key_last($t)];
            $ttt = explode('.', $tt);
            // print_r($ttt);
            if(count($ttt)>0)
                $v = $ttt[0];
            // explode('.', $tt)[0];
        }
        // return explode('.', $tt)[0];
    });
    // print_r($web_sql0);
    // echo "tables in database\n";
    // print_r($tables);

    // echo "tables in fw\n";
    // print_r($fw_sql0);

    $tables1 = array_diff($tables, $fw_sql0);
    $fw_sql1 = array_intersect($tables, $fw_sql0);
    $fw_sql2 = array_diff($fw_sql0, $fw_sql1);
    
    // echo "table list after removing fw tables\n";
    // print_r($tables1);

    // echo "tables remained in fw\n";
    // print_r($fw_sql2);
    
    

    // echo "tables in database after removing fw\n";
    // print_r(array_values($tables1));
    $tables1 = array_values($tables1);
    // print_r($tables1);
    // echo "tables in web\n";
    // $web_sql0 = array_flip($web_sql0);
    // print_r($web_sql0);
    
    $tables2 = array_diff($tables1, $web_sql0);
    // $tables2 = $tables1 - $web_sql0;
    $web_sql1 = array_intersect($tables1, $web_sql0);

    $web_sql2 = array_diff($web_sql0, $web_sql1);
    // echo "common tables in database and web\n";
    // print_r($web_sql1);
    
    // echo "tables in database after removing web\n";
    // print_r($tables2);

    // echo "tables remained in web\n";
    // print_r($web_sql2);
    
    // $fw_sql = array_flip($fw_sql0);
    // print_r($fw_sql);
    // foreach($fw_sql0 as $val) {
    //     print_r( array_pop(explode('/', $val)));
    //     array
    // }
    // print_r($tables);

    switch($action) {
        case 'list':
            switch($target) {
                case 'fw':
                    foreach($fw_sql0 as $el)echo("$el\n");
                    break;
                case 'web':
                    foreach($web_sql0 as $el)echo("$el\n");
                    break;
                case 'all':
                    foreach($fw_sql0 as $el)echo("$el\n");
                    foreach($web_sql0 as $el)echo("$el\n");
                    break;
            }
            break;
        case 'new':
            switch($target) {
                case 'fw':
                    foreach($fw_sql2 as $el)echo("$el\n");
                    break;
                case 'web':
                    foreach($web_sql2 as $el)echo("$el\n");
                    break;
                case 'all':
                    foreach($fw_sql2 as $el)echo("$el\n");
                    foreach($web_sql2 as $el)echo("$el\n");
                    break;
            }
            break;
        case 'missing':
            foreach($tables2 as $el)echo("$el\n");
            break;

    }

    // echo("Tables to be added in database from fw\n");
    // print_r($fw_sql2);

    // echo("Tables to be added in database from web\n");
    // print_r($web_sql2);

    // echo("Tables to be dropped from database\n");
    // print_r($tables2);

    chdir($cwd);
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
                'gen:form:array' => 'generate FORM array from YAML',
                'gen:form:html' => 'generate FORM html from array',
                'form:load' => 'load form data to database',
                'form:view' => 'show form data from database',
                'form:view:html' => 'show form HTML data from database',
                'update:bootstrap' => 'update bootstrap for classes PHP file',
                'diff:sql' => 'show differences between YAML files and MySQL tables',
                'diff:sql:all' => 'show differences for all YAML files',
                'data:export' => 'export data from SQL database table to data folder',
                'content:gen' => 'generate content template',
                'content:view' => 'show content data',
                'feed:gen' => 'generate feeder template',
                'feed:gen:yaml' => 'generate feed templates from yaml file', 
                'feed:view' => 'show feed data',
                'feed:load' => 'load feed data to database',
                'feed:clean' => 'clean feed data from the database',
                'tables:list:fw' => 'dump database tables fw',
                'tables:list:web' => 'dump database tables web',
                'tables:list:all' => 'dump database tables from fw & web',

                // not yet implemented
                'tables:new:fw' => '[n/a] show tables from fw that have not been added to database, yet',
                'tables:new:web' => '[n/a] show tables from web that have not been added to database, yet',
                'tables:new:all' => '[n/a] show tables from fw & web that have not been added to database, yet',

                'tables:missing' => '[n/a] show database tables that are missing .yaml representation'
                
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
            
            echo("removing old sql files from $sql_dir/\n");
            shell_exec("rm -f $sql_dir/*.sql");
            
            
            echo("generating sql files in $sql_dir/\n");
            $files = get_yaml_files( $yaml_dir );
            foreach( $files as $yfile ) {
                echo("  ... " . pathinfo($yfile, PATHINFO_FILENAME ) . "\n");
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
            echo("removing old class files\n");
            $res = shell_exec("ls *.php | grep -v bootstrap_classes.php - | xargs rm -v");

            print_r($res);
            
            echo("generating class files\n");
            $files = get_yaml_files( $yaml_dir );
            foreach($files as $yfile ) {
                echo("  ... " . pathinfo($yfile, PATHINFO_FILENAME ) . "\n");

                spill_class($yaml_dir . '/' . $yfile, $class_dir);
            }
            break;
        case 'update:bootstrap':
            $files = get_class_files( $class_dir, $yaml_dir );
            $s = spill_bootstrap( $files );

            echo "Updating bootstrap file $bootstrap_file\n";
            file_put_contents($bootstrap_file, $s);
            break;

            // echo $s."\n";
        case 'diff:sql':
            $file = $optparams[1];
            // echo "Processing file: $file\n";
            diff_sql( $yaml_dir . '/' . $file );
            break;

        case 'diff:sql:all':
            $files = get_yaml_files( $yaml_dir );
            foreach($files as $yfile) {
                // echo "yfile: $yfile\n";
                diff_sql( $yaml_dir . '/' . $yfile );
            }
            break;

        case 'content:gen':
                // echo "options: " . print_r($cmdline_options, 1);
                // usage: content:gen content-name title
                if(!isset($options['name'])
                    || !isset($options['author'])
                    || !isset($options['type'])
                    || !isset($options['title'])
                    // && !isset($options['desc'])
                    // && !isset($options['viewmode'])
                    ) {
                        echo "Usage: --name [content-name] --author [author-created] --type [content-type] --title [title of content] {--desc [description of content]} {--viewmode [view mode]}\n";
                        exit;
                    }
                    if(!isset($options['viewmode']))$options['viewmode' ] = 'main';
                    if(!isset($options['desc']))$options['desc'] = $options['title'];

                    generate_content($options['author'], $options['type'], $options['name'], $options['title'], $options['desc'], $options['viewmode'] );
                    // echo "Creating content " . print_r($options, 1);
                    // exit;
            break;


        case 'content:view':
            $file = $optparams[1];
            echo "Showing content for file: $file\n";
            content_view( $file );
            break;

        // case 'feed:gen':
        //     // echo "options: " . print_r($cmdline_options, 1);
        //     // usage: content:gen content-name title
        //     if(!isset($options['template'])
        //         || !isset($options['name'])
        //         ) {
        //             echo "Usage: --template [yaml-template] --name [content-name] \n";
        //             exit;
        //         }

        //     generate_feed($options['template'], $options['name'] );
        //     // echo "Creating feeder " . print_r($options, 1);
        //     break;

        case 'feed:gen:yaml':
            if(!isset($options['name'])
                // || !isset($options['dir']
            )
             {
                echo "Usage: --name [feeder yaml template] [--dir [class yaml dir]] [--update key1[|key2|...]]\n";
                exit;
            }
            $arr = array();
            if(isset($options['update'])) {
                $arr = explode(':', $options['update']);
                echo "Force updating records : " . implode(' : ', $arr) . "\n";
            }

            generate_feed_from_yaml($options['name'], $options['dir']??null, $arr);
            break;

        case 'feed:load':
            if(!isset($options['name'])) {
                echo "Usage: --name [feeder yaml template]\n";
                exit;
            }

            load_feed_data($options['name']);
            break;

        case 'feed:clean':
            if(!isset($options['name'])
                // || !isset($options['dir'])
            ) {
                echo("Usage: --name [feeder yaml template] [--dir [class yaml dir]]\n");
                exit;
            }
            clean_feed_data($options['name'], $options['dir']);

            break;

        case 'data:export':

            // be sure the sql directory exists, otherwise create it
            makesure_dir_exists( $data_dir );
            $file =  $optparams[1];
            export_data($file);
            break;

        case 'tables:list:fw':
            tables_action('list', 'fw');
            break;
        case 'tables:list:web':
            tables_action('list', 'web');
            break;
        case 'tables:list:all':
            tables_action('list', 'all');
            break;
        case 'tables:new:fw':
            tables_action('new', 'fw');
            break;
        case 'tables:new:web':
            tables_action('new', 'web');
            break;
        case 'tables:new:all':
            tables_action('new', 'all');
            break;
        case 'tables:missing':
            tables_action('missing');
            break;

        case 'gen:form:array':
            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            if(!file_exists($file)) {
                echo("File $file does not exist!");
                exit(-1);
            }

            $yamlData = yaml_parse_file( $file);
            if(isset($yamlData['form'])) {
                // $form = generateHTMLForm( $yamlData );
                // $form = generateHTMLForm0( $yamlData );
                $formarray = generateHTMLFormArray( $yamlData );
                print_r( $formarray );

            } else echo ('No form data'. PHP_EOL);

            break;

        case 'gen:form:html':

            if(!DIR::$fw || !DIR::$app) {
            }

        // include webform sources
            require_once(DIR::$fw . "/bootstrap.php");
            
            $kernel = new Kernel(['MAKER_INVOKE' => true, 'PHP_SELF' => __FILE__, 'SCRIPT_FILENAME' => __FILE__], DIR::$app . "/config");
            Renderer::init([DIR::$fw. '/templates']);
            Renderer::$enable_comments = true;

            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            if(!file_exists($file)) {
                echo("File $file does not exist!");
                exit(-1);
            }
            $yamlData = yaml_parse_file( $file);
            if(isset($yamlData['form'])) {
                $formarray = generateHTMLFormArray( $yamlData );
                // echo("<!-- "); print_r( $formarray ); echo(" --!>");
                $form = generateHTMLForm( $formarray );
                print_r( $form );

            } else echo ('No form data'. PHP_EOL);

            break;

        case 'form:load':
            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            if(!file_exists($file)) {
                echo("File $file does not exist!");
                exit(-1);
            }

            $yamlData = yaml_parse_file( $file );
            if(isset($yamlData['form'])) {
                // $form = generateHTMLForm( $yamlData );
                // $form = generateHTMLForm0( $yamlData );
            form_load( $yamlData );

                //$formarray = generateHTMLFormArray( $yamlData );
                //print_r( $formarray );

            } else echo ('No form data'. PHP_EOL);

            break;

        case 'form:view':
            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            if(!file_exists($file)) {
                echo("File $file does not exist!");
                exit(-1);
            }

            $yamlData = yaml_parse_file( $file );
            if(isset($yamlData['form'])) {
                // $form = generateHTMLForm( $yamlData );
                // $form = generateHTMLForm0( $yamlData );
            form_view( $yamlData );

                //$formarray = generateHTMLFormArray( $yamlData );
                //print_r( $formarray );

            } else echo ('No form data'. PHP_EOL);

            break;

        case 'form:view:html':
            if(!$optparams[1]) {
                mlog('Usage: ' . $argv[0] . ' db-schema.yaml  file-to-process');
                exit;
            }
            $file = $optparams[1];
            if(!file_exists($file)) {
                echo("File $file does not exist!");
                exit(-1);
            }

            $yamlData = yaml_parse_file( $file );
            if(isset($yamlData['form'])) {
                // $form = generateHTMLForm( $yamlData );
                // $form = generateHTMLForm0( $yamlData );
            form_view_html( $yamlData );

                //$formarray = generateHTMLFormArray( $yamlData );
                //print_r( $formarray );

            } else echo ('No form data'. PHP_EOL);

            break;

        case 'gen:form:all':
            break;

        default:
            echo "Unknown command\n";
            exit;
    }