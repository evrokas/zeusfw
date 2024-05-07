<?php

class notificationsModule extends moduleClass {
    function render($params = array()) {
        global $kernel;

            $prms = array();
            foreach(['notice', 'error', 'warning'] as $level) {
                $s = $kernel->getStatus($level, true);
                // echo "$level " . print_r($s, 1) . "<br>";

                if(count($s))$prms[ $level ] = implode('<br>', $s);
            }

        return $this->RenderTemplate($prms);
    }
}


function register_notifications_module() {
    global $kernel;

    $kernel->registerModule( new notificationsModule(__DIR__, 'notifications', 'notifications.zetem'));
}