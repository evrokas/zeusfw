<?php

class messageModule extends moduleClass {
    public function run($params = array()) {
        return "messagg run";
    }

    public function render($params = array()) {
        // echopre("message module params: " . print_r($params, 1));
        // $msgsList = array_reverse(messageClass::sgetAllFilter('message', ['category' => $params['__arguments']['type']]));
        $msgsList = array_reverse(messageClass::sgetAllFilter('message'));

        // return "message 1";
        return $this->renderTemplate(['messages' => $msgsList]);
    }
}

function register_message_module() {
    global $kernel;

    $kernel->registerModule(new messageModule(__DIR__ , 'message', 'message.zetem'));
}
