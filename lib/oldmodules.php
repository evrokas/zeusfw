<?php

class topbarModule extends moduleClass {
    function render($params = array()) {
        return("topbarModule: " . print_r($params, 1));
    }
}

class htmltextModule extends moduleClass {
    protected $htmltext;

    function __construct($amodule, $atemplate, $text) {
        parent::__construct($amodule, $atemplate);
        $this->htmltext = $text;
    }

    function render($params = array()) {
        // global $Renderer;
        return($this->renderTemplate(['text' =>$this->htmltext, 'params' => $params]));
    }
}

class header extends moduleClass {
    function render($params = array()) {
      global $kernel;
      global $Renderer;
        $tit = $kernel->getConfig()['title'];
        // echo "header module: param title = " . $tit . "\n";
        return ($Renderer->render("header.zetem", ["title" => $kernel->getConfig()['title'] ]));
    }
}

class breadcrumbsModule extends moduleClass {
    function render($params = array()) {
        global $Renderer;
        global $Request;
        global $kernel;

        $ptrail = new Menutrail($Request->getQueryRoute(), $kernel->getConfig('menu')['main']);
        $path = array();
        $ptrail->getTrail($path);
        // print_r( $path );
        if(!count($path)) {
            // menu trail could not get a path
            // try router instead, to get the current route
            $rtrail = new Routetrail();
            $rtrail->getTrail($path);
            // print_r( $path );
        }

        if(!(count($path) == 1) || !(str_starts_with(strtolower($path[0]), 'home')))
            $path = array_merge(["Home"], $path);

        $loop=0;
        $pathfinal = arraY();
        foreach($path as $pathitem) {
            if(!$loop)
                // first item
                $pathfinal[] = [
                    'text' => '::',
                    'attributes' => new Attributes('class', 'bradcrumb-first')
                ]; else
                $pathfinal[] = [
                    'text' => '/',
                    'attributes' => new Attributes('class', 'breadcrumb-separator')
                ];

                $pathfinal[] = [
                    'text' => $pathitem,
                    'attributes' => new Attributes('class', 'breadcrumb-item')
                ];
            $loop++;
        }

        return ($this->renderTemplate( ["path" => $pathfinal] ));
        // return ($this->renderTemplate( ["path" => ["home", "view", "test"]] ));
        // return ($Renderer->render("breadcrumbs.zetem", ["path" => ["home", "view", "test"]]));
    }
}

class mainnavigationModule extends moduleClass {
    protected $menu;
    protected $trail = array();

    function __construct($amodule, $atemplate, $amenu) {
        global $Request;

        parent::__construct($amodule, $atemplate);
        $this->menu = $amenu;
        $pathtrail = new Menutrail($Request->getQueryRoute(), $amenu);
        $pathtrail->getTrail($this->trail);
        // echo "<pre>";
        // print_r($this->menu);
        // print_r($this->trail);
        // echo "</pre>";
    }
    
    function setupMenuAttributes(&$amenu, $alevel) {
        // $amenu['attributes'] = new Attributes();
        // $amenu['attributes']->addClass('menu');
        // $amenu['attributes']->addClass('menu-level-'.$alevel);
        // if(!count($amenu))return;

        foreach($amenu as $mitem => $mdata) {
            // $at = new Attributes();
            $amenu[ $mitem ]['attributes'] = new Attributes();

            if(!isset($amenu[$mitem]['submenu']))
                $amenu[$mitem]['attributes']->addClass('menu-item');
            else {
                $amenu[$mitem]['attributes']->addClass('submenu-item');
                $amenu[$mitem]['attributes']->addClass('submenu-item-level-' . $alevel+1);
            }
                // $at->addClass('submenu-item-level-' . $alevel+1);

            // $amenu[ $mitem ]['attributes'] = $at;
            
            // echo "<pre>" . $amenu[$mitem]['text'] . ' <> ' . $this->trail[$alevel] . " {" . $alevel . "}<br/></pre>";
            if( isset($this->trail[$alevel])/*$alevel*/ && ($amenu[$mitem]['text'] == $this->trail[$alevel]) )
                $amenu[$mitem]['attributes']->addClass('in-menu-trail');


            // echo "mdata ($alevel):" . $amenu[$mitem]['text'];
            if(isset($amenu[$mitem]['submenu'])) {  
                // $amenu[$mitem]['submenu']['attributes'] = new Attributes('class', 'submenu');
                // ($amenu[$mitem]['submenu']['attributes'])->addClass('submenu');

                $this->setupMenuAttributes($amenu[ $mitem ]['submenu'], $alevel+1);
            }
            // echo "<pre>"; print_r( $amenu ); echo "</pre>";
            // echo "attrs: " . $amenu[$mitem]['attributes']->getAttributes() . "<br>";
        }
    }
    function render($params = array()) {
        global $Renderer;

        $mmenu = $this->menu;
        $this->setupMenuAttributes($mmenu, 0);
        // echo "<pre>"; print_r( $mmenu ); echo "</pre>";

        return(
            // "main navigation module<br>" .
            $this->RenderTemplate(['menu' => $mmenu]));
    }
}

