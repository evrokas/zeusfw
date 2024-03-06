<?php

// provide route tail interface

class Routetrail {
    function getTrail(&$routetrail, $aroute = null) {
        global $router;
        global $Request;
        if(!$aroute)$aroute = $router->matchRoute( $Request );;
        // echo "<pre>Routetrail::getTrail: ";
        // print_r( $aroute['_routedata'] );
        // echo "</pre>";
        if($aroute)
            $routetrail = array($aroute['_routedata']['title']);
        else $routetrail = array();
    }
}