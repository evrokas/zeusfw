<?php

class RouterClass {
    private $route_table;

    function __construct($rt = null) {
        $this->route_table = array();
        $this->route_table = $rt;
    }

    function getAllRoutes() {
        return( $this->route_table );
    }

    function getRoute($route_name) {

        foreach($this->route_table as $routename => $routeval) {
            if($routename === $route_name)
                return ( $routeval );
        }
        return (null);
    }

    function matchRoutePaths($req, $path, &$params) {
        // print_r( $req );
        // print_r( $path );
        $aparams = array();
        $match_count = 0;
        $req_token_count = 0;
        $lit_token_count = 1;
        foreach($req as $req_token) {
            // echo 'Token: ' . print_r( $req_token,1 )." Token[".$req_token_count."]=".$req[$req_token_count]." ==> ";
            
            if(isset($path[$req_token_count])) {
                // echo 'Path: ' . $path[ $req_token_count ] . "\n";

                if(preg_match("/\{[^\}]+\}/", $path[ $req_token_count ])) {
                    // echo "IS A PARAMETER name: " . trim($path[$req_token_count],"{}")." value: ". $req_token . "\n";
                    $match_count++;
                    $aparams[ trim($path[$req_token_count], "{}") ] = $req_token;
                }
                if($req_token === $path[$req_token_count]) {
                    $match_count++;
                    $lit_token_count++;
                }
                    // echo 'MATCH!' . "\n";

            }
        
            $req_token_count++;
        }

        while(isset($path[$req_token_count]))
            $req_token_count++;

        // echo "\nFinal match count : " . $match_count . " [Total tests:" . $req_token_count . "]\n";
        if( $match_count === $req_token_count ) {
            // echo "Route is a match: " . print_r($req,1) . " === " . print_r($path,1) . "\n";
            $params = $aparams;
            return ( $lit_token_count );
        }

        unset($params);
        return (0);
    }

    function matchRoute($arequest) {
        // $request is in the form /option1/options2/arg1/arg2/etc...
        $req = $arequest;
        $req = trim($arequest);
        $req = trim($req, '/');
        $req = explode('/', $req);
        // print_r( $req );
        $match_collection = array();

        foreach($this->route_table as $routename => $routedata) {
            
            $rpath = explode('/', trim($routedata['url'], '/'));
            // print_r( $routename . " : " . $routedata['url'] . "\n" );
            // print_r( $rpath );


            $params = array();
            $match = $this->matchRoutePaths($req, $rpath, $params);
            if($match) {

                //put all matching routes in $match_collection
                //use _fit to distinguish between routes with same tokens and different parameters
                $match_collection[] = ["_routename" => $routename, "_routedata" => $routedata, "_params" => $params, "_fit" => $match];
                // echo "Route match: " . $arequest . " === " . $routedata['title'] . " = " . $routedata['url'] . "\n";
                // foreach($params as $key => $val) {
                    // echo " - param: " . $key . " = " . $val . "\n";
                // }
//                return( ["_routename" => $routename, "_routedata" => $routedata, "_params" => $params ]);
            }
        }
        // print_r( $match_collection );
        $rval = 0;
        $rmatch = null;
        foreach($match_collection as $r) {
            if($r['_fit'] > $rval) {
                $rval = $r['_fit'];
                $rmatch = $r;
            }
        }
        // print_r( $rmatch );
        return ($rmatch);
    //   return (null);
    }
}