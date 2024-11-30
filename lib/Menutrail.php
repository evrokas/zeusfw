<?php

// provide menu tail interface

class Menutrail {
    private $menu = null;
    private $path;

    function __construct($apath, $amenu = null) {
        // $this->path = $apath;
        $this->setPath($apath);
        // $this->menu = $amenu;
        if($amenu)$this->setMenu( $amenu );
    }

    function setMenu($amenu) {
        $this->menu = $amenu;
    }

    function setPath($apath) {
        $this->path = $apath;
        if(count($this->path) == 0) {
            $this->path = ['/'];
        }
    }

    function getTrail(&$amenutrail, $amenu = null, $level = 1) {
        // $path = explode('/', $apath);
        if(!$amenu)$amenu = $this->menu;

        // echopre("Search path --- " . implode(' | ', $this->path) . " -- trail level $level and token " . $this->path[ $level ]);

        if(!$amenu) {
            $amenutrail = array();
            return;
        }

        $found = false;
        foreach($amenu as $menuitem) {
            // echopre("menutrail [lvl: $level]: '" . print_r($menuitem, 1) ."'");
            // echopre("test trail: " . array_key_first($menuitem) . " === " . $this->path[ $level ] . "<br>");
            if(isset($this->path[$level]) && (array_key_first($menuitem) == $this->path[ $level ])) {
                // echopre("found trail " . $menuitem['text'] );
                // ]. "<br>". print_r( $menuitem, 1));
                // echopre("menuitem: " . print_r($menuitem, 1));
                // echopre("menutrail: " . print_r($amenutrail, 1));
                $amenutrail[] = array('text' => getLangText( $menuitem['text'] ), 'url' => (key_exists('url', $menuitem)?$menuitem['url']:null)) ;

                $found = true;
                // echopre("current trail: " . print_r($amenutrail, 1));
                if(isset($menuitem['submenu'])) {
                    // echopre("Has submenu");
                    $this->getTrail($amenutrail, $menuitem['submenu'], $level+1);
                }
                return;
            }
            if($found)break;
        }
    }

    function getTrails() {
        foreach($this->menu as $menuitem) {
            echo "menuitem: " . $menuitem['text'] . "<br>";
        }
    }
}