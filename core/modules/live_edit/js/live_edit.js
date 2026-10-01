/*
 * live_edit content-source overlay -- Step 1 of a planned live-editing
 * feature. Click-to-copy only; no editing capability exists yet.
 */
(function () {
  var root = document.getElementById('zfw-live-root');
  if (!root) return;

  var badge = document.getElementById('zfw-live-badge');
  var path = root.dataset.zfwLivePath || '';
  var copiedLabel = root.dataset.zfwLiveCopiedLabel || 'Copied';
  if (!badge) return;

  var labelEl = badge.querySelector('.zfl-path');
  var originalLabel = labelEl ? labelEl.textContent : '';
  var resetTimer = null;

  badge.addEventListener('click', function () {
    if (!path) return;

    var showCopied = function () {
      if (resetTimer) clearTimeout(resetTimer);
      badge.classList.add('zfl-copied');
      if (labelEl) labelEl.textContent = copiedLabel;
      resetTimer = setTimeout(function () {
        badge.classList.remove('zfl-copied');
        if (labelEl) labelEl.textContent = originalLabel;
      }, 1200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(path).then(showCopied, function () {});
    }
  });
})();
