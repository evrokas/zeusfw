<?php

/*
 * reCAPTCHA v3 module - the client half of reCAPTCHA support, pairing
 * with core/lib/Recaptcha.php's recaptchaClass (the server half, which
 * verifies the token and fails closed). Before this module, every app had
 * to hand-wire the api.js <script> tag plus its own token-filling JS
 * (erweb's web/js/recaptcha-widget.js was the one real example); now an
 * app lists `recaptcha` under its own modules: and calls, from the one
 * template that has a protected form:
 *
 *   {{ module('recaptcha', ['siteKey' => ..., 'lang' => $lang]) }}
 *
 * then gives the form data-recaptcha-action="<action>" and a hidden
 * <input name="recaptcha_token">, and verifies server-side with
 * recaptchaClass::isHuman($_POST['recaptcha_token'] ?? null, $secret,
 * recaptchaClass::DEFAULT_SCORE_THRESHOLD, '<action>', $remoteIp).
 *
 * Page-scoped by design, not a site-wide region: Google's api.js is only
 * requested on pages that actually render this module, so every other
 * page stays free of the third-party request. Same moduleClass/render()
 * shape - and the same reason for emitting its own <link>/<script> tags
 * instead of attach_library() - as core/modules/google_analytics (see
 * that module's docblock for the Kernel::renderPage() asset-timing
 * hazard).
 *
 * An empty/'TODO'-containing siteKey renders nothing at all: no request
 * to Google, and forms submit with an empty token, which the server half
 * then rejects as not_configured - the same fail-closed contract
 * recaptchaClass already has, never a silently-unprotected form.
 */

class recaptchaModule extends moduleClass {
    private $adir;

    // Google's required attribution text when the floating badge is
    // hidden (hideBadge). Wording follows Google's own FAQ; links are to
    // Google's policies, not the app's.
    const LABELS = [
        'el' => [
            'notice' => 'Αυτός ο ιστότοπος προστατεύεται από το reCAPTCHA και ισχύουν η @privacy και οι @terms της Google.',
            'privacy' => 'Πολιτική Απορρήτου',
            'terms' => 'Όροι Χρήσης',
        ],
        'en' => [
            'notice' => 'This site is protected by reCAPTCHA and the Google @privacy and @terms apply.',
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of Service',
        ],
    ];

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->adir = $adir;

        $rt = yaml_parse_file(__DIR__ . '/recaptcha.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);
    }

    /** True for a real-looking site key; false for empty or a TODO placeholder. */
    static function isConfigured(?string $key): bool {
        $key = trim((string) $key);
        return $key !== '' && stripos($key, 'TODO') === false;
    }

    /*
     * $params:
     *   'siteKey'   => the app's reCAPTCHA v3 site key (public). Required;
     *                  empty/TODO renders nothing (see header).
     *   'lang'      => 'el' | 'en' (default 'en') - notice text and api.js hl.
     *   'hideBadge' => bool (default false) - hide Google's floating
     *                  bottom-right badge (useful when it would collide with
     *                  an app's own fixed UI) and render the attribution
     *                  notice in its place, as Google requires.
     *   'labels'    => optional overrides merged over LABELS[$lang].
     */
    function render($params = array()) {
        global $kernel;

        $siteKey = trim((string) ($params['siteKey'] ?? ''));
        if (!self::isConfigured($siteKey)) {
            return '';
        }

        $lang = $params['lang'] ?? 'en';
        $labels = array_merge(
            self::LABELS[$lang] ?? self::LABELS['en'],
            is_array($params['labels'] ?? null) ? $params['labels'] : []
        );
        $hideBadge = !empty($params['hideBadge']);

        $apiUrl = 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($siteKey)
            . '&hl=' . rawurlencode((string) $lang);

        // Built here rather than in the template: ZETEM treats any `|`
        // inside {{ }} as a filter separator, so ENT_QUOTES | ENT_HTML5
        // can't be written there. Every label is escaped; only the two
        // fixed Google links are markup.
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $noticeHtml = strtr($esc($labels['notice']), [
            '@privacy' => '<a href="https://policies.google.com/privacy" rel="noopener" target="_blank">' . $esc($labels['privacy']) . '</a>',
            '@terms' => '<a href="https://policies.google.com/terms" rel="noopener" target="_blank">' . $esc($labels['terms']) . '</a>',
        ]);

        return $this->renderTemplate([
            'siteKey' => $siteKey,
            'apiUrl' => $apiUrl,
            'hideBadge' => $hideBadge,
            'noticeHtml' => $noticeHtml,
            'cssUrl' => rel_url($kernel->resolveModuleDir('@core/css/recaptcha.css', $this->adir, $this->getName())),
            'jsUrl' => rel_url($kernel->resolveModuleDir('@core/js/recaptcha.js', $this->adir, $this->getName())),
        ]);
    }

    function run($params = array()) {
        return $this->render($params);
    }
}

function register_recaptcha_module() {
    global $kernel;
    $kernel->registerModule(new recaptchaModule(__DIR__, 'recaptcha', 'recaptcha.zetem'));
}
