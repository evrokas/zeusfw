<?php

class LanguageSelectorModule extends moduleClass {
    function render($params = array()) {
        global $kernel;

        $langs = $kernel->getConfig('languages');

        return $this->RenderTemplate(['available' => $langs, 'current' => $kernel->getCurrentLanguage()]);
    }
}


function register_language_selector_module() {
    global $kernel;

    $kernel->registerModule( new LanguageSelectorModule(__DIR__, 'language_selector', 'language_selector.zetem'));
}
