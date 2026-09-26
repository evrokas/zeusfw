<?php

class breadcrumbsModule extends moduleClass {
    function render($params = array()) {
        global $Request;
        global $kernel;

        $ptrail = new Menutrail($Request->getQueryRoute(), $kernel->getConfig('menu')['main']);
        $path = array();
        // $ptrail->getTrail($path);

        $results = [];
        if(isset($_SESSION['route_match'])) {
            $results = $ptrail->search_menu_trail_for_key($_SESSION['route_match']['_routename'],
            $kernel->getConfig('menu')['main'], $kernel->getConfig('routes'));
            
            // reverse array
            $results = array_reverse( $results );
            // echopre("breadcrumps items: " . print_r($results, 1));
        }


        $path = $results;

        // echopre(print_r( $path, 1 ));
        if(!count($path)) {
            // menu trail could not get a path
            // try router instead, to get the current route
            $rtrail = new Routetrail();
            $rtrail->getTrail($path);
            // echopre("Using Routetrail() " . print_r($path, 1));
        }
        
        // if(!(count($path) == 1) || !(str_starts_with(strtolower($path[0]['text']), 'home')))
            // $path = array_merge([['text' => getLangText(["en"=>"Home","gr"=>"Αρχική"]), 'url' => '/']], $path);
    
        // echopre(print_r( $path,1) );
        $loop=0;
        $pathfinal = array();
        foreach($path as $pathitem) {
            if(!$loop)
                // first item
                $pathfinal[] = [
                    'text' => '::',
                    'attributes' => new Attributes(['class' => 'breadcrumb-first'])
                ]; 
            else
                $pathfinal[] = [
                    'text' => '/',
                    'attributes' => new Attributes(['class' => 'breadcrumb-separator'])
                ];

                $pathfinal[] = [
                    // 'text' => $pathitem['text'],
                    // Menutrail::search_menu_trail_for_key() keys its
                    // entries 'title' (with 'route_title' set only when
                    // the menu item is itself a real, named route);
                    // Routetrail::getTrail()'s single-segment fallback
                    // instead keys its one entry 'text'. This chain covers
                    // all three: a real route (route_title), a pure
                    // menu-grouping label with no route behind it (title
                    // -- e.g. "Ραντεβού"/"Ασθενείς", which only exist to
                    // hold a submenu), and the Routetrail fallback (text).
                    // Checking route_title ?? text ?? null alone (as this
                    // used to) left every grouping-label segment
                    // rendering the literal string "nolangtext", since
                    // it has neither route_title nor text, only title.
                    'text' => getLangText($pathitem['route_title']??$pathitem['title']??$pathitem['text']??null),
                    'attributes' => new Attributes(['class' => 'breadcrumb-item']),
                    'url' => '#'    //is_array($pathitem['url'])?$pathitem['url'][0]:$pathitem['url']
                ];
            $loop++;
        }

        return ($this->renderTemplate( ["path" => $pathfinal] ));
        // return ($this->renderTemplate( ["path" => ["home", "view", "test"]] ));
        // return (Renderer::render("breadcrumbs.zetem", ["path" => ["home", "view", "test"]]));
    }
}


function register_breadcrumbs_module() {
    global $kernel;

    $kernel->registerModule(new breadcrumbsModule(__DIR__, 'breadcrumbs', 'breadcrumbs.zetem'));

    $info = yaml_parse_file( __DIR__ . "/breadcrumbs.yaml");
    $kernel->addConfig($info);

    attach_library('breadcrumbs-library');
}
