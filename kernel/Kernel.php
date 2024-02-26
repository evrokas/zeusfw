<?php

// this is the Kernel Class
function kernel_debug($dbg) {
    global $footer_message;
    $footer_message .= '<br/>' . $dbg;
}

class Kernel {
    protected $rootpath = null;
    protected $config = null;

    protected $modules = array();

    function __construct($asrv, $ainfofile) {
        $this->rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        $this->config = yaml_parse_file($ainfofile);
        // echo "<pre>";
        // print_r( $this->config );
        // echo "</pre>";
    }

     function getrootpath() {
        return ($this->rootpath);
    }

     function rel_url($apath) {
        if($apath == '/')
            $apath = '';

        $apath = str_replace('//', '/', $this->rootpath . $apath);

      return ( $apath);
    }

    function getConfig($section=null) {
        if($section) {
            if(isset($this->config[$section]))
              return ($this->config[$section]);
            else return array();
        } else
            return ($this->config);
    }

    function getRoutes() {
        return ($this->config['routes']);
    }

    function getBlocksInRegion($aregion) {
        $blks = $this->config['structure'];
        // print_r( $blks[$aregion] );
      return ($blks[$aregion]);
    }

    function registerModule($amod) {
        // echo "kernel: registering new module " . $amod->getName() . "<br/>";
        $this->modules[$amod->getName()] = $amod;
    }

    function getModule($amodulename) {
        // echo "requesting module with name " . $amodulename;
        if(isset($this->modules[ $amodulename ])) {
            // echo " Found!\n";
            return( $this->modules[ $amodulename ] );
        }
        else {
            // echo " Not found!\n";
            return null;
        }
    }

    function addStatus($level, $statusMessage) {
        if(!isset($_SESSION[ $level ]))
            $_SESSION[ $level ] = array();
        $_SESSION[ $level ][] = $statusMessage;
    }

    function getStatus($level, $clear = null) {
        if(isset($_SESSION[ $level ])) {
            $st = $_SESSION[ $level ];
            if($clear)
                $_SESSION[ $level ] = array();
            return $st;
        }
        else
            return array();
    }
}


function rel_url($p) {
    global $kernel;

    return $kernel->rel_url($p);
}

function attributes($at) {
    // $s = 'atrributes ' . print_r( $at, 1 ) . '<br>';
    $s = '';
    if(isset($at['attributes']))
        return ($s . $at['attributes']->getAttributes());
    else return $s;
}

