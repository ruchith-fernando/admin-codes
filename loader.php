<!-- loader.php -->
<style>
  #globalLoader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.3s ease, visibility 0.3s ease;
  }
  .loader-hidden {
    opacity: 0;
    visibility: hidden;
  }
  .loader-inner.line-scale > div {
    height: 72px;
    width: 10.8px;
    margin: 3.6px;
    display: inline-block;
    animation: scaleStretchDelay 1.2s infinite ease-in-out;
  }
  .loader-inner.line-scale > div:nth-child(odd) { background-color: #0070C0; }
  .loader-inner.line-scale > div:nth-child(even) { background-color: #E60028; }
  .loader-inner.line-scale > div:nth-child(1) { animation-delay: -1.2s; }
  .loader-inner.line-scale > div:nth-child(2) { animation-delay: -1.1s; }
  .loader-inner.line-scale > div:nth-child(3) { animation-delay: -1.0s; }
  .loader-inner.line-scale > div:nth-child(4) { animation-delay: -0.9s; }
  .loader-inner.line-scale > div:nth-child(5) { animation-delay: -0.8s; }
  @keyframes scaleStretchDelay {
    0%, 40%, 100% { transform: scaleY(0.4); }
    20% { transform: scaleY(1.0); }
  }
</style>

<div id="globalLoader" class="loader-hidden" aria-hidden="true">
  <div class="loader-inner line-scale" role="status" aria-live="polite" aria-label="Loading">
    <div></div><div></div><div></div><div></div><div></div>
  </div>
</div>

<script>
(function (w) {
  'use strict';

  // Keep this flag global for pages that toggle it
  w.skipGlobalLoader = !!w.skipGlobalLoader;

  function showLoader() {
    var el = document.getElementById('globalLoader');
    if (!el) return;
    el.classList.remove('loader-hidden');
    el.setAttribute('aria-hidden', 'false');
  }
  function hideLoader() {
    var el = document.getElementById('globalLoader');
    if (!el) return;
    el.classList.add('loader-hidden');
    el.setAttribute('aria-hidden', 'true');
  }

  // Expose safely
  w.showLoader = showLoader;
  w.hideLoader = hideLoader;

  // Page navigation overlay (does NOT interfere with your AJAX; you control overlay via show/hide)
  window.addEventListener('beforeunload', function () {
    if (!w.skipGlobalLoader) showLoader();
  });
  window.addEventListener('load', hideLoader);

  // SAFE fallback for pages that relied on a global exportData defined here.
  // If a page (like mobile-bill-report.php) already defines window.exportData, we DO NOT override it.
  if (typeof w.exportData !== 'function') {
    w.exportData = function (type) {
      // Legacy finance download logic kept as-is; adjust only if needed by those pages.
      var update_date_el = document.getElementById('update_date');
      var update_date = update_date_el ? update_date_el.value : '';
      if (!update_date) {
        if (window.jQuery && typeof jQuery.fn.modal === 'function') {
          // expects a #selectMonthModal existing on those pages
          jQuery('#selectMonthModal').modal('show');
        } else {
          alert('Please select a month.');
        }
        return;
      }
      // Prevent navigation overlay for downloads
      w.skipGlobalLoader = true;
      var downloadUrl = 'export-mobile-bill-finance-excel.php?update_date=' + encodeURIComponent(update_date);
      window.open(downloadUrl, '_blank');
      setTimeout(function () { w.skipGlobalLoader = false; }, 2000);
    };
  }

})(window);
</script>
