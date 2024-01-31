<?php
    function mlog($s, $nl = true) {
        echo $s . ($nl?"\n":"");
    }


    // $inline_options[] = array();
    $inline_options['add-id'] = 'true';
    // $inline_options['extend-class'] = null;


class Database {
    private string $name;
    private $fields;

    function __construct($ayaml) {
        foreach($ayaml as $table) {
            if(isset($table)) {
                // mlog("table set");
                
                $this->name = $table['table'];
                // mlog("table name: " . $this->name);
                
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
        mlog('// class ' . $this->name);
        mlog("require_once('../db/dbal.php');\n");

        mlog('class ' . $this->name . (isset($options['extends-class'])?' extends ' . $options['extends-class']:"") . ' {');
        

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


    switch($optparams[0]) {
        case 'description':
            if(!$optparams[1]) {
                mlog('Please specify a file to process.');
                exit;
            }
            $file = $optparams[1];
            
            $dbinfo = yaml_parse_file( $file );
        
            // print_r( $dbinfo );
        
            $db = new Database( $dbinfo );
            $s = $db->emitDescription();
            echo $s;
            break;

        case 'class':
            if(!$optparams[1]) {
                mlog('Please specify a file to process.');
                exit;
            }
            $file = $optparams[1];
               
            $dbinfo = yaml_parse_file( $file );
         // print_r( $dbinfo );
                
            $db = new Database( $dbinfo );
            $db->emitClass();
            break;

    }