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
    protected $config = null;       // framework configuration
    protected $siteconf = null;     // site configuration
    protected $modules = array();

    function __construct($asrv, $configdir) {
        $this->rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        $this->basepath= substr($asrv['SCRIPT_FILENAME'],0,strrpos($asrv['SCRIPT_FILENAME'],'/')+1);

        $configdir = rtrim($configdir, '/');

        // echo "<pre>basepath: " . $this->basepath . "</pre>";
        if(!file_exists($configdir . '/zeusconf.info.yaml')) {
            echopre("Configuration file does not exist. Please contact administrator.");
            ob_flush();
            exit();
        }
        $this->config = yaml_parse_file($configdir . '/zeusconf.info.yaml');
        
        if(file_exists($configdir . '/site.info.yaml')) {
            $this->siteconf = yaml_parse_file($configdir . '/site.info.yaml');
            // echopre(print_r($c=$this->getSiteConfig(), 1));
        } else {
            $this->siteconf = array();
        }
        
        // echo "<pre>";
        // print_r( $this->config );
        // echo "</pre>";

        date_default_timezone_set( $this->safeGetConfig('tz') );
    }

    function getSiteConfig($section=null) {
        if($section) {
            if(isset($this->siteconf[$section]))
              return $this->siteconf[$section];
            else return array();
        } else
            return ($this->siteconf);
    }

    function getConfig($section=null) {
        if($section) {
            if(isset($this->config[$section]))
              return ($this->config[$section]);
            else return array();
        } else
            return ($this->config);
    }

    function safeGetConfig($section) {
        if(isset($this->config[ $section ]))return $this->config[ $section ];
        else return '';
    }

    function safeGetConfigValue($section, $key) {
        if((isset($this->config[$section])) &&
        (array_key_exists($key, $this->config[$section]))) {
            return ($this->config[$section][$key]);
        } else return '';
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
        if(key_exists($aregion, $blks))
            return $blks[$aregion];
        else return null;
    //   return ($blks[$aregion]);
    }


    function findMenuKey($amenu, $menukey) {
        // echopre("findMenuKey: $menukey");
        // echopre(print_r($amenu, 1));
        
        foreach($amenu as $menuitemkey => $menuitemrecord) {
            // echopre("== $menuitemkey : " . array_key_first($menuitemrecord));  //) print_r($menuitemrecord, 1));
            if(key_exists($menukey, $menuitemrecord)) {
                // echopre("** key found **");
                // echopre(print_r($menuitemrecord, 1));

                // if(key_exists('submenu', $menuitemrecord))
                //     return $menuitemrecord['submenu'];
                // else
                    return $menuitemrecord;

            } else if(key_exists('submenu', $menuitemrecord)) {
                $ret = $this->findMenuKey($menuitemrecord['submenu'], $menukey);
                if($ret)return $ret;
            }
        }
        return null;
    }

    function getMenu($asection = null, $alevel = 0) {
        $config_menu = $this->getConfig('menu');
        // echopre( print_r($config_menu,1) );
        if(!$asection)$menu = $config_menu;
        else {
            if(isset($config_menu[$asection]))$menu = $config_menu[$asection];
        }

        // echopre(print_r($menu, 1));
        return ($menu);
    }


    function registerModule($amod ) {
        // echo "kernel: registering new module " . $amod->getName() . "<br/>";
        $this->modules[$amod->getName()] = $amod;
    }

    function resolveModuleDir($info, $adir, $amodule) {
        // echopre("SERVER: ". print_r($_SERVER, 1));
        // echopre("module dir: " . $adir);
        // echopre("pre resolve: " .  print_r($info, 1) );
        $aadir = substr(dirname($adir), strlen($_SERVER['DOCUMENT_ROOT'])+strlen(dirname($_SERVER['PHP_SELF']))).'/'.$amodule;
        return ( recursive_array_replace("@", $aadir, $info) );
        // echopre("post resolve: " . print_r( $info, 1) );
    
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
        // Renderer::view("main.zetem", $kernel->getConfig() );
        // registerModules();
        
        $regions_resp = array();

        // $cont = new ContentClass('content/homepage.html');

        foreach($this->getConfig()['regions'] as $region) {
            // echo "<pre>Region " . print_r( $region, 1 ) . "</pre>";
            $blocks = $this->getBlocksInRegion( $region );
            // print_r( $blocks );
            $blk_resp = '';
            if($blocks)
                foreach($blocks as $block) {
                    $blk = $this->getModule( $block );
                    // echopre("Calling module->render() for block " . print_r( $blk, 1). "<br/>");
                    if($blk) {
                        $bresponse = $blk->render();
                        // echopre("Reponse from module: " .print_r( $blk, 1) . " : << $bresponse >><br/>");
                        $blk_resp .= $bresponse;
                    }
                }
                // echo "Region response text " . $blk_resp;
            // $regions_resp[ $region ] = $blk_resp;
            $sugg = array();
            Renderer::getTemplateSuggestions(['type' => 'region', 'name' => $region], function($args, &$suggestions) {
                // echopre(print_r($args, 1));
                if($args['type'] == 'region') {
                    $suggestions[] = 'region';
                    $suggestions[] = 'region--' . $args['name'];
                }
            }, $sugg);

            // echopre(print_r($sugg, 1));

            $temp = Renderer::getTemplate($sugg);
            // echopre("found template: $temp : blk_resp size (bytes) " . strlen($blk_resp));
            
            if(strlen($blk_resp))
                $regions_resp[ $region ] = Renderer::render($temp /*'region.zetem'*/, ['region_name' => $region, 'blocks' => $blk_resp], [$sugg, $temp]);
        }

        Renderer::view('main.zetem', 
        [
            'title' => $this->getConfig('title'),
            'meta' => $this->getConfig('meta'),
            'css' => $this->getConfig('css'),
            'fonts' => $this->getConfig('fonts'),
            'head_links' => $this->getConfig('head_links'),
            'head_script' => $this->getConfig('head_script'),
            'foot_script' => $this->getConfig('foot_script'),
            'regions' => $regions_resp
        ]);
    
    }

    // status can be: error || warning || notice
    function addStatus($level, $statusMessage) {
        if(!isset($_SESSION[ $level ]))
            $_SESSION[ $level ] = array();
        $_SESSION[ $level ][] = $statusMessage;
        error_log("\nSet status: [$level]: $statusMessage\n");
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
        session_destroy();
        session_start();
//        session_regenerate_id();
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

    function isUserLoggedin(): bool {
        global $kernel;

        // uncomment the following line to force cookie search
        // unset($_SESSION['user']);

        if(isset($_SESSION['user'])) {
            return true;
        }

        // prelog('Trying to get user info from cookie');

        $token = filter_input(INPUT_COOKIE, 'zeusfwrememberme');    //, FILTER_SANITIZE_STRING);
        prelog("token from cookie: " . print_r($token, 1));
        if($token && userTokensClassEx::token_is_valid($token)) {
            prelog("Found valid token in DB, token: " . print_r($token, 1));

            $user = userTokensClassEx::getUserByToken($token);
            if($user) {
                $us = usersClassEx::getUserAccount( $user->getuname());
                prelog("Found user: " . print_r($user, 1) . " user record: " . print_r($us, 1));
                
                $kernel->loginUser($us->getuname(), $us->getroles());
                return true;
            }
        } else {
            prelog("token does not exist or is invalid");
        }
        return false;
    }

    function isAjaxRequest() {
        if(key_exists('HTTP_X_REQUESTED_WITH', $_SERVER))
            if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
                return true;

        return false;
    }

    function setCurrentLanguage($curlang) {
        $_SESSION['CURRENT_LANGUAGE'] = $curlang;
    }

    function getCurrentLanguage() {
        if(isset($_SESSION['CURRENT_LANGUAGE']))
          return $_SESSION['CURRENT_LANGUAGE'];
    }
}

