<?php

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


function register_mainnavigation_module() {
    global $kernel;

    $kernel->registerModule( new mainnavigationModule('mainnavigation', 'main_navigation.zetem', $kernel->getConfig('menu')['main']));
   }