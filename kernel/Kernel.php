<?php

// this is the Kernel Class
function kernel_debug($dbg) {
    global $footer_message;
    $footer_message .= '<br/>' . $dbg;
}

class Kernel {
    private static $rootpath = null;
    private static $config = null;

    private static $modules = array();

    function __construct($asrv, $ainfo) {
        self::$rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        self::$config = $ainfo;
    }

    static function getrootpath() {
        return (self::$rootpath);
    }

    static function relative_url($apath) {
        if($apath == '/')
            $apath = '';
      return ( self::$rootpath . $apath);
    }

    static function getConfig() {
        return (self::$config);
    }

    static function getRoutes() {
        return (self::$config['routes']);
    }

    static function getBlocksInRegion($aregion) {
        $blks = self::$config['structure'];
        // print_r( $blks[$aregion] );
      return ($blks[$aregion]);
    }

    static function registerModule($amod) {
        echo "kernel: registering new module " . $amod->getName() . "<br/>";
        self::$modules[$amod->getName()] = $amod;
    }

    static function getModule($amodulename) {
        echo "requesting module with name " . $amodulename;
        if(isset(self::$modules[ $amodulename ])) {
            echo " Found!\n";
            return( self::$modules[ $amodulename ] );
        }
        else {
            echo " Not found!\n";
            return null;
        }
    }
}
