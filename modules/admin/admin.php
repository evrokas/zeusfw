<?php

class adminModule extends moduleClass {
    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        echopre("adminModule::construct($adir, $amodule)");
        
        $rt = yaml_parse_file(__DIR__ . '/admin.yaml');
        echopre("admin module admin.yaml contents: " . print_r($rt, 1));

        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);

        echopre("admin module: \$srt: " . print_r($srt, 1));
        // update kernel configuration
        $kernel->addConfig( $srt );

        echopre("adminModule construct: new kernel configuration: " . print_r($kernel->getConfig(), 1));

        // add routes to router table
        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    function render($params = array())
    {
        global $kernel;
        return $this->renderTemplate([]);   
    }

    function run($params = array()) {
        return $this->render([]);
    }
}


function register_admin_module() {
    global $kernel;

    // prelog('registering admin module');
    echopre('registering admin module');
    
    $am = new adminModule(__DIR__, 'admin', 'admin.zetem');
    echopre("register_admin_module: " . print_r($am));
    // $kernel->registerModule(new adminModule(__DIR__, 'admin', 'admin.zetem'));
    $kernel->registerModule($am);
}
