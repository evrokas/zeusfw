<?php

// provide route tail interface

class Routetrail {
    function getTrail(&$routetrail, $aroute = null) {
        global $router;
        global $Request;
        if(!$aroute)$aroute = $router->matchRoute( $Request );;
        // echo "<pre>Routetrail::getTrail: "; print_r( $aroute['_routedata'] ); echo "</pre>";
        if($aroute) {
            $routetrail = array(['text' => $aroute['_routedata']['title'],
                    'url' => $aroute['_routedata']['url']]);

        }
        else $routetrail = array(['text' => '', 'url' => null]);
    }
}