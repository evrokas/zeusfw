<?php

// this is the Kernel Class
function kernel_debug($dbg) {
    global $footer_message;
    $footer_message .= '<br/>' . $dbg;
}

class Kernel {
    private static $rootpath = null;
    private static $info = null;

    function __construct($asrv, $ainfo) {
        self::$rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        self::$info = $ainfo;
    }

    static function getrootpath() {
        return (self::$rootpath);
    }

    static function relative_url($apath) {
        if($apath == '/')
            $apath = '';
      return ( self::$rootpath . $apath);
    }

    static function getBlocks($aregion) {
        $blks = self::$info['structure'];
        // print_r( $blks[$aregion] );
      return ($blks[$aregion]);
    }
}