function rel_url($p) {
    global $kernel;

    if(str_starts_with($p, 'http'))
        return ($p);

    return $kernel->rel_url($p);
}

function asset($file) {
    return rel_url('assets/' . $file);
}

function attributes($at) {
    if(is_object($at) && (get_class($at) === 'Attributes')) return $at->getAttributes();
    else if(is_array($at) && isset($at['attributes']))
        return ($at['attributes']->getAttributes());
    else return '';
}

function guid() {
    return (trim(file_get_contents('/proc/sys/kernel/random/uuid')));
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

function formatBrowserDate($str) {
    return (date("Y-m-d", strtotime($str)));
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

function recursive_array_replace ($find, $replace, $array) {
    if (!is_array($array)) {
        return str_replace($find, $replace, $array);
    }

    $newArray = [];
    foreach ($array as $key => $value) {
        $newArray[$key] = recursive_array_replace($find, $replace, $value);
    }
    return $newArray;
}

function inject_block($blockname, $content, $attrs = null) {
    $s = "<$blockname";
    if($attrs)
        $s .= " " . attributes($attrs);
    $s .= ">";
    $s .= $content;
    $s .= "</$blockname>";

    return $s;
}

function kindex($token) {
    $at = new Attributes("class", "index-token");
    return inject_block("span", $token, $at);

    // return "<span class=\"index\">".$token."</span>";
}


// if $tok is string, return that string,
// if $tok is an array, and current selected language is in array keys
//    then return value for that key, otherwise return value of first key
function getLangText($tok) {
  global $kernel;

    // echopre("token: " . print_r($tok, 1));
    if(is_array($tok)) {
        $lang = $kernel->getCurrentLanguage();
        if(key_exists($lang, $tok))return $tok[ $lang ];
        else return $tok[ array_key_first($tok) ];
    }

    if(is_string($tok))return $tok;

    return 'nolangtext';
}

function getConf($atok) {
  global $kernel;
 
      if(array_key_exists('config', $kernel->getConfig())) {
          if(array_key_exists($atok, $kernel->getConfig('config'))) {
              return $kernel->getConfig('config')[$atok];
          } else return null;
      } else return null;
}
