<?php

require_once(__DIR__ . '/admin_crud.php');

class adminModule extends moduleClass {
    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        // echopre("adminModule::construct($adir, $amodule)");
        
        $rt = yaml_parse_file(__DIR__ . '/admin.yaml');
        // echopre("admin module admin.yaml contents: " . print_r($rt, 1));

        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);

        // update kernel configuration
        // echopre("admin module: \$srt: " . print_r($srt, 1));
        $kernel->addConfig( $srt );

        // echopre("adminModule construct: new kernel configuration: " . print_r($kernel->getConfig(), 1));

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
    // echopre(phpinfo());
    // echopre('registering admin module');
    // error_log(">>registering admin module");
    // $am = new adminModule(__DIR__, 'admin', 'admin.zetem');
    // echopre("functionregister_admin_module: " . print_r($am, 1));
    $kernel->registerModule(new adminModule(__DIR__, 'admin', 'admin.zetem'));
    // $kernel->registerModule($am);
}
