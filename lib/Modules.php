
<?php
// modules


class moduleClass {
    private $modulename;     // module name
    private $template;       // template associateed wi

    function __construct($amodule, $atemplate) {
        $this->modulename = $amodule;
        $this->template = $atemplate;

    }
    function render($params = array()) {
        $params[] = ['module' => $this->template];

        // echo "render module " . $this->modulename;
        return $this->renderTemplate( $params );

        // return $Renderer->render( $this->template, $params ); 
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
        global $Renderer;

        return (
            // $this->modulename . ": renderTemplate(): " . $this->template . ": " .
            $Renderer->render($this->template, $aparms));
    }
}



function registerModules() {
    global $kernel;

    $mods = $kernel->getConfig('modules');
    // echo(print_r($mods));

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