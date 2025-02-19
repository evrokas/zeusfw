<?php

class menuModule extends moduleClass {
    protected $menu;
    protected $trail = array();

    function __construct($adir, $amodule, $atemplate, $amenu = null) {
        global $Request;

        parent::__construct($adir, $amodule, $atemplate);
        if($amenu)$this->setMenu($amenu);

        // $pathtrail = new Menutrail($Request->getQueryRoute(), $this->menu);
        // $pathtrail->getTrail($this->trail);

        // echopre("Menu links: " . print_r($pathtrail->getMenulinks(), 1));
        // $pathtrail = new Menutrail($Request->getQueryRoute(), $this->menu);

        // move the following in setMenu()
        // $pathtrail = new Menutrail($Request->getQueryRoute(), $amenu);
        // $pathtrail->getTrail($this->trail);


        // echo "<pre>";
        // print_r($this->menu);
        // print_r($this->trail);
        // echo "</pre>";
    }
    
    function setMenu($amenu, $apath = null) {
        global $Request;

        $this->menu = $amenu;
        
        if(!$apath)$apath = $Request->getQueryRoute();
        // echopre("Query Route: " . print_r($apath, 1));
        // menu has changed, update Menutrail info

        // $pathtrail = new Menutrail($apath, $this->menu);
        // $pathtrail->getTrail($this->trail);
    }

    function setupMenuAttributes(&$amenu, $alevel) {
        // $amenu['attributes'] = new Attributes();
        // $amenu['attributes']->addClass('menu');
        // $amenu['attributes']->addClass('menu-level-'.$alevel);
        // if(!count($amenu))return;
        // echopre("menu: " . print_r($amenu, 1));
        if(!$amenu)return;

        foreach($amenu as $mitem => $mdata) {

            $show = 0;
            // check for published key
            if(true || (key_exists('published', $mdata) && $mdata['published'])
                || !key_exists('published', $mdata) )$show++;
            

            // check for access key
            if(key_exists('access', $mdata)) {
                // echopre("access key " . print_r($mdata, 1));
                $errmsg = SecurityClass::require( $mdata['access'] );
                // echopre("errmsg: $errmsg");
                if($errmsg)$show = 0;
            }

            if($show>0) {

                // echopre("mitem: " . print_r($mdata, 1) . " ==> " . array_key_first($mdata));
                // $at = new Attributes();
                $amenu[ $mitem ]['attributes'] = new Attributes();
                $amenu[ $mitem ]['key'] = array_key_first($mdata);
                if(key_exists('published', $mdata)) {
                    $amenu[ $mitem ]['attributes']->addClass($mdata['published']?'menu-item-published':'menu-item-unpublished');
                } else
                    $amenu[ $mitem ]['attributes']->addClass('menu-item-published');
                    
                    
                if(!isset($amenu[$mitem]['submenu']))
                    $amenu[$mitem]['attributes']->addClass('menu-item');
                else {
                    $amenu[$mitem]['attributes']->addClass('submenu-item');
                    $amenu[$mitem]['attributes']->addClass('submenu-item-level-' . $alevel+1);
                }
                // $at->addClass('submenu-item-level-' . $alevel+1);
                
                // $amenu[ $mitem ]['attributes'] = $at;
                
                // echo "<pre>" . $amenu[$mitem]['text'] . ' <> ' . $this->trail[$alevel] . " {" . $alevel . "}<br/></pre>";
                // if( isset($this->trail[$alevel])/*$alevel*/ && ($amenu[$mitem]['text'] == $this->trail[$alevel]) )
                // $amenu[$mitem]['attributes']->addClass('in-menu-trail');
                

                // echopre("mdata ($alevel):" . $amenu[$mitem]['text']);
                if(isset($amenu[$mitem]['submenu'])) {  
                    // $amenu[$mitem]['submenu']['attributes'] = new Attributes('class', 'submenu');
                    // ($amenu[$mitem]['submenu']['attributes'])->addClass('submenu');

                    $this->setupMenuAttributes($amenu[ $mitem ]['submenu'], $alevel+1);

                    $amenu[$mitem]['drop-menu-attributes'] = new Attributes();
                    $amenu[$mitem]['drop-menu-attributes']->addClass('drop-menu');
                    $amenu[$mitem]['drop-menu-attributes']->addClass('drop-menu-'.$alevel+1);

                    if(isset($amenu[$mitem]['submenu-class'])) {
                        $amenu[$mitem]['drop-menu-attributes']->addClass($amenu[$mitem]['submenu-class']);
                    }
                }
                // echo "<pre>"; print_r( $amenu ); echo "</pre>";
                // echo "attrs: " . $amenu[$mitem]['attributes']->getAttributes() . "<br>";
            } else {
                unset($amenu[$mitem]);
            }
        }
    }

    function render($params = array()) {

        $mmenu = $this->menu;
        $this->setupMenuAttributes($mmenu, 0);
        // echo "<pre>"; print_r( $mmenu ); echo "</pre>";

        return(
            // "main navigation module<br>" .
            $this->RenderTemplate(['menu' => $mmenu]));
    }
}

function register_mainnavigation_module() {
    global $kernel;

    $kernel->registerModule( new menuModule(__DIR__, 'mainnavigation', 'main_navigation.zetem', $kernel->getConfig('menu')['main']));
}
