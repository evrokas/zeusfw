<?php

// provide menu tail interface

class Menutrail {
    private $menu = null;
    private $path;
    private $menulinks;

    function __construct($apath, $amenu = null) {
        // $this->path = $apath;
        $this->setPath($apath);
        // $this->menu = $amenu;
        if($amenu)$this->setMenu( $amenu );
        $this->menulinks = [];
    }

    function setMenu($amenu) {
        $this->menu = $amenu;
    }

    function setPath($apath) {
        $this->path = $apath;
        if(count($this->path) === 0) {
            $this->path = ['/'];
        }
    }

    function search_menu_trail_for_key($key, $menu, $routes) {
        foreach($menu as $mkey => $mval) {
            // echopre("menu key: $key, mkey: " . print_r($mkey,1) . " val: " . array_key_first($mval));
            // echopre("menu item: " . print_r($mval, 1));
            if(array_key_first($mval) === $key) {
                // echopre("found: $mkey");
                $out = ['key' => array_key_first($mval),
                'title' => $mval['text']??array_key_first($mval),
                'url' => $mval['url']??null,
                'route_title' => isset($routes[ array_key_first($mval) ])?$routes[ array_key_first($mval) ]['title']:null];

                $t = [];
                $t[] = $out;

                return $t;
            } else
            if(array_key_exists('submenu', $mval)) {
                // echopre("submenu: " . array_key_first($mval));
                // echopre("submenu: " . array_key_first(($mval)));
                $res = $this->search_menu_trail_for_key($key, $mval['submenu'], $routes);
                // echopre("found res : " . print_r($res, 1));
                if(count($res)) {
                    $out = ['key' => array_key_first($mval),
                    'title' => $mval['text']??array_key_first($mval),
                    'url' => $mval['url']??null,
                    'route_title' => isset($routes[ array_key_first($mval) ])?$routes[ array_key_first($mval) ]['title']:null];

                    // echopre("merge arrays: res:" . print_r($res, 1) . " and out:" . print_r($out, 1));
                    // $res = $res + $out;
                    $t = [];
                    $t = $res;
                    $t[] = $out;
                    // $t[] = $res;
                    // array_push($t, $res);
                    // array_push($t, $out);
                    // $res[] = $out;
                    // array_merge_recursive($res, $out);
                    // echopre("result22: " . print_r($out, 1));
                    // return $res;    //array_push($res, $out);
                    return $t;
                }
            }
        }
        return [];
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
            $this->menulinks[] = array_key_first($menuitem);
            // echopre("menutrail [lvl: $level]: '" . print_r($menuitem, 1) ."'");
            echopre("[l:$level] test trail: " . array_key_first($menuitem) . " === " . $this->path[ $level ] . "<br>");
            if(isset($this->path[$level]) && (array_key_first($menuitem) === $this->path[ $level ])) {
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
            if($found) {
                echopre("match found [l:$level]");
                break;
            }
        }
    }

    function getTrails() {
        foreach($this->menu as $menuitem) {
            // echopre("menuitem: " . $menuitem['text'] . "<br>");
        }
    }

    function getMenulinks() {
        return $this->menulinks;
    }
}