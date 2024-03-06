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

    function addConfig($section) {
        $addsection = yaml_parse($section);

        // echo "<pre>addConfig: " . print_r( $addsection, 1) . "</pre>";
        // echo "<pre>addConfig: " . print_r( $this->config, 1) . "</pre>";

        $this->config = array_merge_recursive($this->config, $addsection);

        // foreach($addsection as $sectionkey => $sectionval) {
        //     if(!isset($this->config[ $sectionkey ])) {
        //         $this->config[ $sectionkey ] = array();
        //     } 
        //     foreach($sectionval as $valkey => $valval) {
        //         $this->config[ $sectionkey ][ $valkey ] = $valval;
        //     }
        // }
        // echo "<pre>addConfig: " . print_r( $this->config, 1) . "</pre>";        
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
        // error_log("\nSet status: [$level]: $statusMessage\n");
    }

    function getStatus($level, $clear = null) {
        if(isset($_SESSION[ $level ])) {
            $st = $_SESSION[ $level ];
            if($clear)
                unset($_SESSION[ $level ]);
            //  = array();
            return $st;
        }
        else
            return array();
    }

    function pushRouteHistory($aroute) {
        if(!isset($_SESSION['route_history']))
            $_SESSION['route_history'] = array();
        array_push($_SESSION['route_history'], $aroute);
    }

    function popRouteHistory() {
        if(!isset($_SESSION['route_history']))
            return null;
        return ( array_pop($_SESSION['route_history']));
    }

    function hasRouteHistory() {
        if(!isset($_SESSION['route_history']))
            $_SESSION['route_history'] = array();
        return(count($_SESSION['route_history']));
    }

    function authenticateUser($auser, $apass) {
        // checks to see if user exists in the database,
        // if pass matches
        // if ok, then sets up variables in session

        $user = usersClassEx::getUser( $auser, $apass );
        if($user) {
            // user exists and password is a match
            echo "User exists!";
        }
    }

    function getUserName() {
        if(isset($_SESSION) && isset($_SESSION['user'])) {
            return ($_SESSION['user']);
        } else return null;
    }

    function getUserRoles() {
        if(isset($_SESSION) && isset($_SESSION['user']) && isset($_SESSION['user_roles'])) {
            return($_SESSION['user_roles']);
        } else return null;
    }

    function loginUser($uname, $uroles) {
        session_start();
        session_regenerate_id();
        $_SESSION['user'] = $uname;
        $urolelist = SecurityClass::processRoles($uroles);
        if(!$urolelist) {
            echo "<pre>User roles are initialized falsely. Please check!";
            exit();
        }
        $_SESSION['user_roles'] = $urolelist;
    }

    function logoutUser() {
        unset( $_SESSION['user'] );
        unset( $_SESSION['user_roles']);
        session_destroy();
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

function guid() {
    return (file_get_contents('/proc/sys/kernel/random/uuid'));
}

function getDBtime($atime = null) {
    if(!$atime)$atime=time();
    return (date ('Y-m-d H:i:s', $atime));  
}

function formatDate($str) {
    return (date("d-m-Y H:i:s", strtotime($str)));
}

function randomChar($str) {
    $len = strlen($str);
    return ($str[ random_int(1, $len-1)]);
}

function randomAlpha($nchars, $nwords = 1) {
    static $alpha = 'abcdefghhjklmnopqrstuvwxyz';
    
    $words = array();
    while($nwords--) {
        $n = random_int(1, $nchars);
        $s = '';
        while($n>0) {
            $s .= randomChar($alpha);
            $n--;
        }
        $words[] = $s;
    }

    return(implode(' ', $words));
}

function randomNumber($ndigits) {
    static $numbers = '0123456789';

    $s = '';
    while($ndigits--) {
        $s .= randomChar($numbers);
    }

    return ($s);
}

function randomAlnum($nchars, $nwords) {
    static $alpha = 'abcdefghhjklmnopqrstuvwxyz0123456789ABCDEFGHJKLMNOPQRSTUVWXYZ';
    
    $words = array();
    while($nwords--) {
        $n = random_int(1, $nchars);
        $s = '';
        while($n--) {
            $s .= randomChar($alpha);
        }
        $words[] = $s;
    }

    return(implode(' ', $words));
}

function randomEmail() {
    $s = randomAlpha(1) . randomAlnum(10, 1) . '@' . randomAlnum(8, 1) . '.' . randomAlnum(2, 1);

    return ($s);
}


function echopre($str) {
    echo "<pre>$str</pre>";
}


function module($astr, $params) {
    global $kernel;

    // echopre("Calling module: $astr");
    $mod = $kernel->getModule( $astr );
    // echopre(print_r($mod, 1));
    return ( $mod->run( $params ) );

    return("<h1><pre>Module: " . print_r($astr, 1) . "</pre></h1>");
}