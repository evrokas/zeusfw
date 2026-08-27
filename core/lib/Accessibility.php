<?php

/*
 * Framework-level accessibility widget: a floating toggle button + panel
 * offering disability profiles (Blind, Color Blindness, Dyslexia, Epilepsy
 * Safe) and individual page options (Contrast, Font Size, Letter Spacing,
 * Hide Images). Added for erweb, at direct request - see erweb's own
 * CLAUDE.md entry for the app-level wiring.
 *
 * Framework-wide, not app-specific: ZeusFW core has never shipped a shared
 * CSS/JS asset directory (confirmed by exhaustive search before writing
 * this - every app's web/css and web/js has always been entirely local to
 * that app), so this class follows the one precedent that already exists
 * for a self-contained, drop-in UI surface with no app-side asset
 * dependency: ErrorHandlers.php's crash pages, which hand-assemble their
 * own <style>. accessibilityClass::render() does the same thing at a
 * larger scale - one call returns a single self-contained HTML string
 * (inline <style>, inline <script>, no external file, no dependency on
 * the host app's own CSS tokens/variables) that a caller echoes directly
 * into its page, typically once, near the end of <body> (see erweb's
 * main.zetem for the exact call site).
 *
 * Every effect this widget applies is done by toggling classes/CSS custom
 * properties on <html> and reading them back from generic selectors - it
 * has no knowledge of, and makes no assumption about, the calling app's
 * own markup or stylesheet, which is what makes it safe to drop into any
 * ZeusFW app unmodified.
 *
 * State (which profile/options are active) persists in the visitor's own
 * browser via localStorage, keyed by self::STORAGE_KEY - never sent to the
 * server, never shared across visitors, exactly like DocArc's own
 * browser-storage conventions elsewhere in this ecosystem.
 *
 * Color-blindness support is deliberately a single "reduce reliance on
 * color" mode (desaturate + a contrast boost), not three separate
 * per-deficiency "corrective" filters. A true daltonization correction
 * (as opposed to a *simulation* of what a color-blind visitor sees, which
 * is the opposite of what's wanted here) is a composed multi-step color
 * transform, and no verified, authoritative single-matrix correction
 * values were available while building this - shipping an unverified
 * per-type "correction" matrix risked doing the opposite of what a
 * colorblind visitor actually needs. Desaturation + contrast is a
 * simpler, safe, well-understood mitigation that helps regardless of
 * deficiency type, at the cost of not being type-specific. Documented
 * here so a future revision with verified matrices knows why this
 * started simpler.
 */

class accessibilityClass {
    const STORAGE_KEY = 'zfw_a11y_state';

    // Bilingual labels. A caller passing a language not listed here falls
    // back to 'en' (see render()) - matches the fallback convention
    // erweb's own erweb_axis_label()/DOCARC_RETRIEVAL_SECRET_LABELS-style
    // lookups already use.
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

