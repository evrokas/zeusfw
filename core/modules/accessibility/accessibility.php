<?php

/*
 * Real ZeusFW module for the accessibility widget - a floating toggle
 * button + panel offering disability profiles (Blind, Color Blindness,
 * Dyslexia, Epilepsy Safe) and individual page options (Contrast, Font
 * Size, Letter Spacing, Hide Images). Converted from the original
 * core/lib/Accessibility.php (accessibilityClass::render(), a
 * self-contained heredoc with inline <style>/<script>) into this
 * moduleClass-based shape - see this framework's own CLAUDE.md for the
 * full writeup of why (a genuine, verified timing hazard rules out
 * attach_library() as the asset-delivery mechanism here, so CSS/JS URLs
 * are resolved directly via resolveModuleDir()/rel_url() instead and
 * emitted as plain <link>/<script src> tags at the template's own
 * position - functionally equivalent, without depending on
 * Kernel::renderPage()'s asset-array timing).
 *
 * Registration is app opt-in, not unconditional from core/bootstrap.php:
 * an app adds "accessibility" to its own settings.info.yaml's existing
 * modules: list (see erweb's config for the concrete example) - this
 * keeps the change's blast radius to whichever app opts in, since
 * core/bootstrap.php is shared by every app vendoring this checkout.
 *
 * Every effect this widget applies is done by toggling classes/CSS custom
 * properties on <html> and reading them back from generic selectors (see
 * css/accessibility.css) - it has no knowledge of, and makes no
 * assumption about, the calling app's own markup or stylesheet, which is
 * what makes it safe to drop into any ZeusFW app.
 *
 * State (which profile/options are active) persists in the visitor's own
 * browser via localStorage - never sent to the server, never shared
 * across visitors.
 */

class accessibilityModule extends moduleClass {
    // moduleClass's own $moduledir is private, not inherited-accessible -
    // this module needs its own copy of $adir to resolve its CSS/JS URLs
    // at render() time (resolveModuleDir() needs the module's directory,
    // not just its name).
    private $adir;

    // Bilingual labels. A caller passing a language not listed here falls
    // back to 'en' (see render()) - matches the fallback convention
    // erweb's own erweb_axis_label()/DOCARC_RETRIEVAL_SECRET_LABELS-style
    // lookups already use. Copied verbatim from the original
    // accessibilityClass::LABELS.
    const LABELS = [
        'el' => [
            'toggleLabel' => 'Επιλογές Προσβασιμότητας',
            'panelTitle' => 'Προσβασιμότητα',
            'closeLabel' => 'Κλείσιμο',
            'resetLabel' => 'Επαναφορά όλων',
            'profilesHeading' => 'Προφίλ',
            'optionsHeading' => 'Επιλογές Σελίδας',
            'profileBlind' => 'Τυφλότητα',
            'profileBlindDesc' => 'Ανάγνωση περιεχομένου φωναχτά',
            'profileColorblind' => 'Αχρωματοψία',
            'profileColorblindDesc' => 'Μείωση εξάρτησης από το χρώμα',
            'profileDyslexia' => 'Δυσλεξία',
            'profileDyslexiaDesc' => 'Ευανάγνωστη μορφοποίηση κειμένου',
            'profileEpilepsy' => 'Επιληψία',
            'profileEpilepsyDesc' => 'Διακοπή κίνησης και εφέ',
            'optionContrast' => 'Αντίθεση',
            'optionFontSize' => 'Μέγεθος Γραμματοσειράς',
            'optionLetterSpacing' => 'Απόσταση Γραμμάτων',
            'optionHideImages' => 'Απόκρυψη Εικόνων',
            'contrastNormal' => 'Κανονική',
            'contrastHigh' => 'Υψηλή',
            'contrastInvert' => 'Αντεστραμμένη',
            'spacingNormal' => 'Κανονική',
            'spacingWide' => 'Πλατιά',
            'spacingWider' => 'Πλατύτερη',
            'readAloudPlay' => 'Ανάγνωση Σελίδας',
            'readAloudPause' => 'Παύση',
            'readAloudResume' => 'Συνέχεια',
            'readAloudStop' => 'Διακοπή',
            'unsupported' => 'Μη διαθέσιμο σε αυτόν τον browser',
        ],
        'en' => [
            'toggleLabel' => 'Accessibility Options',
            'panelTitle' => 'Accessibility',
            'closeLabel' => 'Close',
            'resetLabel' => 'Reset all',
            'profilesHeading' => 'Profiles',
            'optionsHeading' => 'Page Options',
            'profileBlind' => 'Blind',
            'profileBlindDesc' => 'Read content aloud',
            'profileColorblind' => 'Color Blindness',
            'profileColorblindDesc' => 'Reduce reliance on color',
            'profileDyslexia' => 'Dyslexia',
            'profileDyslexiaDesc' => 'Easier-to-read text layout',
            'profileEpilepsy' => 'Epilepsy',
            'profileEpilepsyDesc' => 'Stop motion and effects',
            'optionContrast' => 'Contrast',
            'optionFontSize' => 'Font Size',
            'optionLetterSpacing' => 'Letter Spacing',
            'optionHideImages' => 'Hide Images',
            'contrastNormal' => 'Normal',
            'contrastHigh' => 'High',
            'contrastInvert' => 'Inverted',
            'spacingNormal' => 'Normal',
            'spacingWide' => 'Wide',
            'spacingWider' => 'Wider',
            'readAloudPlay' => 'Read Page Aloud',
            'readAloudPause' => 'Pause',
            'readAloudResume' => 'Resume',
            'readAloudStop' => 'Stop',
            'unsupported' => 'Not available in this browser',
        ],
    ];

