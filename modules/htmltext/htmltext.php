<?php

// include ('Modules.php');


class htmltextModule extends moduleClass {
    protected $htmltext;

    function __construct($amodule, $atemplate, $text) {
        parent::__construct($amodule, $atemplate);
        $this->htmltext = $text;
    }

    function render($params = array()) {
        // global $Renderer;
        return($this->renderTemplate(['text' =>$this->htmltext, 'params' => $params]));
    }
}


function register_htmltext_module() {
    global $kernel;

    //
}