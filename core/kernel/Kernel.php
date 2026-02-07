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
    protected $languageDetector = null;  // language detection service
    protected $multilingualManager = null;  // file-based translation manager

    function __construct($asrv, $configdir) {
        $this->rootpath= substr($asrv['PHP_SELF'],0,strrpos($asrv['PHP_SELF'],'/')+1);
        $this->basepath= substr($asrv['SCRIPT_FILENAME'],0,strrpos($asrv['SCRIPT_FILENAME'],'/')+1);

        $configdir = rtrim($configdir, '/');
        $this->config = array();

        // read framework configuration
        if(file_exists(__FWDIR__ . '/config/zeusfw.info.yaml')) {
            $conf = yaml_parse_file(__FWDIR__ . '/config/zeusfw.info.yaml');
        } else $conf = array();

        $this->addConfig( $conf );

        // read site-wide configuration
        if(file_exists($configdir . '/site.info.yaml')) {
            $conf = yaml_parse_file($configdir . '/site.info.yaml');
        } else {
            $conf = array();
        }

        // merge site-wide configuration
        $this->addConfig( $conf );


        // setup error displaying for site
        if($t=$this->getConfig('php_display_errors'))
            ini_set('display_errors', $t);

        if($t=$this->getConfig('php_display_startup_errors'))
            ini_set('display_startup_errors', $t);

        error_reporting(E_ALL);
        register_shutdown_function( "fatal_handler" );
        

        if(!file_exists($configdir . '/settings.info.yaml')) {
            echopre("Configuration file does not exist. Please contact administrator.");
            ob_flush();
            exit();
        }
        $conf = yaml_parse_file($configdir . '/settings.info.yaml');
        
        // merge project configuration
        $this->addConfig( $conf );
        
        // echo "<pre>";
        // print_r( $this->config );
        // echo "</pre>";

        date_default_timezone_set( $this->safeGetConfig('tz') );


        // initialize databse connection
        dbConnection::init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if(!dbConnection::Connect()) {
            echo "Could not connect to the database. Fatal error. Please contact administrator!";
            exit(-1);
        }

        /* check if it not invoked by maker.php */
        if(!key_exists('MAKER_INVOKE', $asrv) || !$asrv['MAKER_INVOKE']) {
            if(class_exists('pageAnalyticsClassEx')) {
                pageAnalyticsClassEx::initializePageAnalyticsRecord();
            }
        }

        // set maintenance class
        Maintenance::init();
        Maintenance::maintenance();

        // Initialize language detector after configuration is loaded
        $this->initLanguageDetector();

        // Initialize multilingual manager for file-based translations
        $this->initMultilingualManager();
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
        // Log::log("adding section in configuration: " . print_r($section, 1));
        if(!is_array($section)) {
            $addsection = yaml_parse($section);
        } else
            $addsection = $section;

        $this->config = array_merge_recursive($this->config, $addsection);
    }

    function getRoutes() {
        return ($this->config['routes']);
    }

    /*
     * obseolete function, since `sections` are introduced
     * in structure key in settings.info.yaml
    
     function getBlocksInRegion($aregion) {
        // echopre("searching for blocks in region `$aregion`");
        $blks = $this->config['structure'];
        // print_r( $blks[$aregion] );
        $blocks_in_region = recursive_array_search_key($aregion, $blks);
        // echopre("found blocks in region `$aregion`: " . print_r($blocks_in_region, 1));
        return $blocks_in_region;

        if(key_exists($aregion, $blks))
            return $blks[$aregion];
        else return null;
    //   return ($blks[$aregion]);
    }
    */

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


    function registerModule($amod) {
        // echopre("kernel: registering new module <b>" . print_r($amod->getName(), 1) . "</b>");
        $this->modules[$amod->getName()] = $amod;
    }

    function resolveModuleDir($info, $adir, $amodule) {
        // echopre("DOCUMENT_ROOT: ". print_r($_SERVER['DOCUMENT_ROOT'], 1));
        // echopre("PHP_SELF: ". print_r($_SERVER['PHP_SELF'], 1));
        // echopre("dirname(PHP_SELF): ". print_r(dirname($_SERVER['PHP_SELF']), 1));

        // echopre("APP_DIR: " . __APPDIR__);
        // echopre("FW_DIR: " . __FWDIR__);
        // echopre("BOOTSTRAP_DIR: " . __BOOTSTRAP_DIR__);
        // echopre("module dir: " . $adir);
        // echopre("<hr>");

        $bootstrap_len = strlen(__BOOTSTRAP_DIR__);
        $app_len = strlen(__APPDIR__ . '/web');

        $root_dir = dirname($_SERVER['PHP_SELF']);
        $core_dir = substr(__FWDIR__, strlen($_SERVER['DOCUMENT_ROOT']));
        $core_module_dir = substr($adir, $bootstrap_len);
        $app_module_dir = substr($adir, $app_len);


        // echopre("<hr>");

        $final_core_module_dir = /*$core_dir . */'/core' . $core_module_dir; /*. '/' . $amodule;*/
        // echopre("final_core_module_dir: $final_core_module_dir");

        $final_app_module_dir = /*$root_dir . */$app_module_dir; /* . '/' . $amodule;*/

        // echopre("\$info: " . print_r($info, 1));
        $rinfo = recursive_array_replace("@core", $final_core_module_dir, $info);
        // echopre("\$rinfo: " . print_r($rinfo, 1));
        $rrinfo = recursive_array_replace("@app", $final_app_module_dir, $rinfo);
        // echopre("\$rrinfo: " . print_r($rrinfo, 1));

        // echopre("post resolve: " . print_r( $rrinfo, 1) );
        return ( $rrinfo );
    }

    function getModule($amodulename) {
        // echopre("requesting module with name " . $amodulename);
        // echopre("modules list " . print_r($this->modules, 1));

        if(isset($this->modules[ $amodulename ])) {
            // echopre("Found!");
            return( $this->modules[ $amodulename ] );
        }
        else {
            // echopre("Not found!");
            return null;
        }
    }

    function renderRegion($structure, $regionName) {
        if (!isset($structure[$regionName])) {  
            // echopre("Region '$regionName' not found,");
            $output = "Region '$regionName' not found.";
            return $output;
        }
    
        $output = '';

        // echopre("Rendering region: $regionName\n");
        foreach ($structure[$regionName] as $key => $value) {
            if (is_array($value)) {
                // echopre("rendering section " . $key);
                $output .= $this->renderBlock($key, $value, 0, ['region' => $regionName]); // Section
            } else {
                // echopre("rendering block `$value`");
                $out = $this->renderBlock($value, null, 0, ['region' => $regionName]); // Block
                // echopre("block output: " . $out);
                $output .= $out;
            }
        }


        $sugg = array();
        Renderer::getTemplateSuggestions(['type' => 'region', 'name' => $regionName], function($args, &$suggestions) {
            // echopre(print_r($args, 1));
            if($args['type'] === 'region') {
                $suggestions[] = 'region';
                $suggestions[] = 'region--' . $args['name'];
            }
        }, $sugg);

        
        $regionAttributes = new Attributes();
        $regionAttributes->addAttribute(['class' => 'region']);
        $regionAttributes->addAttribute(['class' => 'region-' . $regionName]);
        
        $template = Renderer::getTemplate($sugg);
        // echopre("template $template, suggestions: " . print_r($sugg, 1));
        if(strlen($output)) {
            $region_output_all = Renderer::render($template, 
                [ 'region_name' => $regionName,
                    'attributes' => $regionAttributes,
                    'blocks' => $output],
                    [$sugg, $template]);
        } else $region_output_all = '';

        // echopre("region_output_all ($regionName): " . $region_output_all);
        $output = $region_output_all;

        return $output;
    }
    
    function renderModule($module): string|null {
        $mods = $this->getConfig('modconf');
        if(isset($mods[$module->getName()]))
            $modconfig = $mods[$module->getName()];
        else $modconfig = null;

        if(isset($_SESSION['route_match']) && isset($_SESSION['route_match']['_routename'])) {
            $routename = $_SESSION['route_match']['_routename'];
        } else $routename = ''; 

        $aparams = [];

        if($modconfig) {
            // echopre( print_r( $modconfig, 1));
            // echopre( print_r($_SESSION['route_match']['_routename'], 1));
            if(isset($modconfig['display'])) {
                $display = false;
                // echopre( "module: " .$module->getName() . ", display: " . print_r( $modconfig['display'], 1));
                if((array_search($routename, array_keys($modconfig['display'])) !== false)
                    || (array_search('__all__', array_keys($modconfig['display']))) ) {
                    // echopre('Display');
                    $display = true;
                    $mconf = $modconfig['display'][$routename];
                    if(isset($mconf['arguments'])) {
                        // echopre("Arguments: " . print_r($mconf['arguments'], 1));
                        $aparams['__arguments'] = $mconf['arguments'];
                        // echopre("Arguments params: " . print_r($aparams, 1));
                        // array_push($aparams, $mconf['arguments']);
                    }
                } else $display = false;
            } else 
            if(isset($modconfig['hide'])) {
                $display = true;
                // echopre( "module: " . $module->getName() . ", hide: " . print_r( $modconfig['hide'], 1));
                if(array_search($routename, array_keys($modconfig['hide'])) !== false) {
                    $display = false;
                }
            } else $display = true;              
        } else $display = true;

        if($display) {
            return (
            // $this->modulename . ": renderTemplate(): " . $this->template . ": " .
            $module->render( $aparams ) );
            // Renderer::render($this->template, $aparams));
        }
        else { 
            // echopre("module " . $module->getName() . " does not return output");
            return '';
        }
    }

    function renderBlock($name, $content = null, $tab = 0, $depth = []) {
        $output = '';
        // echopre("depth: " . print_r($depth, 1));
        if ($content === null) {
            // echopre(str_repeat("  ", $tab) . "Block: $name\n");
            // echopre(str_repeat("  ", $tab) . "rendering block `$name`");

            $module = $this->getModule( $name );
            // echopre("found module: " . print_r($module, 1));
            if($module) {
                // echopre("rendering module 1 $name");
                // $output = $module->render();
                $output = $this->renderModule($module); //->renderModule();
                // $output = $module->run();
            }
            // echopre("module output: $output");
            // echopre(str_repeat("  ", $tab) . "return from block");
        } else {
            // echopre("renderBlock() content: " . print_r($content, 1));
            if(is_array($content)) {
                foreach ($content as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        // echopre(str_repeat("  ", $tab) .  "  Section: $subKey\n");
                        $tab++;

                        // $output .= "<div class=\"section section-$subKey\">";
                        $depth['section'][] = $subKey;
                        foreach($subValue as $section) {
                            // echopre(str_repeat("  ", $tab) . "renderingBlock(null, section)");
                            // array_push($depth, $subKey);
                            $out = $this->renderBlock(null, $section, $tab + 1, $depth);
                            // echopre(str_repeat("  ", $tab) . " output from renderBlock()   `" . print_r($out, 1)."`");
                            $output .= $out;
                        }

                        $sugg = array();
                        Renderer::getTemplateSuggestions(
                                ['type' => 'section',
                                    'section' => $depth['section'],
                                    'region' => 'region-'.$depth['region']
                                ], function($args, &$suggestions) {
                            // echopre(print_r($args, 1));
                            if($args['type'] === 'section') {
                                $suggestions[] = 'section';
                                $suggestions[] = $args['region'] . '--section';   //'section';
                                $section_name = [];
                                foreach($args['section'] as $sect) {
                                    $section_name[] = $sect;
                                    $section_list = implode('-', $section_name);
                                    $suggestions[] = 'section--' . $section_list;
                                    $suggestions[] = $args['region'].'--section--' . $section_list;
                                    // $suggestions[] = $section_list;
                                }
                            }
                        }, $sugg);
                
                        
                        $template = Renderer::getTemplate($sugg);
                        // echopre("template $template, suggestions: " . print_r($sugg, 1));
                        if(strlen($output)) {
                            $sectionAttributes = new Attributes();
                            $sectionAttributes->addAttribute(['class' => 'section']);

                            // foreach($depth['section'] as $sect) {
                            //     $sectionAttributes->addAttribute( ['class' => "section-" . $sect] );
                            // }
                            // echopre("depth: " . print_r($depth['section'], 1));
                            $sectionAttributes->addAttribute(['class' => $depth[ 'section' ][array_key_last($depth['section']) ]]);
                            
                            $sectionAttributes->addAttribute(['class' => 'section-' . $depth[ 'section' ][array_key_last($depth['section']) ]]);
                            $section_output_all = Renderer::render($template, 
                                [
                                    // 'section_name' => $subKey,
                                    'section_name' => implode('-', $depth['section']),
                                    'attributes' => $sectionAttributes,
                                    'blocks' => $output],
                                    [$sugg, $template]);
                        } else $section_output_all = '';
                
                        $output = $section_output_all;

                        // $output .= '</div>';
                        // $this->renderBlock($subKey, $subValue, $tab + 1); // Nested section
                    } else {
                        // echopre(str_repeat("  ", $tab) .  "  *Block: $subKey\n");
                        // echopre(str_repeat("  ", $tab) . "renderingBlock($subValue, null)");
                        // $depth['section'] = [];
                        // array_push($depth, $subKey);
                        $depth['section'][] = $subKey;
                        $output .= $this->renderBlock($subValue, null, $tab+1, $depth); // Block
                    }
                }
            } else {
                // echopre("content is not an array");
                $output = $this->renderBlock($content, null, $tab+1); // Block
            }

        }

        return $output;
    }
        
    function renderRegions() {
        global $kernel;

        $structure = $kernel->getConfig('structure');
        $regions = $kernel->getConfig('regions');

        $region_output = [];
        foreach($regions as $region) {
            // echopre("## rendering region `$region`" );//. print_r($regionBlocks, 1));
            $region_output[ $region ] = $kernel->renderRegion($structure, $region);
            // echopre("regions output[ $region ]: " . print_r($region_output, 1));
        }

        return $region_output;
    }

    function renderPage() {
        $t1 = hrtime();

        $regions_response = $this->renderRegions();
        // echopre("regions response: " . print_r($regions_response, 1));

        $links = [];
        $fav = $this->getConfig('favicon');
        // echopre(print_r($config['favicon'], 1));
        if($fav &&
            isset($fav['src']) && file_exists( $fav['src']) ) {
            $links[] = new Attributes([
                "rel" => "icon",
                "type" => $fav['type'] ?? mime_content_type($fav['src']),
                // "type" => "image/ico",
                "href" => rel_url($fav['src'])
            ]);
        }

        $clean_css = remove_header_duplicates($this->getConfig('css'));
        foreach($clean_css as $css) {
            if(!is_array($css['src'])) {
                $links[] = new Attributes([
                    "rel" => "stylesheet",
                    "href" => rel_url($css['src']. "?".time())
                ]);
            } else {
                foreach($css['src'] as $css_src) {
                    $links[] = new Attributes([
                        "rel" => "stylesheet",
                        "href" => rel_url($css_src . "?" . time())
                    ]);
                }
            }
        }

        $head_links = $this->getConfig('head_links');

        if($head_links) {
            $clean_head_links = remove_header_duplicates( $head_links );
            $attr = new Attributes();
            echopre("head links: " . print_r($clean_head_links, 1));
            foreach($clean_head_links as $lnk) {
                foreach($lnk as $elname => $elvalue) {
                    if(strtolower( $elname ) === "href" && str_starts_with('http', strtolower( $elname )))
                        $attr->addAttribute(['src' => rel_url( $elvalue )]);
                    else $attr->addAttribute([$elname => $elvalue]);
                }
            }
            $links[] = $attr;
        }

        $head_scripts = [];
        $head_script = $this->getConfig('head_script');
        if($head_script) {
            $clean_head_script = remove_header_duplicates( $head_script );
            foreach($clean_head_script as $script) {
                $attr = new Attributes();
                foreach($script as $skey => $sval) {
                    if( strtolower($skey) === 'src' ) {
                            $attr->addAttribute(['src' => rel_url( $sval ) ]);
                    } else $attr->addAttribute([$skey => $sval]);
                }
                $head_scripts[] = $attr;
            }
        }

        $foot_links = [];
        $foot_script = $this->getConfig('foot_script');
        if($foot_script) {
            $clean_foot_script = remove_header_duplicates( $foot_script );
            foreach($clean_foot_script as $script) {
                $attr = new Attributes();
                unset( $script[ array_key_first($script ) ] );
                foreach($script as $skey => $sval) {
                    if( strtolower($skey) === 'src' ) {
                            $attr->addAttribute(['src' => rel_url( $sval ) ]);
                    } else $attr->addAttribute([$skey => $sval]);
                }
                $foot_links[] = $attr;
            }
        }

        // final render
        Renderer::view('main.zetem', 
        [
            'title' => getLangText($this->getConfig('title')),
            'meta' => $this->getConfig('meta'),
            'links' => $links,
            'css' => $css,
            'fonts' => $this->getConfig('fonts'),
            'head_links' => $head_links,
            'head_scripts' => $head_scripts,
            'foot_links' => $foot_links,
            'regions' => $regions_response
        ]);

        $t2 = hrtime();

        if($this->safeGetConfig('time_profile')) {
            echo "Time : " . print_r($t2,1)." ~ ".print_r($t1,1)."\n";
            echo ($t2[1] - $t1[1])/1000000.0." msec";
        }
        
    }

    // status can be: error || warning || notice
    function addStatus($level, $statusMessage) {
        if(!isset($_SESSION[ $level ]))
            $_SESSION[ $level ] = array();
        if(is_array($statusMessage)) {
            foreach($statusMessage as $msg)
                $_SESSION[ $level ][] = $msg;
        } else
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
        // echopre(print_r($_SESSION, 1));
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
        $urolelist[] = 'authenticated';
        $_SESSION['user_roles'] = $urolelist;
    }
    
    function logoutUser() {
        unset( $_SESSION['user'] );
        unset( $_SESSION['user_roles']);

        if(isset($_COOKIE['zeusfwrememberme'])) {
            unset($_COOKIE['zeusfwrememberme']);
        }
        
        session_destroy();
    }

    function isUserLoggedin(): bool {
        global $kernel;

        // uncomment the following line to force cookie search
        // unset($_SESSION['user']);

        if(isset($_SESSION['user'])) {
            return true;
        }

        prelog('Trying to get user info from cookie');

        $token = filter_input(INPUT_COOKIE, 'zeusfwrememberme');    //, FILTER_SANITIZE_STRING);
        prelog("token from cookie: " . print_r($token, 1));
        if($token && userTokensClassEx::token_is_valid($token)) {
            prelog("Found valid token in DB, token: " . print_r($token, 1));

            $remoteip = $_SERVER['REMOTE_ADDR'];
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        
            $user = userTokensClassEx::getUserByToken($token, $remoteip, $useragent);
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

    /**
     * Initialize the language detector
     * Called after configuration is loaded
     */
    private function initLanguageDetector() {
        // Only initialize if multilingual is enabled
        if ($this->getConfig('multilingual')) {
            require_once(__FWDIR__ . '/lib/LanguageDetector.php');
            $this->languageDetector = new LanguageDetector($this);
        }
    }

    /**
     * Get the language detector instance
     *
     * @return LanguageDetector|null
     */
    public function getLanguageDetector() {
        return $this->languageDetector;
    }

    /**
     * Initialize the multilingual manager
     * Called after configuration is loaded
     */
    private function initMultilingualManager() {
        // Only initialize if multilingual is enabled
        if ($this->getConfig('multilingual')) {
            require_once(__FWDIR__ . '/lib/MultilingualManager.php');
            $this->multilingualManager = new MultilingualManager($this);
        }
    }

    /**
     * Get the multilingual manager instance
     *
     * @return MultilingualManager|null
     */
    public function getMultilingualManager() {
        return $this->multilingualManager;
    }

    /**
     * Set current language with validation and persistence
     *
     * @param string $curlang - Language code to set
     * @return bool - True if language was set, false if invalid
     */
    function setCurrentLanguage($curlang) {
        // Validate language code
        if ($this->languageDetector && !$this->languageDetector->isValidLanguage($curlang)) {
            return false;
        }

        // Set session
        $_SESSION['CURRENT_LANGUAGE'] = $curlang;

        // Set cookie for persistence (1 year)
        $cookieExpire = time() + (365 * 24 * 60 * 60);
        setcookie('user_lang', $curlang, $cookieExpire, '/');

        // Trigger language change hook (if implemented)
        $this->triggerLanguageChangeHook($curlang);

        return true;
    }

    /**
     * Get current language using intelligent detection
     *
     * @return string - Current language code
     */
    function getCurrentLanguage() {
        // If session is set, return it
        if (isset($_SESSION['CURRENT_LANGUAGE'])) {
            return $_SESSION['CURRENT_LANGUAGE'];
        }

        // Use language detector if available
        if ($this->languageDetector) {
            $detectedLang = $this->languageDetector->detect();
            // Set the detected language in session for future requests
            $_SESSION['CURRENT_LANGUAGE'] = $detectedLang;
            return $detectedLang;
        }

        // Fallback to default
        return $this->getDefaultLanguage();
    }

    /**
     * Get list of supported languages
     *
     * @return array - Array of language codes
     */
    function getSupportedLanguages() {
        if ($this->languageDetector) {
            return $this->languageDetector->getSupportedLanguages();
        }

        // Fallback: get from config
        $languages = $this->getConfig('languages');
        if (is_array($languages)) {
            // Handle both old and new format
            return array_keys($languages);
        }

        return ['el', 'en'];
    }

    /**
     * Get default language from configuration
     *
     * @return string - Default language code
     */
    function getDefaultLanguage() {
        if ($this->languageDetector) {
            return $this->languageDetector->getDefaultLanguage();
        }

        // Fallback
        $langDetection = $this->getConfig('language_detection');
        return $langDetection['default'] ?? 'el';
    }

    /**
     * Get language name (native or English)
     *
     * @param string $code - Language code
     * @param bool $native - Return native name (true) or English name (false)
     * @return string|null - Language name or null
     */
    function getLanguageName($code, $native = true) {
        if ($this->languageDetector) {
            return $this->languageDetector->getLanguageName($code, $native);
        }

        // Fallback: try to get from config
        $languages = $this->getConfig('languages');
        if (isset($languages[$code]) && is_array($languages[$code])) {
            if ($native) {
                return $languages[$code]['native_name'] ?? $languages[$code]['name'] ?? null;
            } else {
                return $languages[$code]['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Trigger language change hook
     * Can be overridden or extended by applications
     *
     * @param string $newLang - New language code
     */
    protected function triggerLanguageChangeHook($newLang) {
        // Hook for future extensions
        // Applications can override this to perform actions on language change
        // Example: log language changes, update user preferences, etc.
    }
}

