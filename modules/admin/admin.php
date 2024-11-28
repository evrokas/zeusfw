<?php

class adminModule extends moduleClass {
    function __construct($adir, $amodule, $atemplate)
    {
        $rt = yaml_parse_file(__DIR__ . '/admin.yaml');
        global $kernel;

        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);

        $kernel->addConfig( $srt );

        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    function render($params = array())
    {
        global $kernel;

        return $this->renderTemplate([]);   
    }

    function run($params = array()) {
        return $this->renderTemplate([]);
    }
}


function register_admin_module() {
    global $kernel;

    prelog('registering admin module');
    echopre('registering admin module');
    
    $kernel->registerModule(new adminModule(__DIR__, 'admin', 'admin.zetem'));
}
