<?php

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


function register_breadcrumbs_module() {
    global $kernel;

    $kernel->registerModule(new breadcrumbsModule(__DIR__, 'breadcrumbs', 'breadcrumbs.zetem'));

    $info = yaml_parse_file( __DIR__ . "/breadcrumbs.yaml");
    $kernel->addConfig($info);

    attach_library('breadcrumbs-library');
}
