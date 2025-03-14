
<?php
// modules


class moduleClass {
    private $moduledir;
    private $modulename;     // module name
    private $template;       // template associateed wi

    function __construct($adir, $amodule, $atemplate) {
        // echopre("moduleClass __construct($adir, $amodule, " . print_r($atemplate,1));
        $this->moduledir = $adir;
        $this->modulename = $amodule;
        $this->template = $atemplate;

    }
    function render($params = array()) {
        $params[] = ['module' => $this->template];

        // echo "render module " . $this->modulename;
        return $this->renderTemplate( $params );

        // return Renderer::render( $this->template, $params ); 
    }

    function getName() {
        return ($this->modulename);
    }

    function getTemplate() {
        return ($this->template);
    }

    function setTemplate($atemplate) {
        $this->template = $atemplate;
    }


    function run($aparams = array()) {
        echopre("default run function");
        // override here to add functionality beyond render()
        return ($this->render($aparams));
    }
    function renderTemplate($aparams = array()) {
        // helper function to render a template
        return Renderer::render($this->template, $aparams);
    }
}

function registerModules() {
    global $kernel;

    $mods = $kernel->getConfig('modules');
    // echo("Register module: " . print_r($mods, 1) . "\n");

    foreach($mods['path'] as $mpath) {
        $modpath = $kernel->getbasepath() . '..' . $mpath;
        // $modpath = $mpath;

        foreach($mods['modules'] as $mod) {
            // echopre("module: $modpath => $mod");
            $yfile = $modpath . $mod . '/' . $mod . '.info.yaml';
            // echopre("Testing yfile: $yfile");
            if(file_exists($yfile)) {
                // echopre("Module exists");
                $yinfo = file_get_contents($yfile);
                // echopre("yfile-contents: " . print_r($yinfo, 1));

                $module_class = 
                $modpath . 
                $mod . '/' . $mod . '.php';

                // include module source
                // echopre("module class: $module_class");
                require( $module_class );
                
                $module_register_callback = 'register_' . $mod . '_module';
                // echo "<pre>register function: $module_register_callback</pre>";
                if(function_exists($module_register_callback)) {
                    // echo "<pre>Function <b>$module_register_callback</b> exists</pre>";
                    call_user_func( $module_register_callback );
                }
            }
        }

    }
}