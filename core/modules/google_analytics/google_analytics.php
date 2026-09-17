<?php

/*
 * Google Analytics (GA4) module - consent-gated gtag.js loading plus a
 * cookie-consent banner, reusable by any app on this framework. First
 * adopter: erweb, which already had a hand-rolled, single-app version of
 * exactly this (web/js/consent.js, web/css/consent.css,
 * templates/partials/erweb-consent-banner.zetem) sitting fully unhooked -
 * see erweb's own CLAUDE.md for the migration off those files onto this
 * module. Built as a reusable core module rather than promoting erweb's
 * own files verbatim, since GA4 consent-gating has nothing app-specific
 * about it once the measurement ID and banner copy are parameterized -
 * every future app on this framework gets it as a one-line modules: list
 * addition instead of re-implementing the same consent/gtag dance.
 *
 * Same moduleClass/render($params)/opt-in-via-modules:-list shape as
 * core/modules/accessibility - see that module's own docblock for why a
 * moduleClass, not attach_library(), delivers this module's CSS/JS: the
 * same verified timing hazard applies here, since this module is meant
 * to be invoked ad hoc from inside a template's own body (e.g. right
 * before </body>, next to the accessibility widget's own call site), not
 * via a structure: region - attach_library() would run after
 * Kernel::renderPage()'s $links/$foot_links arrays are already built, a
 * silent no-op.
 *
 * *** Fails closed on consent, the same way Recaptcha.php fails closed on
 * verification: no explicit "Accept" (stored in localStorage, this
 * browser only - the same per-visitor, never-shared-with-the-server
 * convention accessibilityClass's own state already uses) means gtag.js
 * is never even requested, not merely configured to no-op. A missing/
 * empty/TODO-containing measurementId also short-circuits before any
 * network request fires - see js/google-analytics.js's own loadGtag()
 * guard - so a fresh app that hasn't configured a real ID yet, or one
 * that's deliberately keeping GA4 off pending consent-banner review,
 * never leaks a request to Google regardless of what a visitor clicks. ***
 */

class googleAnalyticsModule extends moduleClass {
    // moduleClass's own $moduledir is private, not inherited-accessible -
    // this module needs its own copy of $adir to resolve its CSS/JS URLs
    // at render() time, same reasoning as accessibilityModule's own $adir.
    private $adir;

    // Bilingual banner copy, matching accessibilityModule::LABELS'
    // exact shape (fixed languages here, fall back to 'en' for anything
    // else). A caller can override any individual string via render()'s
    // own 'labels' param (merged over these, not required to replace the
    // whole set - see render()'s docblock), so an app in a third
    // language, or one that just wants different wording, never has to
    // fork this file.
    const LABELS = [
        'el' => [
            'bannerText' => 'Χρησιμοποιούμε cookies μόνο για ανώνυμα στατιστικά επισκεψιμότητας (Google Analytics). Δεν φορτώνονται πριν δώσετε τη συγκατάθεσή σας.',
            'accept' => 'Αποδοχή',
            'reject' => 'Απόρριψη',
            'ariaLabel' => 'Συναίνεση cookies',
        ],
        'en' => [
            'bannerText' => 'We use cookies only for anonymous visit statistics (Google Analytics). Nothing loads until you consent.',
            'accept' => 'Accept',
            'reject' => 'Reject',
            'ariaLabel' => 'Cookie consent',
        ],
    ];

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->adir = $adir;

        $rt = yaml_parse_file(__DIR__ . '/google_analytics.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);
    }

    /*
     * $params:
     *   'measurementId' => 'G-XXXXXXXXXX' - the app's own GA4 property
     *                 id. Never baked into this framework file - every
     *                 app supplies its own via whatever config
     *                 mechanism it already uses (e.g. erweb's
     *                 erweb_ga4_measurement_id(), a site.info.yaml key).
     *                 Missing/blank/'TODO'-prefixed is handled, not
     *                 fatal - see this file's own header comment.
     *   'lang'          => 'el' | 'en' (default 'en') - which LABELS set
     *                 to use for the banner.
     *   'labels'        => optional array of overrides merged over
     *                 LABELS[$lang] (e.g. ['bannerText' => '...']).
     *   'storageKey'    => localStorage key the visitor's choice is
     *                 stored under (default 'cookie-consent') - override
     *                 only if an app already used a different key for a
     *                 prior consent mechanism and needs to keep reading
     *                 it rather than re-prompting every returning
     *                 visitor.
     */
    function render($params = array()) {
        global $kernel;

        $lang = $params['lang'] ?? 'en';
        $labels = array_merge(
            self::LABELS[$lang] ?? self::LABELS['en'],
            is_array($params['labels'] ?? null) ? $params['labels'] : []
        );

        $measurementId = trim((string) ($params['measurementId'] ?? ''));
        $storageKey = $params['storageKey'] ?? 'cookie-consent';

        $cssUrl = rel_url($kernel->resolveModuleDir('@core/css/google-analytics.css', $this->adir, $this->getName()));
        $jsUrl = rel_url($kernel->resolveModuleDir('@core/js/google-analytics.js', $this->adir, $this->getName()));

        return $this->renderTemplate([
            'lang' => $lang,
            'labels' => $labels,
            'measurementId' => $measurementId,
            'storageKey' => $storageKey,
            'cssUrl' => $cssUrl,
            'jsUrl' => $jsUrl,
        ]);
    }

    function run($params = array()) {
        return $this->render($params);
    }
}

function register_google_analytics_module() {
    global $kernel;
    $kernel->registerModule(new googleAnalyticsModule(__DIR__, 'google_analytics', 'google_analytics.zetem'));
}
