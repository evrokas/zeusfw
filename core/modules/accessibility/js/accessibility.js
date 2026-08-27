/*
 * Behavior for the accessibility widget (core/modules/accessibility/).
 * Moved from the original core/lib/Accessibility.php's inline <script>
 * block when that class was converted into a real ZeusFW module - see
 * core/modules/accessibility/accessibility.php and this framework's own
 * CLAUDE.md for the conversion writeup. The interaction logic below is
 * unchanged; what changed is *how* it learns the storage key, read-aloud
 * selector, and bilingual labels that used to be PHP-interpolated
 * directly into this block - those now come from data-zfw-a11y-* attributes
 * the .zetem template renders onto #zfw-a11y-root (see accessibility.zetem),
 * read here via the standard DOM dataset API.
 *
 * Loaded as a plain, non-deferred <script src>, at the same DOM position
 * the original inline <script> occupied - deliberately not `defer`, which
 * would delay applying a returning visitor's saved state until the whole
 * document finishes parsing, working against the point of restoring it
 * as early as possible.
 *
 * Every profile/option control below is null-checked before its listener
 * is wired: the .zetem template only renders the controls enabled via
 * accessibility.yaml's default_options (or a render() call's own
 * 'profiles'/'options' override) - disabled ones are omitted from the
 * markup entirely, not just hidden, so their corresponding element here
 * is genuinely absent from the DOM, not merely invisible.
 */
