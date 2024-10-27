<?php

/*
 * Create a content class that will be similar to ZETEMTemplate class
 * it will have a base content directory where all content is placed in 
 * there. Content is in plain .html notation.
 * 
 * Content will have a template associated.
 * Content will be language dependent.
 * Multiple alternative templates can exist to display in certain blocks 
 * or regions
 * 
 */ 

class htmlContentClass {
    // path to content files
    static $content_path = array();

    // array of content files, array key is content name, value is file path
    static $content_files = array();


    static function init($acontent_path) {
        if(isset($acontent_path)) {
            if(is_array($acontent_path))self::$content_path = $acontent_path;
            else array_push(self::$content_path, $acontent_path);
        }
        
        self::$content_files = array();
        foreach(self::$content_path as $cpath) {
            self::findContent($cpath, self::$content_files);
        }
    }

    static function findContent($apath, &$carr) {
        // $files = glob($apath . '*.html');

        $files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($apath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY);

        // print_r( $files );

        $dupl = 0;
        foreach($files as $fnam) {
            $f = explode('/', $fnam);
            // print_r( $fnam );
            if(isset( $carr[ $f[ array_key_last($f) ]])) {
                $dupl++;
            }
            $carr[ $f[ array_key_last($f)]] = $fnam->getPathName();
        }

        // print_r($carr);
        if($dupl > 0) {
            //
        }
    }

    static function existsContent($cname) {
        // echopre("existsContent: ". print_r($cname, 1));
        // echopre(self::$content_files);
        if(array_key_exists($cname, self::$content_files)) {
            return true;
        } else return false;
    }

    static function getContent($file) {
        $content = "";
        $content .= "<!-- begin content (file: " . $file . ") -->";
        $content .= file_get_contents(self::$content_files[ $file]);
        $content .= "<!-- end content (file: " . $file . ") -->";
        
        // echopre("htmlContent contents {" . $content . "}");
        return ($content);
    }
    
}   /* end class definition */


// content base class

class xContentClass {
    private $filename;
    private $content;
    private $meta;
    private $template;
    function __construct($afile) {
        global $kernel;
        // echo $afile;

        // echopre(print_r($kernel->getbasepath(), 1));
        $contents = file_get_contents($kernel->getbasepath() . $afile);
        $str = explode('---', $contents);
        // echopre( print_r($str, 1) );
        
        $this->meta = yaml_parse( $str[0] );
        // echopre( print_r($this->meta, 1) );

        if($str[1]) {
            $this->filename = $afile;
            $this->content = $str[1];
        }

        /*
         * {{guid}}.zetem
         * basic-{name}-{viewmode}.zetem
         * basic-{name}.zetem
         * basic-{viewmode}.zetem
         * basic.zetem
         * 
         */
        // 
        $this->template = $this->meta['contenttype'] . '.zetem';
    }

    function render() {
        return Renderer::render($this->template, $this->meta);
        // return $this->content;
    }
}

