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

        // Get language switcher configuration
        $switcherConfig = $kernel->getConfig('language_switcher') ?? [];
        $mode = $switcherConfig['mode'] ?? 'path_prefix'; // Default to path_prefix mode
        $preservePage = $switcherConfig['preserve_page'] ?? true;

        $language_flags = [];
        $flagAttributes = [];
        $languageUrls = [];
        $langs = $kernel->getConfig('languages');

        foreach(array_keys($langs) as $lang) {
            // Set up flag attributes
            $flagAttributes[ $lang ] = new Attributes(['class' => 'flag']);
            $flagAttributes[ $lang ]->addAttribute(['src' => $this->getFlagURL($lang)]);
            $flagAttributes[ $lang ]->addAttribute(['alt' => $lang ]);
            $flagAttributes[ $lang ]->addAttribute(['data-lang' => $lang ]);

            if($kernel->getCurrentLanguage() === $lang) {
                $flagAttributes[ $lang ]->addAttribute(['class' => 'current']);
            }

            // Generate language-specific URL
            if ($preservePage) {
                // Use helper function which now generates path-based URLs by default
                $languageUrls[ $lang ] = get_current_url_with_lang($lang);
            } else {
                // Just link to language home page
                $languageUrls[ $lang ] = '/' . $lang . '/';
            }
        }

        // echopre("languages: " . print_r($language_flags, 1));
        return $this->RenderTemplate([
            'available' => array_keys($langs),
            'current' => $kernel->getCurrentLanguage(),
            'attributes' => $flagAttributes,
            'urls' => $languageUrls,
            'mode' => $mode,
            'preserve_page' => $preservePage
        ]);
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