(function () {
  var root = document.getElementById('zfw-a11y-root');
  if (!root) return;

  var STORAGE_KEY = root.dataset.zfwA11yStorageKey;
  var READ_SELECTOR = root.dataset.zfwA11yReadSelector;
  var FONT_STEPS = [1, 1.15, 1.3, 1.45, 1.6];

  var html = document.documentElement;
  var toggle = document.getElementById('zfw-a11y-toggle');
  var panel = document.getElementById('zfw-a11y-panel');
  var closeBtn = document.getElementById('zfw-a11y-close');
  var resetBtn = document.getElementById('zfw-a11y-reset');
  if (!toggle || !panel || !closeBtn || !resetBtn) return;

  var defaults = {
    profiles: { blind: false, colorblind: false, dyslexia: false, epilepsy: false },
    contrast: 'normal',
    fontStep: 0,
    spacing: 'normal',
    hideImages: false
  };

  var state;
  try {
    var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    state = saved ? Object.assign({}, defaults, saved, { profiles: Object.assign({}, defaults.profiles, saved.profiles || {}) }) : JSON.parse(JSON.stringify(defaults));
  } catch (e) {
    state = JSON.parse(JSON.stringify(defaults));
  }

  function persist() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) {}
  }

  function setClass(name, on) {
    html.classList.toggle(name, !!on);
  }

  var CONTRAST_LABELS = {
    normal: root.dataset.zfwA11yContrastNormal,
    high: root.dataset.zfwA11yContrastHigh,
    invert: root.dataset.zfwA11yContrastInvert
  };
  var SPACING_LABELS = {
    normal: root.dataset.zfwA11ySpacingNormal,
    wide: root.dataset.zfwA11ySpacingWide,
    wider: root.dataset.zfwA11ySpacingWider
  };
  var CONTRAST_ORDER = ['normal', 'high', 'invert'];
  var SPACING_ORDER = ['normal', 'wide', 'wider'];

  var contrastBtn = document.getElementById('zfw-a11y-contrast');
  var spacingBtn = document.getElementById('zfw-a11y-spacing');
  var imagesBtn = document.getElementById('zfw-a11y-images');
  var fontValueEl = document.getElementById('zfw-a11y-font-value');
  var fontDec = document.getElementById('zfw-a11y-font-dec');
  var fontInc = document.getElementById('zfw-a11y-font-inc');
  var profileButtons = Array.prototype.slice.call(document.querySelectorAll('[data-zfa-profile]'));
  var readAloudWrap = document.getElementById('zfw-a11y-readaloud');
  var fxContrast = document.getElementById('zfw-a11y-fx-contrast');
  var fxColorblind = document.getElementById('zfw-a11y-fx-colorblind');

  function apply() {
    // Contrast/invert/colorblind are mix-blend-mode overlays living inside
    // #zfw-a11y-root (see the .zfa-fx rules) - toggled on those elements
    // directly, never on <html>, so nothing here ever gives the host
    // page's own content a filtered ancestor (see accessibility.css's
    // comment above .zfa-fx for why that matters).
    if (fxContrast) {
      fxContrast.classList.toggle('is-high', state.contrast === 'high');
      fxContrast.classList.toggle('is-invert', state.contrast === 'invert');
    }
    if (fxColorblind) {
      fxColorblind.classList.toggle('is-active', state.profiles.colorblind);
    }
    setClass('zfw-a11y-dyslexia', state.profiles.dyslexia);
    setClass('zfw-a11y-epilepsy', state.profiles.epilepsy);
    setClass('zfw-a11y-blind', state.profiles.blind);
    setClass('zfw-a11y-hide-images', state.hideImages);
    setClass('zfw-a11y-spacing-wide', state.spacing === 'wide');
    setClass('zfw-a11y-spacing-wider', state.spacing === 'wider');
    html.style.setProperty('--zfa-font-scale', String(FONT_STEPS[state.fontStep] || 1));

    if (contrastBtn) {
      contrastBtn.textContent = CONTRAST_LABELS[state.contrast];
      contrastBtn.setAttribute('data-zfa-value', state.contrast);
    }
    if (spacingBtn) {
      spacingBtn.textContent = SPACING_LABELS[state.spacing];
      spacingBtn.setAttribute('data-zfa-value', state.spacing);
    }
    if (imagesBtn) {
      imagesBtn.setAttribute('aria-checked', String(!!state.hideImages));
    }
    if (fontValueEl) {
      fontValueEl.textContent = Math.round((FONT_STEPS[state.fontStep] || 1) * 100) + '%';
    }
    if (fontDec) fontDec.disabled = state.fontStep <= 0;
    if (fontInc) fontInc.disabled = state.fontStep >= FONT_STEPS.length - 1;

    profileButtons.forEach(function (btn) {
      var key = btn.getAttribute('data-zfa-profile');
      btn.setAttribute('aria-pressed', String(!!state.profiles[key]));
    });

    if (readAloudWrap) {
      readAloudWrap.hidden = !state.profiles.blind;
      if (!state.profiles.blind) stopReading();
    }
  }

  toggle.addEventListener('click', function () {
    var open = panel.hasAttribute('hidden');
    if (open) {
      panel.removeAttribute('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      panel.setAttribute('hidden', '');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  closeBtn.addEventListener('click', function () {
    panel.setAttribute('hidden', '');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.focus();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hasAttribute('hidden')) {
      panel.setAttribute('hidden', '');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });
  document.addEventListener('click', function (e) {
    if (panel.hasAttribute('hidden')) return;
    if (root.contains(e.target)) return;
    panel.setAttribute('hidden', '');
    toggle.setAttribute('aria-expanded', 'false');
  });

  profileButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-zfa-profile');
      state.profiles[key] = !state.profiles[key];
      apply();
      persist();
    });
  });

  if (contrastBtn) {
    contrastBtn.addEventListener('click', function () {
      var i = (CONTRAST_ORDER.indexOf(state.contrast) + 1) % CONTRAST_ORDER.length;
      state.contrast = CONTRAST_ORDER[i];
      apply();
      persist();
    });
  }
  if (spacingBtn) {
    spacingBtn.addEventListener('click', function () {
      var i = (SPACING_ORDER.indexOf(state.spacing) + 1) % SPACING_ORDER.length;
      state.spacing = SPACING_ORDER[i];
      apply();
      persist();
    });
  }
  if (imagesBtn) {
    imagesBtn.addEventListener('click', function () {
      state.hideImages = !state.hideImages;
      apply();
      persist();
    });
  }
  if (fontDec) {
    fontDec.addEventListener('click', function () {
      state.fontStep = Math.max(0, state.fontStep - 1);
      apply();
      persist();
    });
  }
  if (fontInc) {
    fontInc.addEventListener('click', function () {
      state.fontStep = Math.min(FONT_STEPS.length - 1, state.fontStep + 1);
      apply();
      persist();
    });
  }
  resetBtn.addEventListener('click', function () {
    state = JSON.parse(JSON.stringify(defaults));
    apply();
    persist();
  });

  // ---- Read-aloud (Blind profile), Web Speech API ----
  var synth = window.speechSynthesis;
  var utterance = null;
  var readToggle = document.getElementById('zfw-a11y-read-toggle');
  var readToggleLabel = document.getElementById('zfw-a11y-read-toggle-label');
  var readStop = document.getElementById('zfw-a11y-read-stop');
  var LABEL_PLAY = root.dataset.zfwA11yReadPlay;
  var LABEL_PAUSE = root.dataset.zfwA11yReadPause;
  var LABEL_RESUME = root.dataset.zfwA11yReadResume;
  var LABEL_UNSUPPORTED = root.dataset.zfwA11yUnsupported;

  function stopReading() {
    if (synth) synth.cancel();
    utterance = null;
    if (readToggleLabel) readToggleLabel.textContent = LABEL_PLAY;
  }

  if (readToggle) {
    if (!synth) {
      readToggle.disabled = true;
      if (readToggleLabel) readToggleLabel.textContent = LABEL_UNSUPPORTED;
    } else {
      readToggle.addEventListener('click', function () {
        if (synth.speaking && !synth.paused) {
          synth.pause();
          if (readToggleLabel) readToggleLabel.textContent = LABEL_RESUME;
          return;
        }
        if (synth.paused) {
          synth.resume();
          if (readToggleLabel) readToggleLabel.textContent = LABEL_PAUSE;
          return;
        }
        var target = document.querySelector(READ_SELECTOR) || document.body;
        var text = (target.innerText || target.textContent || '').trim();
        if (!text) return;
        utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = html.lang || root.getAttribute('data-zfw-a11y-lang') || undefined;
        utterance.onend = function () { if (readToggleLabel) readToggleLabel.textContent = LABEL_PLAY; };
        synth.cancel();
        synth.speak(utterance);
        if (readToggleLabel) readToggleLabel.textContent = LABEL_PAUSE;
      });
    }
  }
  if (readStop) {
    readStop.addEventListener('click', stopReading);
  }
  window.addEventListener('pagehide', function () { if (synth) synth.cancel(); });

  apply();
})();