class contentModule extends moduleClass {
    function render($params = array()) {
        global $Renderer;
        global $kernel;
        global $Request;
        global $content_response;

            return ($content_response);
    }
}

class notificationsModule extends moduleClass {
    function render($params = array()) {
        global $kernel;

            $prms = array();
            foreach(['notice', 'error', 'warning'] as $level) {
                $s = $kernel->getStatus($level, true);
                // echo "$level " . print_r($s, 1) . "<br>";

                if(count($s))$prms[ $level ] = implode('<br>', $s);
            }

        return $this->RenderTemplate($prms);
    }
}

class githashModule extends moduleClass {
    function __construct($amodule, $atemplate) {
        parent::__construct($amodule, $atemplate);
    }
    
    function render($params = array()) {
        // $hash = __FUNCTION__;
        $rootdir = explode('index.php', $_SERVER['SCRIPT_FILENAME'])[0] . "../";
        
        $headfile = file_get_contents($rootdir . ".git/HEAD");
        $headfile = explode(' ', trim($headfile))[1];
        $hash = file_get_contents($rootdir . ".git/" . $headfile);
        $branch = explode('/', $headfile)[2];

        // error_log( "git hash directory: $branch, hash: $hash\n" );
        return $this->renderTemplate(['branch' => $branch, 'hash' => $hash, 'db' => $GLOBALS['DATABASE']]);
    }
}

class UserModule extends moduleClass {
    function render($params = array()) {
        global $kernel;
        $loggedin = false;
        $user = '';
        $us = $kernel->getUserName();
        // echo "<pre>Username: $us</pre>";

        if(($us=$kernel->getUserName())) {
            $user = $us;
            $loggedin = true;
        }
        return $this->RenderTemplate(['loggedin' => $loggedin, 'name' => $user]);
    }
}

class UserProfileModule extends moduleClass {

    function __construct($amodule, $atemplate) {
        parent::__construct($amodule, $atemplate);

        //add route definitions
        $profile_yaml="
        routes:
            userprofile:
                title: 'User profile'
                name: userprofile
                url: /profile
                module: userprofile
                # handler: module
                method: get
            userprofile_post:
                title: 'User profile'
                name: userprofile_post
                url: /profile
                module: userprofile
                # handler: module
                method: post          
        ";

        global $kernel;
        $kernel->addConfig( $profile_yaml );


        // $region_yaml="
        //     structure:
        //         notification:
        //             - userprofile
        //     ";

        // $kernel->addConfig($region_yaml);
        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
        // echo "<pre>Router routes: " . print_r( $router->getAllRoutes(), 1) . "</pre>";
    }

    function render($params = array()) {
        global $kernel;

        if(!($u=$kernel->getUserName())) {
            return "";
        }

        $user = UsersClassEx::getUserAccount( $u );

        return $this->RenderTemplate(['user' => $user,
            $user->getactive()?'checked="checked"':'',
            $user->getExpired()?'checked="checked"':'']);
    }

    function run($params = array()) {
        // echopre("UserProfile module::run()");
        return $this->render($params);
    }
}


    // $kernel->registerModule( new header('header', 'header.zetem'));
    // $kernel->registerModule( new breadcrumbsModule('breadcrumbs', 'breadcrumbs.zetem'));
    // $kernel->registerModule( new mainnavigationModule('mainnavigation', 'main_navigation.zetem', $kernel->getConfig('menu')['main']));
    // // $kernel->registerModule( new moduleClass('topbar', 'topbar.zetem') );
    // // $kernel->registerModule( new moduleClass('content', 'content.zetem') );
    // $kernel->registerModule( new htmltextModule('copyright', 'htmltext.zetem', 
    //     '&copy 2023-24 by Evangelos M. Rokas, MD' . ' | <a href="#"><i class="bx bx-cog"></i></a>'));
    // $kernel->registerModule( new githashModule('githash', 'githash.zetem'));
    // $kernel->registerModule( new contentModule('content', 'content.zetem'));
    // $kernel->registerModule( new notificationsModule('notifications', 'notifications.zetem'));
    // $kernel->registerModule( new UserModule('userblock', 'userblock.zetem'));

    // $kernel->registerModule( new UserProfileModule('userprofile', 'user_profile.zetem'));
    