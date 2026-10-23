/* Session countdown (core/modules/session_timer/): a quiet «ends in …» label (in any element
 * with the attribute data-zfw-session-label, e.g. beside the user name) and a warning banner with
 * a «stay signed in» button when only a couple of minutes are left.
 *
 * The server decides when the session ends; this only shows it. Nothing here restarts the idle
 * clock except the explicit button: GET /session/status is read-only, POST /session/ping is the
 * click. So a forgotten tab is not kept alive by its own timer. Activity in another tab extends
 * the real session, so the local countdown is re-synced from the server once a minute (while the
 * tab is visible) and always before the banner first appears. When time runs out it offers
 * «sign in» in a NEW tab; it never redirects, so a half-filled form is kept. */
(function () {
    'use strict';
    var root = document.getElementById('zfw-session');
    if (!root) return;
    var d = root.dataset;
    var msg = root.querySelector('.zfs-msg'), clock = root.querySelector('.zfs-clock');
    var stay = root.querySelector('.zfs-stay'), login = root.querySelector('.zfs-login');
    // looked up on every paint: the script may run before the page's header (and its label) exists
    function labelEls() { return document.querySelectorAll('[data-zfw-session-label]'); }
    var warn = +d.warn || 120;
    var deadline = Date.now() + (+d.left) * 1000;
    var canExtend = d.limit !== 'cap';
    var checking = false, ended = false, lastCheck = 0;

    stay.textContent = d.tStay;
    login.textContent = d.tSignIn;
    login.href = d.loginUrl;

    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function mmss(s) { return Math.floor(s / 60) + ':' + pad(s % 60); }
    function leftSec() { return Math.max(0, Math.round((deadline - Date.now()) / 1000)); }
    // coarse on purpose: «3 d», «2h 05'», «47'»; the last 10 minutes show m:ss
    function human(s) {
        if (s >= 2 * 86400) return Math.floor(s / 86400) + ' ' + d.tDaysShort;
        if (s >= 3600) return Math.floor(s / 3600) + 'h ' + pad(Math.floor((s % 3600) / 60)) + "'";
        if (s >= 600) return Math.ceil(s / 60) + "'";
        return mmss(s);
    }

    function apply(res) {
        if (res && res.ok) {
            deadline = Date.now() + res.left * 1000;
            canExtend = typeof res.cap_left === 'number' ? res.cap_left > warn : res.limit !== 'cap';
            ended = false;
        } else if (res && res.signed_in === false) {
            deadline = Date.now();
        }
    }
    function call(url, post) {
        var init = { method: post ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
        if (post) init.headers['X-CSRF-Token'] = csrf();
        return fetch(url, init).then(function (r) { return r.json().catch(function () { return null; }); }).catch(function () { return null; });
    }
    // Another tab may have renewed the session: ask the server before warning.
    function check() {
        if (checking) return;
        checking = true; lastCheck = Date.now();
        call(d.statusUrl, false).then(function (res) { checking = false; if (res) apply(res); render(); });
    }

    function paintLabels(left) {
        var txt = left > 0 ? d.tEndsIn + ' ' + human(left) : d.tEndedShort;
        labelEls().forEach(function (l) {
            l.hidden = false;
            if (!l.title) l.title = d.tLabelTitle || '';
            if (l.textContent !== txt) l.textContent = txt;
            l.classList.toggle('soon', left > 0 && left < 600);
        });
    }

    function render() {
        var left = leftSec();
        paintLabels(left);
        if (left <= 0) {
            ended = true;
            root.hidden = false;
            msg.textContent = d.tExpired; clock.textContent = '';
            stay.hidden = true; login.hidden = false;
            return;
        }
        if (left > warn) { root.hidden = true; return; }
        root.hidden = false;
        login.hidden = true;
        clock.textContent = mmss(left);
        if (!canExtend) { msg.textContent = d.tCannotExtend + ' ' + d.tExpiresIn; stay.hidden = true; }
        else { msg.textContent = d.tExpiresIn; stay.hidden = false; }
    }

    stay.addEventListener('click', function () {
        stay.disabled = true;
        call(d.pingUrl, true).then(function (res) {
            stay.disabled = false;
            if (res && res.ok) { apply(res); render(); }
            else { msg.textContent = d.tFailed; }
        });
    });

    setInterval(function () {
        if (ended) return;
        var left = leftSec();
        // re-verify with the server when the warning is due (at most every 15 s while it is)
        if (left <= warn && Date.now() - lastCheck > 15000) check(); else render();
    }, 1000);
    // keep the countdown honest: a minute-by-minute re-sync while the tab is visible, and as soon
    // as it is back (a sleeping laptop misses its timers)
    setInterval(function () { if (!document.hidden && !ended) check(); }, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
    render();
})();
