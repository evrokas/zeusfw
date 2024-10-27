
<?php
// modules


class moduleClass {
    private $moduledir;
    private $modulename;     // module name
    private $template;       // template associateed wi

    function __construct($adir, $amodule, $atemplate) {
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
        // override here to add functionality beyond render()
        return ($this->render($aparams));
    }
    function renderTemplate($aparms = array()) {
        // helper function to render a template
        global $kernel;

        $mods = $kernel->getConfig('modconf');
        if(isset($mods[$this->modulename]))
            $modconfig = $mods[$this->modulename];
        else $modconfig = null;

        if(isset($_SESSION['route_match']) && isset($_SESSION['route_match']['_routename'])) {
            $routename = $_SESSION['route_match']['_routename'];
        } else $routename = ''; 

        if($modconfig) {
            // echopre( print_r( $modconfig, 1));
            // echopre( print_r($_SESSION['route_match']['_routename'], 1));
            if(isset($modconfig['display'])) {
                $display = false;
                // echopre( print_r( $modconfig[$this->modulename]['display'], 1));
                if(array_search($routename, $modconfig['display']) !== false) {
                    // echopre('Display');
                    // echopre('Display');
                    $display = true;
                } else $display = false;
            } else 
            if(isset($modconfig['hide'])) {
                $display = true;
                if(array_search($routename, $modconfig['hide']) !== false) {
                    $display = false;
                }
            } else $display = true;              
        } else $display = true;

        if($display)
            return (
            // $this->modulename . ": renderTemplate(): " . $this->template . ": " .
            Renderer::render($this->template, $aparms));
        else return '';
    }
}



function registerModules() {
    global $kernel;

    $mods = $kernel->getConfig('modules');
    // echo("Register module: " . print_r($mods, 1) . "\n");

    foreach($mods['path'] as $mpath) {
        $modpath = $kernel->getbasepath() . '..' . $mpath;

        foreach($mods['modules'] as $mod) {
            // echo "<pre>module: $modpath => $mod</pre>";
            $yfile = $modpath . $mod . '/' . $mod . '.info.yaml';
            // echo "<pre>Testing $yfile</pre>";
            if(file_exists($yfile)) {
                // echo "<pre>Module $mod exists</pre>";
                $yinfo = file_get_contents($yfile);
                // echo "<pre>" . print_r($yinfo, 1) . "</pre>";

                $module_class = $modpath . $mod . '/' . $mod . '.php';

                // include module source
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