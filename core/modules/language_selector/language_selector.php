<?php


class LanguageSelectorModule extends moduleClass {
    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);

        $rt = yaml_parse_file(__DIR__.'/language_selector.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);

        $kernel->addConfig( $srt );

        // process newly added routes
        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    function getSimpleLocalizeFlagsURL($lang) {
        if($lang === 'en')$lang='gb';

        return "https://cdn.simplelocalize.io/public/v1/flags/$lang.svg";
    }

    function getFlagURL($lang) {
        return $this->getSimpleLocalizeFlagsURL($lang);
    }

    function render($params = array()) {
        global $kernel;
        
        $language_flags = [];
        $flagAttributes = [];
        $langs = $kernel->getConfig('languages');
        foreach($langs as $lang) {
            $flagAttributes[ $lang ] = new Attributes(['class' => 'flag']);
            $flagAttributes[ $lang ]->addAttribute(['src' => $this->getFlagURL($lang)]);
            $flagAttributes[ $lang ]->addAttribute(['alt' => $lang ]);

            if($kernel->getCurrentLanguage() === $lang) {
                $flagAttributes[ $lang ]->addAttribute(['class' => 'current']);
            }
        }
        // echopre("languages: " . print_r($language_flags, 1)); 
        return $this->RenderTemplate(['available' => $langs, 'current' => $kernel->getCurrentLanguage(),
            // 'flags' => $language_flags,
            'attributes' => $flagAttributes]);
    }

    function run($params = array()) {
        global $kernel;
        // echopre("in language_selector run() function ");
        // echopre("POST: " . print_r($_POST,1), print_r($_SERVER, 1));
        $data = json_decode(file_get_contents('php://input'), true);
        if(isset($_POST)) {
            echo json_encode(['message' => "language received ok", 'lang' => $data['language']]);
            $kernel->setCurrentLanguage( $data['language'] );
        } else {
            echo json_encode(['message' => "error", 'post' => $data]);
        }
        exit(-1);
    }
}


function register_language_selector_module() {
    global $kernel;

    $kernel->registerModule( new LanguageSelectorModule(__DIR__, 'language_selector', 'language_selector.zetem'));
}
