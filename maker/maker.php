<?php
    // include( getcwd() . "/../../config/db.php");
    // echo "cwd(): " . getcwd() . "\n";
    $dnames = explode('/' , getcwd());
    // print_r( $dnames );

    for($i=count($dnames);$i>0;$i--) {
        $dir = implode('/',array_slice($dnames, 0, $i));
        if(file_exists($dir . '/config')) {
            // echo "$dir/config exists!";
            define('__APPDIR__', $dir);
            define('__FWDIR__', $dir . '/fw');
            break;
        } else
        if(file_exists($dir . '/bootstrap.php')) {
            define('__FWDIR__', $dir);
            break;
        }
    }

    // if(defined('__APPDIR__'))echo 'app dir: ' . __APPDIR__ . "\n";
    // if(defined('__FWDIR__'))echo 'fw dir: ' . __FWDIR__ . "\n";


    if(defined('__APPDIR__')) {
        // echo __APPDIR__ . '/config/db.php' . "\n";
        include(__APPDIR__ . '/config/db.php');
    }
    

    function mlog($s, $nl = true, $li=false) {
        if($li)echo __FILE__."(".__FUNCTION__."):".__LINE__.": ";
        echo $s . ($nl?"\n":"");
    }


    function guid() {
        return (trim(file_get_contents('/proc/sys/kernel/random/uuid')));
    }
    


    $getopt_options_short = "f:";
    $getopt_options_long = array(
        'app-dir:',         // application directory
        'add-id',           // add 'is' field (default: yes)
        'extends-class:',   // class name to extend
        'name:',            // class name to create
        'type:', 'author:', 'title:', 'desc:', 'viewmode:',     // content fields
        'template:',        // YAML template to create feeder
        'dir:',             // YAML templates folder
        'key:'              // feeder key field to create feed yaml files
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

    function __construct($ayaml) {
        foreach($ayaml as $table) {
            if(isset($table)) {
                // mlog("table set");
                
                $this->name = $table['table'];
                // mlog("table name: " . $this->name);
                
                $this->classname = $table['class'];
                if(isset($table['extends']))
                    $this->extends = $table['extends'];
                else $this->extends = null;

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
        $res = shell_exec(__APPDIR__ . "/sql/msql.sh < " . $temp);
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
    // echo "# Listing of ".$dir . '/' . " folder.\n";
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
    // echo "emit SQL data in ". $sqldir . '/' . $dbinfo[0]['table']. ".sql\n";
    file_put_contents($sqldir . '/' . $dbinfo[0]['table'].'.sql',$s);
}


function spill_class($sqlfile, $classdir) {
    $dbinfo = yaml_parse_file( $sqlfile );
    // print_r( $dbinfo );

    $db = new Database( $dbinfo );
    $s = $db->emitClass();

    if(isset($dbinfo[0]['extention'])) {
        $s .= "\n\n";
        $s .= "require_once( " . $dbinfo[0]['extention'] . " );\n";
    }
        
    // echo "emit CLASS data in ". $classdir . '/' . $dbinfo[0]['table']. ".php\n";
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

    if(!defined('__APPDIR__'))require_option('app-dir');

    $tableinfo =  yaml_parse_file( $file );
    
    $db = new Database($tableinfo);
    $s = $db->emitSqlDiff();

    echo $s;
}

function generate_content($author, $type, $name, $title, $desc, $viewmode) {

    $s = '';
    $s .= "guid: " . guid() . "\n";
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

function generate_feed($template, $name, $key, $output = null, $specials_list = array()) {

    // echo $template;
    $arr = array();

    $arr += ['cmd' => '"' . implode(' ', $GLOBALS['argv'])  . '"' ];
    $arr += ['directory' => getcwd()];
    $arr += ['createdate' => date ('d-m-Y H:i:s')];

    $arr += ['schema' => realpath( $template )];


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
            
            include_once( dirname(dirname(realpath($template))).'/'.$tem[0]['table'].'.php');
            $cl = new $tem[0]['class']();
            $fields = $cl->getFields();
            // print_r($fields);

            foreach($key['value'] as $optval) {
                $arr['data'][$optval] = array();

                foreach($fields as $fkey => $fval) {
                    if((isset($specials_list['guid'])) && in_array($fkey, $specials_list['guid'])) {
                            $g = guid();
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
                        echo "prepopulate $fkey\n";
                        $arr['data'][$optval] += [$fkey => $specials_list['prefeed'][$fkey]];
                    }
                    if($fkey == $key['name']) {
                        $arr['data'][$optval] += [$fkey => $optval];

                    } else {
                        $arr['data'][$optval] += [$fkey => null];
                    }
                    // }
                }

                echo "opt key val: $optval\n";
            }
        }
    }

    // print_r($arr);
    // print_r( yaml_emit($arr));


    if(!$output) {

        echo "\n# output: $name.yml\n";
    } else {

        if(file_exists($output)) {
            $in = yaml_parse_file($output);
            $out = array_replace_recursive($arr, $in);
            // print_r($in);
            // print_r($out);
        } else $out = $arr;
        // file_put_contents($output, $s);
        yaml_emit_file($output, $out, YAML_UTF8_ENCODING);
        echo "output: $output\n";
    }
}

function generate_feed_from_yaml($name, $dir) {
    $yfeed = yaml_parse_file($name);
    // print_r( $yfeed );
    // echo "yaml path " . $dir. "\n";
    // echo "Real path: " . realpath($name) . "\n";
    // $dir = trim($dir, " /\\");

    $ytemplate = $dir . '/' . $yfeed['schema'];

    if(isset($yfeed['key'])) {
        $key = $yfeed['key'];
    } else $key = null;

    print_r($key);

    $specials_list = array();
    $specials_keys = ['guid', 'date', 'prefeed', 'sequential'];
    foreach($specials_keys as $skey) {
        echo "key prepopulates for $skey\n";
        if(isset($yfeed[ $skey ]))$specials_list[ $skey ] = $yfeed[ $skey ];
    }

    $idx = 0;
    if(isset($yfeed['order'])) {
        foreach($yfeed['order'] as $feeder) {
            $specials_list['__index'] = $idx++;
            echo "#Feeder $feeder ... \n";
            generate_feed($ytemplate, $feeder,
            $key,
            $yfeed['source'][0] . '/' . $feeder . '.yml',
            $specials_list);
        }
    }
}

if(!defined('DB_HOST')) {
    // define some dummy constants
    define('DB_HOST', '');
    define('DB_USER', '');
    define('DB_PASS', '');
    define('DB_NAME', '');
}

include(__DIR__ . '/../db/dbal.php');
include(__FWDIR__ . '/classes/feed_hashes.php');
include(__FWDIR__ . '/classes/feed_class.php');

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
        $yschema = yaml_parse_file( $ydata['schema'])[0];
        $schemaClass = $yschema['class'];

        // echo('schema y-file: '. print_r($yschema, 1));
        // echo('schema class: ' . $schemaClass . "\n");

        $pinfo = pathinfo($ydata['schema']);
        $phpfile = dirname($pinfo['dirname']) . '/' . $pinfo['filename'] . '.php';
        // echo "basename: " . $phpfile . "\n";

        include_once( $phpfile );

        $feeder_class = array();
        $feeder_hash = array();

        foreach($yfeed['key']['value'] as $fkey) { 
            
            // echo "generating class `$schemaClass`\n";
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

        foreach($yfeed['key']['value'] as $fkey) { 
            // print_r($feeder_class);
            // echo("Feeder key: $fkey  name: " . $feeder_class[$fkey]->getname() . "   GUID: " . $feeder_class[$fkey]->getguid() . " HASH: " . $feeder_hash[$fkey] . "\n");
        
            if(($fh=feedhashesClassEx::gethashByGuid($feeder_class[$fkey]->getguid()))) {
                echo "GUID exists!";
                // print_r($fh);
                if($fh->gethash() != $feeder_hash[$fkey]) {
                    echo "hashes differ!\n";

                    echo "new data\n";
                    print_r( $feeder_class[$fkey] );

                    $old_feeder_class = $schemaClass::sgetById($fh->getfeedid());
                    // echo "found old class: " . print_r($old_feeder_class, 1);

                    echo "old data\n";
                    print_r($old_feeder_class);

                    $fld = $feeder_class[$fkey]->getFields();
                    // print_r( $fld );

                    $old_feeder_class->loadFields( $fld );
                    $old_feeder_class->update();

                    // remove 'id' key
                    if(isset($fld['id']))unset($fld['id']);
                    ksort($fld);
                    // print_r($fld);

                    $hash2 = hash('sha256', serialize( $fld ));
                    print_r($hash2 . "\n");

                    $fh->sethash( $hash2 );

                    $fh->update();

                    // $old_feeder_class->loadFields($feeder_class)
                } else {
                    echo "hashes same!\n";

                }

            } else {

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

    if(!defined('__APPDIR__'))require_option('app-dir');

    $temp = tempnam('/tmp', 'export-');

    $yfile = $yaml_dir . '/' . $afile . '.yaml';
    echo "yaml file: $yfile\n";

    $yinfo = yaml_parse_file($yfile)[0];

    // print_r( $yinfo );

    $args = "-y --compact --skip-extended-insert --no-create-info --skip-comments ";
    $cmd = __APPDIR__ . "/sql/msqldump.sh " . $args . $yinfo['table'];
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


    if(isset($options['app-dir']))define('__APPDIR__', $options['app-dir']);

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
                'content:gen' => 'generate content template',
                'content:view' => 'show content data',
                'feed:gen' => 'generate feeder template',
                'feed:gen:yaml' => 'generate feed templates from yaml file', 
                'feed:view' => 'show feed data',
                'feed:load' => 'load feed data to database'
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
                || !isset($options['dir'])
            ) {
                echo "Usage: --name [feeder yaml template] --dir [yaml template dir] [--key keyname,key1:key2:]\n";
                exit;
            }

            generate_feed_from_yaml($options['name'], $options['dir']);
            break;

        case 'feed:load':
            if(!isset($options['name'])) {
                echo "Usage: --name [feeder yaml template]\n";
                exit;
            }

            load_feed_data($options['name']);
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