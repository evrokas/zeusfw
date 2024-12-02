<?php

// include ('Modules.php');


class htmltextModule extends moduleClass {
    protected $htmltext;

    function __construct($adir, $amodule, $atemplate, $text) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->htmltext = $text;
    }

    function render($params = array()) {
        return($this->renderTemplate(['modulename' => $this->getName(), 'text' =>$this->htmltext, 'params' => $params]));
    }
}


function register_htmltext_module() {
    global $kernel;

    //
}