    // The full, fixed set of valid profile/option keys - render()'s own
    // 'profiles'/'options' overrides are intersected against these, so a
    // caller can only narrow what's enabled, never invent a nonexistent
    // one.
    const VALID_PROFILES = ['blind', 'colorblind', 'dyslexia', 'epilepsy'];
    const VALID_OPTIONS = ['contrast', 'fontsize', 'letterspacing', 'hideimages'];

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->adir = $adir;

        $rt = yaml_parse_file(__DIR__ . '/accessibility.yaml');
        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);
    }

    /*
     * $params:
     *   'lang'     => 'el' | 'en' (default 'en') - which LABELS set to use.
     *   'position' => 'left' | 'right' (default 'left') - which bottom
     *                 corner the toggle button sits in.
     *   'readAloudSelector' => CSS selector for the element whose text the
     *                 Blind profile's read-aloud control reads (default
     *                 'main, [role=main]' - falls back to <body> in JS if
     *                 nothing matches).
     *   'profiles' => optional array narrowing which of VALID_PROFILES
     *                 render, overriding accessibility.yaml's
     *                 default_options.profiles for this one call.
     *   'options'  => same, for VALID_OPTIONS / default_options.options.
     */
    function render($params = array()) {
        global $kernel;

        $lang = $params['lang'] ?? 'en';
        $labels = self::LABELS[$lang] ?? self::LABELS['en'];
        $position = ($params['position'] ?? 'left') === 'right' ? 'right' : 'left';
        $readSelector = $params['readAloudSelector'] ?? 'main, [role="main"]';

        $defaultOptions = $kernel->getConfig('default_options');
        $defaultProfiles = is_array($defaultOptions['profiles'] ?? null) ? $defaultOptions['profiles'] : self::VALID_PROFILES;
        $defaultEnabledOptions = is_array($defaultOptions['options'] ?? null) ? $defaultOptions['options'] : self::VALID_OPTIONS;

        $enabledProfiles = array_values(array_intersect(
            self::VALID_PROFILES,
            isset($params['profiles']) && is_array($params['profiles']) ? $params['profiles'] : $defaultProfiles
        ));
        $enabledOptions = array_values(array_intersect(
            self::VALID_OPTIONS,
            isset($params['options']) && is_array($params['options']) ? $params['options'] : $defaultEnabledOptions
        ));

        $cssUrl = rel_url($kernel->resolveModuleDir('@core/css/accessibility.css', $this->adir, $this->getName()));
        $jsUrl = rel_url($kernel->resolveModuleDir('@core/js/accessibility.js', $this->adir, $this->getName()));

        return $this->renderTemplate([
            'lang' => $lang,
            'labels' => $labels,
            'position' => $position,
            'readAloudSelector' => $readSelector,
            'storageKey' => 'zfw_a11y_state',
            'enabledProfiles' => $enabledProfiles,
            'enabledOptions' => $enabledOptions,
            'cssUrl' => $cssUrl,
            'jsUrl' => $jsUrl,
        ]);
    }

    function run($params = array()) {
        return $this->render($params);
    }
}

function register_accessibility_module() {
    global $kernel;
    $kernel->registerModule(new accessibilityModule(__DIR__, 'accessibility', 'accessibility.zetem'));
}
