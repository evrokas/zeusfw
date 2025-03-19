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

class contentPageClass {
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

    static function getFileContents($file): string|false {
        if(array_key_exists($file, self::$content_files)) {
            if(file_exists(self::$content_files[ $file ])) {
                return file_get_contents( self::$content_files[ $file ] );
            } else return "ERROR: content for file $file not found";
        } else return "ERROR: file $file does not exist";
    }

    static function getContent($file) {
        global $kernel;

        $content = "";
        $content .= "<!-- begin content (file: " . $file . ") -->";

        if($kernel->safeGetConfig('page_content_info')) {
            $content .= Renderer::renderSafe('contentpage-info.zetem', [
                                                'filename' => $file,
                                                'filepath' => (self::existsContent( $file )) ? self::$content_files[ $file ] : null
            ]);
        }

        $content .= self::getFileContents($file);
        $content .= "<!-- end content (file: " . $file . ") -->";
        
        // echopre("htmlContent contents {" . $content . "}");
        return ($content);
    }
    
}   /* end class definition */
