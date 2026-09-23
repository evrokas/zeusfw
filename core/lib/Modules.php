
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

    // Tracks which module names have already been require()'d, across
    // every $mods['path'] entry -- see the skip check below for why.
    $loaded = array();

    foreach($mods['path'] as $mpath) {
        $modpath = $kernel->getbasepath() . '..' . $mpath;
        // $modpath = $mpath;

        foreach($mods['modules'] as $mod) {
            if(isset($loaded[$mod])) {
                // Already require()'d from an earlier (higher-priority)
                // entry in $mods['path'] -- this loop previously had no
                // such check, so a module name present under *two*
                // configured paths (e.g. an app's own web/modules/<mod>/
                // left on disk after that module moved into
                // core/modules/<mod>/, still listed in the app's own
                // `modules:` config either way) got require()'d twice,
                // fatal ("Cannot redeclare function register_<mod>
                // _module()") the moment both files define the same
                // global callback function. The first path a module is
                // found under always wins now -- $mods['path'] is already
                // ordered by the app's own config (core before an app's
                // own web/modules/, in every app on this framework), so
                // this is the same priority a working, non-colliding
                // deploy already had by construction.
                continue;
            }

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

                $loaded[$mod] = true;
            }
        }

    }
}