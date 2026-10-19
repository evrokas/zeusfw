/**
 * Cookie consent + GA4 gating - see google_analytics.php's own docblock
 * for the full design. gtag must never fire before explicit consent: the
 * gtag.js script tag is only ever created after an "Accept" click, or
 * immediately on a later page load if consent was already stored as
 * accepted in this browser. "Reject" (or no choice yet) means gtag.js is
 * never even requested, not just configured to no-op.
 *
 * No third-party consent-management platform - localStorage plus two
 * buttons is the whole mechanism (ported from erweb's own original,
 * single-app consent.js, generalized to read its measurement id/storage
 * key from the banner root's own data-zfw-ga-* attributes instead of a
 * hardcoded/currentScript-sourced value, so this one file works
 * unchanged across every app that adopts this module).
 *
 * Deliberately carries no defer/type="module" on its own <script> tag
 * (see google_analytics.zetem) - it runs synchronously at its DOM
 * position, immediately after the banner markup that precedes it in the
 * same template, so `[data-zfw-ga-banner]` is already in the DOM by the
 * time this file executes. Same reasoning as core/modules/accessibility's
 * own external script tag.
 */
(function () {
  var root = document.querySelector('[data-zfw-ga-banner]');
  if (!root) {
    return;
  }

  var measurementId = root.dataset.zfwGaMeasurementId || '';
  var storageKey = root.dataset.zfwGaStorageKey || 'cookie-consent';
  var acceptBtn = root.querySelector('[data-zfw-ga-accept]');
  var rejectBtn = root.querySelector('[data-zfw-ga-reject]');

  function loadGtag() {
    if (!measurementId || measurementId.indexOf('TODO') !== -1 || window.__zfwGtagLoaded) {
      return;
    }
    window.__zfwGtagLoaded = true;

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
      window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', measurementId);
  }

  function hideBanner() {
    root.hidden = true;
  }

  var stored = null;
  try {
    stored = window.localStorage.getItem(storageKey);
  } catch (e) {
    // localStorage unavailable (private mode, disabled) - fall through
    // as "no choice yet"; the banner still works, it just can't persist
    // the visitor's choice across a reload.
  }

  if (stored === 'accepted') {
    loadGtag();
  } else if (stored !== 'rejected') {
    root.hidden = false;
  }

  if (acceptBtn) {
    acceptBtn.addEventListener('click', function () {
      try {
        window.localStorage.setItem(storageKey, 'accepted');
      } catch (e) {
        /* ignore */
      }
      loadGtag();
      hideBanner();
    });
  }

  if (rejectBtn) {
    rejectBtn.addEventListener('click', function () {
      try {
        window.localStorage.setItem(storageKey, 'rejected');
      } catch (e) {
        /* ignore */
      }
      // Withdrawing consent after an earlier "Accept" (via a reopen link,
      // below): gtag.js may already be running on this page, so also set
      // Google's own documented opt-out flag, which stops it sending
      // anything further before the next page load (where it is simply
      // never requested again).
      if (window.__zfwGtagLoaded && measurementId) {
        window['ga-disable-' + measurementId] = true;
      }
      hideBanner();
    });
  }

  // Any element carrying data-zfw-ga-reopen (e.g. a "Cookie settings"
  // link in an app's footer) shows the banner again, so a visitor can
  // change or withdraw their choice as easily as they gave it (GDPR Art.
  // 7(3)) - without it, a stored choice hides the banner forever with no
  // way back short of clearing site data. Delegated from document, so
  // the trigger may sit anywhere on the page, before or after this
  // script's own position.
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest ? event.target.closest('[data-zfw-ga-reopen]') : null;
    if (!trigger) {
      return;
    }
    event.preventDefault();
    root.hidden = false;
    if (acceptBtn) {
      acceptBtn.focus();
    }
  });
})();
