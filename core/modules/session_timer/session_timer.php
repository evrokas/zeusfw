<?php

/*
 * Warns the signed-in user shortly before the session ends (core/lib/SessionPolicy.php) and lets
 * them renew it. Only a courtesy: the server decides when the session ends. Renders nothing when
 * the app has not turned the session policy on, or nobody is signed in.
 *
 * Opt-in like accessibility/live_edit: list `session_timer` under `modules:` and call
 * `{{ module('session_timer') }}` once in the page template. Its CSS/JS are emitted directly
 * (resolveModuleDir()/rel_url()) for the same attach_library() timing reason those document.
 */

class sessionTimerModule extends moduleClass {
    private $adir;

    const LABELS = [
        'el' => [
            'expiresIn' => 'Η σύνδεσή σας λήγει σε',
            'stay' => 'Παραμονή',
            'cannotExtend' => 'Δεν μπορεί να παραταθεί. Αποθηκεύστε τη δουλειά σας.',
            'expired' => 'Η σύνδεσή σας έληξε. Συνδεθείτε ξανά σε νέα καρτέλα, ώστε να μη χαθούν όσα έχετε πληκτρολογήσει εδώ.',
            'signIn' => 'Σύνδεση',
            'failed' => 'Δεν ήταν δυνατή η παράταση. Δοκιμάστε ξανά.',
        ],
        'en' => [
            'expiresIn' => 'Your session ends in',
            'stay' => 'Stay signed in',
            'cannotExtend' => 'It cannot be extended. Save your work.',
            'expired' => 'Your session has ended. Sign in again in a new tab so nothing you typed here is lost.',
            'signIn' => 'Sign in',
            'failed' => 'Could not extend the session. Try again.',
        ],
    ];

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);
        $this->adir = $adir;
    }

    function run($params = array()) {
        return $this->render($params);
    }

    function render($params = array()) {
        global $kernel;
        $p = function_exists('zeusfw_session_policy') ? zeusfw_session_policy() : null;
        if (!$p || empty($_SESSION['user']) || !isset($_SESSION['zfw_login_at'])) {
            return '';
        }
        $lang = (string)($params['lang'] ?? $kernel->getCurrentLanguage());
        $lang = in_array($lang, ['el', 'gr'], true) ? 'el' : 'en';
        $labels = array_merge(self::LABELS[$lang], is_array($params['labels'] ?? null) ? $params['labels'] : []);
        $s = zeusfw_session_seconds_left($p, time());

        $css = rel_url($kernel->resolveModuleDir('@core/css/session_timer.css', $this->adir, $this->getName()) . '?' . time());
        $js = rel_url($kernel->resolveModuleDir('@core/js/session_timer.js', $this->adir, $this->getName()) . '?' . time());

        return $this->renderTemplate([
            'cssUrl' => $css,
            'jsUrl' => $js,
            'left' => $s['left'],
            'limit' => $s['limit'],
            'warn' => $p['warn'],
            'statusUrl' => rel_url('/session/status'),
            'pingUrl' => rel_url('/session/ping'),
            'loginUrl' => rel_url((string)($params['loginUrl'] ?? '/login')),
            'labels' => $labels,
        ]);
    }
}

function register_session_timer_module() {
    global $kernel;
    $kernel->registerModule(new sessionTimerModule(__DIR__, 'session_timer', 'session_timer.zetem'));
}
