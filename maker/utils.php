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

        ob_start();
        mlog('// class ' . $this->name);
        mlog('class ' . $this->name . (isset($options['extend-class'])?' extend ' . $options['extend-class']:"") . ' {');
        
            foreach($this->fields as $fld) {
                mlog('  private ' . $fld['name'] . ';');
            }

            
        mlog('};');
    }
}


// execute various components of the framework

    $getopt_options_short = "f:";
    $getopt_options_long = ['add-id', 'extend-class:'];
    $i=0;
    $optparams = array();
    
    $cmdline_options = getopt($getopt_options_short, $getopt_options_long, $i);
    $options = array_merge($inline_options, $cmdline_options);
    print_r($options);

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
            $s = $db->emitClass();
            echo $s;
            break;

    }