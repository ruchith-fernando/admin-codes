// validation-dashboard-selection.js
// Idempotent, pure helpers (no global event bindings). Safe to include once globally.

(function () {
  'use strict';
  if (window.__vdsel_init) return; // prevent re-initialization
  window.__vdsel_init = true;

  let __vdsel_alertTimer = null;

  // Find the best place to show alerts (the main card inside #contentArea)
  function getCardRoot() {
    const $ca = $('#contentArea');
    if ($ca.length) {
      const $card = $ca.find('.card').first();
      if ($card.length) return $card;
      return $ca;
    }
    const $fallback = $('.card').first();
    return $fallback.length ? $fallback : $('body');
  }

  function clearExistingAlert() {
    if (__vdsel_alertTimer) {
      clearTimeout(__vdsel_alertTimer);
      __vdsel_alertTimer = null;
    }
    // Close any existing alert boxes (both scoped and global just in case)
    const $alerts = $('#contentArea #alert-box, #alert-box');
    if ($alerts.length) {
      try { $alerts.alert('close'); } catch (e) { $alerts.remove(); }
    }
  }

  function autoCloseAlert($el, timeoutMs) {
    if (timeoutMs > 0) {
      __vdsel_alertTimer = window.setTimeout(() => {
        try { $el.alert('close'); } catch (e) { $el.remove(); }
        __vdsel_alertTimer = null;
      }, timeoutMs);
    }
  }

  /**
   * Validate that at least one month checkbox is selected inside #contentArea.
   * Shows a Bootstrap alert on failure. Returns true/false.
   */
  function validateMonthSelection() {
    clearExistingAlert();

    const $checked = $('#contentArea .month-checkbox:checked');
    if ($checked.length === 0) {
      const $root = getCardRoot();
      const html = `
        <div id="alert-box" class="alert alert-danger alert-dismissible fade show" role="alert">
          ❌ Please select at least one month before updating.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
      const $el = $(html);
      $root.prepend($el);
      try { new bootstrap.Alert($el[0]); } catch (e) {}
      autoCloseAlert($el, 5000);
      return false;
    }
    return true;
  }

  /**
   * Show a Bootstrap alert at the top of the current card.
   * @param {string} message - The message to display.
   * @param {'primary'|'secondary'|'success'|'danger'|'warning'|'info'|'light'|'dark'} [type='danger']
   * @param {number} [timeoutMs=5000] - Auto close after ms. Use 0 to disable.
   */
  function showBootstrapAlert(message, type = 'danger', timeoutMs = 5000) {
    clearExistingAlert();

    const $root = getCardRoot();
    const html = `
      <div id="alert-box" class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
    const $el = $(html);
    $root.prepend($el);
    try { new bootstrap.Alert($el[0]); } catch (e) {}
    autoCloseAlert($el, timeoutMs);
  }

  // Expose as globals for page scripts to call
  window.validateMonthSelection = validateMonthSelection;
  window.showBootstrapAlert = showBootstrapAlert;
})();
