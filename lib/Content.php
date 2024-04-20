<?php

// content base class

class ContentClass {
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
        echopre( print_r($this->meta, 1) );

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
        global $Renderer;


        return $Renderer->render($this->template, $this->meta);
        // return $this->content;
    }
}