    /**
     * Returns one self-contained HTML fragment (SVG filter defs are no
     * longer used - see the class docblock - kept as a possible future
     * extension point, not present in the current output; toggle button +
     * panel + <style> + <script>) to echo directly into a page, typically
     * once per page near the end of <body>.
     *
     * $args:
     *   'lang'     => 'el' | 'en' (default 'en') - which LABELS set to use.
     *   'position' => 'left' | 'right' (default 'left') - which bottom
     *                 corner the toggle button sits in. erweb's own
     *                 back-to-top button already occupies bottom-right
     *                 (see erweb's CLAUDE.md), so its own call site passes
     *                 'left' - a caller with nothing else in that corner
     *                 can leave the default or pass 'right'.
     *   'readAloudSelector' => CSS selector for the element whose text the
     *                 Blind profile's read-aloud control reads (default
     *                 'main, [role=main]' - falls back to <body> in JS if
     *                 nothing matches, see the script below).
     */
    static function render(array $args = []): string {
        $lang = $args['lang'] ?? 'en';
        $labels = self::LABELS[$lang] ?? self::LABELS['en'];
        $position = ($args['position'] ?? 'left') === 'right' ? 'right' : 'left';
        $readSelector = $args['readAloudSelector'] ?? 'main, [role="main"]';

        $L = static fn (string $key) => htmlspecialchars($labels[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sideCss = $position === 'right' ? 'right: 1.25rem;' : 'left: 1.25rem;';
        $panelSideCss = $position === 'right' ? 'right: 1.25rem;' : 'left: 1.25rem;';
        $langAttr = htmlspecialchars($lang, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Every value dropped into the <script> block below goes through
        // json_encode() (never raw interpolation) so a label or selector
        // containing a quote, backslash, or a literal "</script>" sequence
        // can never break out of the JS string it's assigned to.
        $J = static fn ($v) => json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS);
        $stateKeyJs = $J(self::STORAGE_KEY);
        $readSelectorJs = $J($readSelector);
        $contrastNormalJs = $J($labels['contrastNormal']);
        $contrastHighJs = $J($labels['contrastHigh']);
        $contrastInvertJs = $J($labels['contrastInvert']);
        $spacingNormalJs = $J($labels['spacingNormal']);
        $spacingWideJs = $J($labels['spacingWide']);
        $spacingWiderJs = $J($labels['spacingWider']);
        $readAloudPlayJs = $J($labels['readAloudPlay']);
        $readAloudPauseJs = $J($labels['readAloudPause']);
        $readAloudResumeJs = $J($labels['readAloudResume']);
        $unsupportedJs = $J($labels['unsupported']);

        return <<<HTML
<div id="zfw-a11y-root" data-zfw-a11y-lang="{$langAttr}">
<button type="button" id="zfw-a11y-toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="zfw-a11y-panel" aria-label="{$L('toggleLabel')}" title="{$L('toggleLabel')}">
  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.5"></circle><circle cx="12" cy="7.1" r="1.6"></circle><path d="M6.3 9.6c3.8 1.15 7.6 1.15 11.4 0M12 9.6v4.4l-2.3 5.6M12 14l2.3 5.6"></path></svg>
</button>

<div id="zfw-a11y-panel" role="dialog" aria-modal="false" aria-label="{$L('panelTitle')}" hidden>
  <div class="zfa-head">
    <h2>{$L('panelTitle')}</h2>
    <button type="button" id="zfw-a11y-close" aria-label="{$L('closeLabel')}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
    </button>
  </div>

  <div class="zfa-body">
    <section>
      <h3>{$L('profilesHeading')}</h3>
      <div class="zfa-grid">
        <button type="button" class="zfa-profile" data-zfa-profile="blind" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle><path d="M3 3l18 18"></path></svg>
          <span class="zfa-profile__label">{$L('profileBlind')}</span>
        </button>
        <button type="button" class="zfa-profile" data-zfa-profile="colorblind" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="9" r="5.5"></circle><circle cx="15" cy="9" r="5.5"></circle><circle cx="12" cy="15" r="5.5"></circle></svg>
          <span class="zfa-profile__label">{$L('profileColorblind')}</span>
        </button>
        <button type="button" class="zfa-profile" data-zfa-profile="dyslexia" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V5h6a3.5 3.5 0 0 1 0 7H5"></path><path d="M11 12h1a3.5 3.5 0 0 1 0 7H5"></path></svg>
          <span class="zfa-profile__label">{$L('profileDyslexia')}</span>
        </button>
        <button type="button" class="zfa-profile" data-zfa-profile="epilepsy" aria-pressed="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path></svg>
          <span class="zfa-profile__label">{$L('profileEpilepsy')}</span>
        </button>
      </div>
    </section>

    <div id="zfw-a11y-readaloud" class="zfa-readaloud" hidden>
      <button type="button" id="zfw-a11y-read-toggle">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 5V4L8 9H4z"></path><path d="M16.5 8.5a5 5 0 0 1 0 7"></path></svg>
        <span id="zfw-a11y-read-toggle-label">{$L('readAloudPlay')}</span>
      </button>
      <button type="button" id="zfw-a11y-read-stop" aria-label="{$L('readAloudStop')}" title="{$L('readAloudStop')}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="1.5"></rect></svg>
      </button>
    </div>

    <section>
      <h3>{$L('optionsHeading')}</h3>
      <ul class="zfa-options">
        <li>
          <span class="zfa-opt-label">{$L('optionContrast')}</span>
          <button type="button" id="zfw-a11y-contrast" class="zfa-cycle" data-zfa-value="normal">{$L('contrastNormal')}</button>
        </li>
        <li>
          <span class="zfa-opt-label">{$L('optionFontSize')}</span>
          <div class="zfa-stepper">
            <button type="button" id="zfw-a11y-font-dec" aria-label="-">&minus;</button>
            <span id="zfw-a11y-font-value">100%</span>
            <button type="button" id="zfw-a11y-font-inc" aria-label="+">+</button>
          </div>
        </li>
        <li>
          <span class="zfa-opt-label">{$L('optionLetterSpacing')}</span>
          <button type="button" id="zfw-a11y-spacing" class="zfa-cycle" data-zfa-value="normal">{$L('spacingNormal')}</button>
        </li>
        <li>
          <span class="zfa-opt-label">{$L('optionHideImages')}</span>
          <button type="button" id="zfw-a11y-images" class="zfa-switch" role="switch" aria-checked="false">
            <span class="zfa-switch__track"><span class="zfa-switch__thumb"></span></span>
          </button>
        </li>
      </ul>
    </section>

    <button type="button" id="zfw-a11y-reset" class="zfa-reset">{$L('resetLabel')}</button>
  </div>
</div>
</div>

<style>
  #zfw-a11y-root {
    --zfa-bg: #16233a;
    --zfa-bg-raised: #1e304d;
    --zfa-fg: #f4f4f2;
    --zfa-fg-soft: rgba(244,244,242,0.68);
    --zfa-accent: #2f6fed;
    --zfa-border: rgba(244,244,242,0.14);
    --zfa-radius: 10px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 15px;
    line-height: 1.4;
    color-scheme: dark;
  }
  #zfw-a11y-root * { box-sizing: border-box; }

  #zfw-a11y-toggle {
    position: fixed;
    {$sideCss}
    bottom: 1.25rem;
    z-index: 2147483000;
    width: 3.1rem;
    height: 3.1rem;
    border-radius: 50%;
    border: none;
    background: var(--zfa-accent);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2), 0 10px 24px -8px rgba(0,0,0,0.45);
  }
  #zfw-a11y-toggle svg {
    width: 1.7rem;
    height: 1.7rem;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  #zfw-a11y-toggle:focus-visible,
  #zfw-a11y-panel button:focus-visible {
    outline: 3px solid #ffd166;
    outline-offset: 2px;
  }
  #zfw-a11y-toggle:hover { filter: brightness(1.08); }

  #zfw-a11y-panel {
    position: fixed;
    {$panelSideCss}
    bottom: 5rem;
    z-index: 2147483000;
    width: min(21rem, calc(100vw - 2rem));
    max-height: min(34rem, calc(100vh - 7rem));
    overflow-y: auto;
    background: var(--zfa-bg);
    color: var(--zfa-fg);
    border-radius: var(--zfa-radius);
    border: 1px solid var(--zfa-border);
    box-shadow: 0 4px 12px rgba(0,0,0,0.25), 0 20px 48px -16px rgba(0,0,0,0.6);
  }
  #zfw-a11y-panel[hidden] { display: none; }

  .zfa-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.9rem 1rem; border-bottom: 1px solid var(--zfa-border);
    position: sticky; top: 0; background: var(--zfa-bg);
  }
  .zfa-head h2 { margin: 0; font-size: 1.05rem; font-weight: 600; }
  #zfw-a11y-close {
    background: transparent; border: none; color: var(--zfa-fg-soft); cursor: pointer;
    width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;
    border-radius: 6px;
  }
  #zfw-a11y-close:hover { background: rgba(244,244,242,0.08); color: var(--zfa-fg); }
  #zfw-a11y-close svg { width: 1.1rem; height: 1.1rem; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; }

  .zfa-body { padding: 0.9rem 1rem 1.1rem; display: flex; flex-direction: column; gap: 1.1rem; }
  .zfa-body h3 {
    margin: 0 0 0.6rem; font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--zfa-fg-soft); font-weight: 600;
  }

  .zfa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.55rem; }
  .zfa-profile {
    display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
    background: var(--zfa-bg-raised); border: 1px solid var(--zfa-border); border-radius: 8px;
    padding: 0.7rem 0.5rem; color: var(--zfa-fg); cursor: pointer; text-align: center;
  }
  .zfa-profile svg { width: 1.4rem; height: 1.4rem; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
  .zfa-profile__label { font-size: 0.78rem; }
  .zfa-profile:hover { border-color: rgba(244,244,242,0.3); }
  .zfa-profile[aria-pressed="true"] { background: var(--zfa-accent); border-color: var(--zfa-accent); }

  .zfa-readaloud {
    display: flex; gap: 0.5rem; background: var(--zfa-bg-raised); border: 1px solid var(--zfa-border);
    border-radius: 8px; padding: 0.6rem 0.7rem;
  }
  .zfa-readaloud[hidden] { display: none; }
  #zfw-a11y-read-toggle {
    flex: 1; display: flex; align-items: center; gap: 0.5rem; background: transparent; border: none;
    color: var(--zfa-fg); cursor: pointer; font-size: 0.86rem; padding: 0.2rem;
  }
  #zfw-a11y-read-toggle svg { width: 1.1rem; height: 1.1rem; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; flex: none; }
  #zfw-a11y-read-stop {
    background: transparent; border: 1px solid var(--zfa-border); border-radius: 6px; color: var(--zfa-fg-soft);
    cursor: pointer; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;
  }
  #zfw-a11y-read-stop svg { width: 0.9rem; height: 0.9rem; fill: currentColor; stroke: none; }
  #zfw-a11y-read-stop:hover { color: var(--zfa-fg); border-color: rgba(244,244,242,0.3); }

  .zfa-options { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; }
  .zfa-options li { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
  .zfa-opt-label { font-size: 0.88rem; color: var(--zfa-fg); }

  .zfa-cycle {
    background: var(--zfa-bg-raised); border: 1px solid var(--zfa-border); color: var(--zfa-fg);
    border-radius: 6px; padding: 0.35rem 0.75rem; font-size: 0.82rem; cursor: pointer; min-width: 6.5rem;
  }
  .zfa-cycle:hover { border-color: rgba(244,244,242,0.3); }
  .zfa-cycle[data-zfa-value="high"], .zfa-cycle[data-zfa-value="invert"],
  .zfa-cycle[data-zfa-value="wide"], .zfa-cycle[data-zfa-value="wider"] {
    background: var(--zfa-accent); border-color: var(--zfa-accent);
  }

  .zfa-stepper { display: flex; align-items: center; gap: 0.5rem; }
  .zfa-stepper button {
    width: 1.7rem; height: 1.7rem; border-radius: 6px; border: 1px solid var(--zfa-border);
    background: var(--zfa-bg-raised); color: var(--zfa-fg); cursor: pointer; font-size: 1rem; line-height: 1;
  }
  .zfa-stepper button:hover { border-color: rgba(244,244,242,0.3); }
  #zfw-a11y-font-value { font-size: 0.82rem; min-width: 2.6rem; text-align: center; font-variant-numeric: tabular-nums; }

  .zfa-switch { background: transparent; border: none; cursor: pointer; padding: 0; }
  .zfa-switch__track {
    display: block; width: 2.4rem; height: 1.3rem; border-radius: 999px; background: var(--zfa-bg-raised);
    border: 1px solid var(--zfa-border); position: relative; transition: background 0.15s ease;
  }
  .zfa-switch__thumb {
    position: absolute; top: 1px; left: 1px; width: 1.05rem; height: 1.05rem; border-radius: 50%;
    background: var(--zfa-fg-soft); transition: transform 0.15s ease, background 0.15s ease;
  }
  .zfa-switch[aria-checked="true"] .zfa-switch__track { background: var(--zfa-accent); border-color: var(--zfa-accent); }
  .zfa-switch[aria-checked="true"] .zfa-switch__thumb { transform: translateX(1.1rem); background: #fff; }

  .zfa-reset {
    background: transparent; border: 1px solid var(--zfa-border); color: var(--zfa-fg-soft);
    border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; cursor: pointer; text-align: center;
  }
  .zfa-reset:hover { color: var(--zfa-fg); border-color: rgba(244,244,242,0.3); }

  /* ---- Effects applied to the rest of the page via classes/vars on <html> ---- */
  html.zfw-a11y-contrast-high :not(#zfw-a11y-root):not(#zfw-a11y-root *) {
    filter: contrast(1.35) saturate(1.1);
  }
  html.zfw-a11y-contrast-invert {
    filter: invert(1) hue-rotate(180deg);
  }
  html.zfw-a11y-contrast-invert #zfw-a11y-root {
    filter: invert(1) hue-rotate(180deg);
  }
  html.zfw-a11y-colorblind :not(#zfw-a11y-root):not(#zfw-a11y-root *) {
    filter: saturate(0.35) contrast(1.15);
  }
  html {
    font-size: calc(100% * var(--zfa-font-scale, 1));
  }
  html.zfw-a11y-spacing-wide body { letter-spacing: 0.04em; word-spacing: 0.08em; }
  html.zfw-a11y-spacing-wider body { letter-spacing: 0.09em; word-spacing: 0.16em; }
  html.zfw-a11y-hide-images img { visibility: hidden !important; }
  html.zfw-a11y-dyslexia body {
    line-height: 1.85 !important;
    letter-spacing: 0.035em;
    word-spacing: 0.14em;
  }
  html.zfw-a11y-dyslexia p, html.zfw-a11y-dyslexia li {
    text-align: left !important;
    max-width: 68ch;
  }
  html.zfw-a11y-epilepsy *, html.zfw-a11y-epilepsy *::before, html.zfw-a11y-epilepsy *::after {
    animation-play-state: paused !important;
    animation-duration: 0.001ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.001ms !important;
    scroll-behavior: auto !important;
  }
  html.zfw-a11y-blind :focus-visible {
    outline: 3px solid #ffd166 !important;
    outline-offset: 2px !important;
  }
</style>

<script>
(function () {
  var STORAGE_KEY = {$stateKeyJs};
  var READ_SELECTOR = {$readSelectorJs};
  var FONT_STEPS = [1, 1.15, 1.3, 1.45, 1.6];

  var root = document.getElementById('zfw-a11y-root');
  if (!root) return;
  var html = document.documentElement;
  var toggle = document.getElementById('zfw-a11y-toggle');
  var panel = document.getElementById('zfw-a11y-panel');
  var closeBtn = document.getElementById('zfw-a11y-close');
  var resetBtn = document.getElementById('zfw-a11y-reset');

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

  var CONTRAST_LABELS = { normal: {$contrastNormalJs}, high: {$contrastHighJs}, invert: {$contrastInvertJs} };
  var SPACING_LABELS = { normal: {$spacingNormalJs}, wide: {$spacingWideJs}, wider: {$spacingWiderJs} };
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

  function apply() {
    setClass('zfw-a11y-contrast-high', state.contrast === 'high');
    setClass('zfw-a11y-contrast-invert', state.contrast === 'invert');
    setClass('zfw-a11y-colorblind', state.profiles.colorblind);
    setClass('zfw-a11y-dyslexia', state.profiles.dyslexia);
    setClass('zfw-a11y-epilepsy', state.profiles.epilepsy);
    setClass('zfw-a11y-blind', state.profiles.blind);
    setClass('zfw-a11y-hide-images', state.hideImages);
    setClass('zfw-a11y-spacing-wide', state.spacing === 'wide');
    setClass('zfw-a11y-spacing-wider', state.spacing === 'wider');
    html.style.setProperty('--zfa-font-scale', String(FONT_STEPS[state.fontStep] || 1));

    contrastBtn.textContent = CONTRAST_LABELS[state.contrast];
    contrastBtn.setAttribute('data-zfa-value', state.contrast);
    spacingBtn.textContent = SPACING_LABELS[state.spacing];
    spacingBtn.setAttribute('data-zfa-value', state.spacing);
    imagesBtn.setAttribute('aria-checked', String(!!state.hideImages));
    fontValueEl.textContent = Math.round((FONT_STEPS[state.fontStep] || 1) * 100) + '%';
    fontDec.disabled = state.fontStep <= 0;
    fontInc.disabled = state.fontStep >= FONT_STEPS.length - 1;

    profileButtons.forEach(function (btn) {
      var key = btn.getAttribute('data-zfa-profile');
      btn.setAttribute('aria-pressed', String(!!state.profiles[key]));
    });

    readAloudWrap.hidden = !state.profiles.blind;
    if (!state.profiles.blind) stopReading();
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

  contrastBtn.addEventListener('click', function () {
    var i = (CONTRAST_ORDER.indexOf(state.contrast) + 1) % CONTRAST_ORDER.length;
    state.contrast = CONTRAST_ORDER[i];
    apply();
    persist();
  });
  spacingBtn.addEventListener('click', function () {
    var i = (SPACING_ORDER.indexOf(state.spacing) + 1) % SPACING_ORDER.length;
    state.spacing = SPACING_ORDER[i];
    apply();
    persist();
  });
  imagesBtn.addEventListener('click', function () {
    state.hideImages = !state.hideImages;
    apply();
    persist();
  });
  fontDec.addEventListener('click', function () {
    state.fontStep = Math.max(0, state.fontStep - 1);
    apply();
    persist();
  });
  fontInc.addEventListener('click', function () {
    state.fontStep = Math.min(FONT_STEPS.length - 1, state.fontStep + 1);
    apply();
    persist();
  });
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
  var LABEL_PLAY = {$readAloudPlayJs};
  var LABEL_PAUSE = {$readAloudPauseJs};
  var LABEL_RESUME = {$readAloudResumeJs};
  var LABEL_UNSUPPORTED = {$unsupportedJs};

  function stopReading() {
    if (synth) synth.cancel();
    utterance = null;
    if (readToggleLabel) readToggleLabel.textContent = LABEL_PLAY;
  }

  if (readToggle) {
    if (!synth) {
      readToggle.disabled = true;
      readToggleLabel.textContent = LABEL_UNSUPPORTED;
    } else {
      readToggle.addEventListener('click', function () {
        if (synth.speaking && !synth.paused) {
          synth.pause();
          readToggleLabel.textContent = LABEL_RESUME;
          return;
        }
        if (synth.paused) {
          synth.resume();
          readToggleLabel.textContent = LABEL_PAUSE;
          return;
        }
        var target = document.querySelector(READ_SELECTOR) || document.body;
        var text = (target.innerText || target.textContent || '').trim();
        if (!text) return;
        utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = html.lang || root.getAttribute('data-zfw-a11y-lang') || undefined;
        utterance.onend = function () { readToggleLabel.textContent = LABEL_PLAY; };
        synth.cancel();
        synth.speak(utterance);
        readToggleLabel.textContent = LABEL_PAUSE;
      });
    }
  }
  if (readStop) {
    readStop.addEventListener('click', stopReading);
  }
  window.addEventListener('pagehide', function () { if (synth) synth.cancel(); });

  apply();
})();
</script>
HTML;
    }

}   /* end class definition */
