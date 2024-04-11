<?php

// this is the Kernel Class
function kernel_debug($dbg) {
    global $footer_message;
    $footer_message .= '<br/>' . $dbg;
}

define("LOG_FILE", '/tmp/fwkernel.log');

function prelog(string $msg) {
    $f = fopen(LOG_FILE, "a");
    fwrite($f, $msg . "\n");
    fclose($f);
}


class Kernel {
    protected $rootpath = null;     // root directory relative to the webserver
    protected $basepath = null;     // base directory relative to the filesystem
    protected $config = null;

    protected $modules = array();

    function __construct($asrv, $ainfofile) {
        $this->rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        $this->basepath= substr($asrv['SCRIPT_FILENAME'],0,strrpos($asrv['SCRIPT_FILENAME'],'/')+1);

        // echo "<pre>basepath: " . $this->basepath . "</pre>";
        $this->config = yaml_parse_file($ainfofile);
        // echo "<pre>";
        // print_r( $this->config );
        // echo "</pre>";

        date_default_timezone_set( $this->safeGetConfig('tz') );
    }

    function safeGetConfig($key) {
        if(isset($this->config[ $key ]))return $this->config[ $key ];
        else return '';
    }

     function getrootpath() {
        return ($this->rootpath);
    }
     function getbasepath() {
        return ($this->basepath);
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
        if(!is_array($section)) {
            $addsection = yaml_parse($section);
        } else
            $addsection = $section;

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

    function renderPage() {
        // $Renderer->view("main.zetem", $kernel->getConfig() );
        // registerModules();
        
        $regions_resp = array();
        global $Renderer;

        // $cont = new ContentClass('content/homepage.html');

        foreach($this->getConfig()['regions'] as $region) {
            // echo "<pre>Region " . print_r( $region, 1 ) . "</pre>";
            $blocks = $this->getBlocksInRegion( $region );
            // print_r( $blocks );
            $blk_resp = '';
            foreach($blocks as $block) {
                $blk = $this->getModule( $block );
                // echo("Calling module->render() for block " . print_r( $blk, 1). "<br/>");
                if($blk) {
                    $bresponse = $blk->render();
                    // echo("Reponse from module: " .print_r( $blk, 1) . " : << $bresponse >><br/>");
                    $blk_resp .= $bresponse;
                }
            }
            // echo "Region response text " . $blk_resp;
            // $regions_resp[ $region ] = $blk_resp;
            $regions_resp[ $region ] = $Renderer->render('region.zetem', ['region_name' => $region, 'blocks' => $blk_resp]);
//            $Renderer->view('region.zetem', ['region_name' => $region, 'blocks' => $regions_resp[ $region ]]);
        }

        $Renderer->view('main.zetem', 
        [
            'title' => $this->getConfig('title'),
            'meta' => $this->getConfig('meta'),
            'css' => $this->getConfig('css'),
            'head_script' => $this->getConfig('head_script'),
            'foot_script' => $this->getConfig('foot_script'),
            'regions' => $regions_resp
        ]);
    
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
        error_log("\nroute_history: " . print_r($_SESSION['route_history'], 1));
    }

    function popRouteHistory() {
        if(!isset($_SESSION['route_history']))
            return null;
        error_log("\nroute_history: " . print_r($_SESSION['route_history'], 1));
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

    function isAjaxRequest() {
        if(key_exists('HTTP_X_REQUESTED_WITH', $_SERVER))
            if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
                return true;

        return false;
    }
}


function rel_url($p) {
    global $kernel;

    if(str_starts_with($p, 'http'))
        return ($p);

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

function getDBformattime($str) {
    return (date("Y-m-d H:i:s", strtotime($str)));
}
function formatDateTime($str) {
    return (date("d-m-Y H:i:s", strtotime($str)));
}

function formatDate($str) {
    return (date("d-m-Y", strtotime($str)));
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
}


function attach_library_helper($libname) {
    global $kernel;

    // error_log("attach library: " . $libname . "\n");

    $cnf = $kernel->getConfig();
    // echo "<pre>";print_r( $cnf['css'] );echo "</pre>";
    // echo "<pre>";print_r( $cnf['libraries'] );echo "</pre>";

    if(array_key_exists($libname, $cnf['libraries'])) {
        // echo "<pre>";print_r( $cnf['libraries'][$libname] );echo "</pre>";
        $kernel->addConfig($cnf['libraries'][$libname]);
    }

    // echo "<pre>";print_r( $kernel->getConfig('css'));echo "</pre>";
    // echo "<pre>";print_r( $kernel->getConfig('foot_script'));echo "</pre>";
    // exit();
}

function attach_library($libname) {
    if(is_array($libname)) {
        foreach($libname as $lib) {
            attach_library_helper( $lib );
        }
    } else {
        attach_library_helper( $libname );
    }
}