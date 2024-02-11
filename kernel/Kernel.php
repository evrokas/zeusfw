<?php

// this is the Kernel Class
function kernel_debug($dbg) {
    global $footer_message;
    $footer_message .= '<br/>' . $dbg;
}

class Kernel {
    private static $rootpath = null;

    function __construct($asrv) {
        self::$rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
    }

    static function getrootpath() {
        return (self::$rootpath);
    }

    static function relative_url($apath) {
        if($apath == '/')
            $apath = '';
      return ( self::$rootpath . $apath);
    }
}
