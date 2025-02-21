<?php

class messageModule extends moduleClass {
    public function run($params = array()) {
        return "messagg run";
    }

    public function render($params = array()) {
        return "message 1";
        // return $this->renderTemplate($params);
    }

}

function register_message_module() {
    global $kernel;

    $kernel->registerModule(new messageModule(__DIR__ , 'message', 'message.zetem'));
}
