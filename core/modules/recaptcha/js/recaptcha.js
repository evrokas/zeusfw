/**
 * reCAPTCHA v3 client half - see recaptcha.php's own docblock. Fills a
 * hidden recaptcha_token field before letting a form submit, for every
 * <form data-recaptcha-action="..."> on the page. The site key is read
 * from this module's own root element (data-zfw-recaptcha-sitekey), so a
 * form only needs the action name and a hidden token field:
 *
 *   <form method="post" data-recaptcha-action="contact_booking">
 *     <input type="hidden" name="recaptcha_token" value="">
 *
 * The security boundary is server-side (recaptchaClass::verify()/
 * isHuman(), core/lib/Recaptcha.php), which fails closed on a missing or
 * invalid token. This script therefore fails OPEN on purpose: if
 * grecaptcha never loads (blocked script, outage, ad blocker) the form
 * still submits with an empty token rather than trapping the visitor, and
 * the server rejects it as unverified instead of silently accepting it.
 *
 * Loaded with defer, so the whole document (every form included) has been
 * parsed by the time this runs.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-zfw-recaptcha]');
  var siteKey = root ? root.dataset.zfwRecaptchaSitekey || '' : '';

  document.querySelectorAll('form[data-recaptcha-action]').forEach(function (form) {
    var action = form.dataset.recaptchaAction;
    var tokenField = form.querySelector('input[name="recaptcha_token"]');
    if (!siteKey || !action || !tokenField) {
      return;
    }

    form.addEventListener('submit', function (event) {
      if (form.dataset.recaptchaReady === 'true') {
        return;
      }
      event.preventDefault();

      var submitNow = function (token) {
        tokenField.value = token || '';
        form.dataset.recaptchaReady = 'true';
        // submit(), not requestSubmit(): native validation already passed
        // (the submit event only fires after it), and requestSubmit() is
        // a silent no-op when called synchronously from inside this same
        // submit event - exactly the grecaptcha-missing path below.
        form.submit();
      };

      if (typeof window.grecaptcha === 'undefined') {
        submitNow('');
        return;
      }

      window.grecaptcha.ready(function () {
        window.grecaptcha.execute(siteKey, { action: action })
          .then(submitNow)
          .catch(function () { submitNow(''); });
      });
    });
  });
})();
