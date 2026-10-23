/* Session end warning (core/modules/session_timer/). The server decides when the session
 * ends; this only warns. It polls GET /session/status (read-only: never counts as activity)
 * and renews only through POST /session/ping, when the user presses the button. When time
 * runs out it offers "sign in" in a NEW tab; it never redirects, so a half-filled form is kept. */
(function () {
    'use strict';
    var root = document.getElementById('zfw-session');
    if (!root) return;
    var d = root.dataset;
    var msg = root.querySelector('.zfs-msg'), clock = root.querySelector('.zfs-clock');
    var stay = root.querySelector('.zfs-stay'), login = root.querySelector('.zfs-login');
    var warn = +d.warn || 120;
    var deadline = Date.now() + (+d.left) * 1000;
    var limit = d.limit;
    var checking = false, ended = false, lastCheck = 0;

    stay.textContent = d.tStay;
    login.textContent = d.tSignIn;
    login.href = d.loginUrl;

    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
    function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r; }
    function leftSec() { return Math.max(0, Math.round((deadline - Date.now()) / 1000)); }

    function apply(res) {
        if (res && res.ok) { deadline = Date.now() + res.left * 1000; limit = res.limit; ended = false; return; }
        if (res && res.signed_in === false) { deadline = Date.now(); }
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

    function render() {
        var left = leftSec();
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
        clock.textContent = fmt(left);
        if (limit === 'cap') { msg.textContent = d.tCannotExtend + ' ' + d.tExpiresIn; stay.hidden = true; }
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
    // a sleeping laptop misses its timers: look again as soon as the tab is back
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
    render();
})();
