<?php

class RouterClass {
    private $route_table;
    static private $route_error = 0;

    function __construct($rt = array()) {
        $this->initRouteTable( $rt );
    }

    function initRouteTable($rt = array()) {
        $this->route_table = $rt;
    }
    static function setError($err) {
        self::$route_error = $err;
    }

    static function clearError() {
        self::$route_error = 0;
    }

    static function getError() {
        return (self::$route_error);
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

    function matchRoute(&$areq) {
        // $request is in the form /option1/options2/arg1/arg2/etc...
        $arequest = $areq->getQueryString();
        // $arequest = $areq
        // echo "Request string: >>" . print_r($areq->getQueryRoute(), 1) . "<<<br/>";
        $req = trim($arequest);
        $req = trim($req, '/');
        $req = explode('/', $req);
        // print_r( $req );
        $match_collection = array();
        $rule = '';

        foreach($this->route_table as $routename => $routedata) {
            
            if(is_array($routedata['url'])) {
                // echopre("Route is array");

                foreach($routedata['url'] as $routeurl) {
                    $rpath = explode('/', trim($routeurl, '/'));
                    // echo("<pre>Routename: ". print_r( $routename, 1) . " : " . $routedata['url'] . " method: " . (isset($routedata['method'])?$routedata['method']:"unknown") . "</pre>" );
                    // echo("<pre>rpath: ". print_r( $rpath, 1). "</pre>");
                    
                    
                    $params = array();
                    $match = $this->matchRoutePaths($req, $rpath, $params);
                    if($match) {
                        
                        $rule = $routeurl;
                        break;
                    }
                }
            } else {
                $rpath = explode('/', trim($routedata['url'], '/'));
                // echo("<pre>Routename: ". print_r( $routename, 1) . " : " . $routedata['url'] . " method: " . (isset($routedata['method'])?$routedata['method']:"unknown") . "</pre>" );
                // echo("<pre>rpath: ". print_r( $rpath, 1). "</pre>");
                
                
                $params = array();
                $match = $this->matchRoutePaths($req, $rpath, $params);
                $rule = $routedata['url'];
            }
            if($match) {


                
                //put all matching routes in $match_collection
                //use _fit to distinguish between routes with same tokens and different parameters

                // use permissions to drop unauthorized access
                // if((!isset($routedata['permissions'])) ||
                    // (isset($routedata['permissions']) && $this->validatePermissions($routedata['permissions']))) {
                        // echo "<pre>Router permissions: " . $routedata['permissions']
                        // }
                if(isset($routedata['access'])) {
                    // echo "<pre>Router permissions: " . $routedata['access'] . "</pre>";
                    if(SecurityClass::userIsPermitted($routedata['access'])) {
                        // echo "<pre>User permitted!</pre>";
                    } else {
                        // echo "<pre>User is not permitted!</pre>";
                        http_response_code(401);
                        self::setError(401);
                        break;
                    }
                }


                // add in collection only if 
                // - method is not specified in route
                // - or if method is specifed, it is the same as in request
                if( (!isset($routedata['method'])) || 
                    ((isset($routedata['method']) && $areq->matchMethod( $routedata['method'] )))
                ) {
                    $match_collection[] = [
                        "_routename" => $routename, 
                        "_routedata" => $routedata, 
                        "_params" => $params, 
                        "_fit" => $match,
                        "_rule" => $rule,
                        "_request" => $areq->getQueryString()
                    ];
                    // echo "<pre>Route match: " . $arequest . " === " . $routedata['title'] . " = " . $routedata['url'] . " method: " . $routedata['method'] . "</pre>";
                }

                // foreach($params as $key => $val) {
                    // echo " - param: " . $key . " = " . $val . "\n";
                // }
//                return( ["_routename" => $routename, "_routedata" => $routedata, "_params" => $params ]);
            }
        }

        // find the route that has the best _fit
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

    static function routerCallFunction($match_route) {
        global $kernel;
        
        $hasAnalytics = class_exists("analyticsClass");
        $hasPageAnalytics = class_exists("pageAnalyticsClass");

        if($hasAnalytics && $kernel->safeGetConfig('analytics')) {
            $ips = $kernel->safeGetConfigValue('analytics', 'ignore_ips');
            // error_log('ignore IPs: ' . print_r($ips, 1));

            if(in_array($_SERVER['REMOTE_ADDR'], $ips)) {
                $hasAnalytics = false;
            }
        }
        // error_log("hasAnalytics: " . $hasAnalytics);
        // error_log("hasPageAnalytics: " . $hasPageAnalytics);
        $page = ($match_route && $match_route['_routename'])?$match_route['_routename']:"n/a";
        $url = $_SERVER['QUERY_STRING'];
        
        // if $url is empty then just call it '/'
        if(!strlen($url))$url = '/';

        if($hasAnalytics) {
            $acl = new analyticsClass([
                'cdate' => getDBtime(),
                'page' => $page,
                'url' => $url,
                'remote_ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        if(!$match_route) {
            if($hasAnalytics) {
                $acl->setserved(0);
                $acl->insert();
            }

            if(self::getError()) {
                http_response_code(self::getError());

                switch(self::getError()) {
                    case 401: return (error_401());
                }

                self::clearError();
            } else {
                http_response_code(404);

                return (error_404() );
            }
        }
        http_response_code(200);

        if($hasPageAnalytics) {
            $pan = pageAnalyticsClassEx::getPageByURL( $url );
            if($pan) {

                // echopre("pageAnalytics found [page: $page]: " . print_r($pan, 1));
                
                $pane = new pageAnalyticsClassEx( $pan->getAllFields() );
            } else {
                $pane = new pageAnalyticsClassEx([
                    'cdate' => getDBtime(),
                    'page' => $page,
                    'url' => $url,
                    'rule' => $match_route['_rule'],
                    'total_count' => 0,
                    'week_count' => 0,
                    'last_week_count' => 0,

                    'month_count' => 0,
                    'last_month_count' => 0,
                ]);

                // echopre("page analytics not found [page: $page, url: $url]");
            }
        }

        if(!isset($match_route['_routedata']['handler'])) {

            // if there is no page match, search for modules match
            $fexe = $match_route['_routedata']['module'];
            if(!$fexe) {
                if($hasAnalytics) {
                    $acl->setserved(0);
                    $acl->insert();
                }
                return (error_404());
            }

            // update analytics table
            if($hasAnalytics) {
                $acl->setserved(1);
                $acl->insert();
            }

            // update page statistics table
            if($hasPageAnalytics) {
                $pane->increase();
                if(!$pan)$pane->insert();
                else $pane->update();
            }

            $params = $match_route['_params'];
            return (self::call_module_func($fexe, $params) );

        }

        
        $fexe = $match_route['_routedata']['handler'];
        $params = $match_route['_params'];

        // update analytics table
        if($hasAnalytics) {
            $acl->setserved(1);
            $acl->insert();
        }

        // update page statistics table
        if($hasPageAnalytics) {
            $pane->increase();
            if(!$pan)$pane->insert();
            else $pane->update();
        }
        
        return ( call_user_func($fexe, $params) );
    }

    static function call_module_func($amodule, $params) {
        echopre("router call_module_func for module $amodule and params: " . print_r($params, 1));
        return (module( $amodule, $params ) );
    }
}