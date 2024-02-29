<?php

// content base class

class ContentClass {
    private $filename;
    private $content;
    private $meta;
    function __construct($afile) {
        echo $afile;
        $this->content = file_get_contents($afile);
        $str = explode('---', $this->content);
        echo "<pre>";
        print_r( $str );
        echo "</pre>";
        
        $this->meta = yaml_parse( $this->content );
        echo($this->meta);
        

        if($this->content) {
            $this->filename = $afile;
        }
    }

    function render() {
        // echo $conten
    }
}

