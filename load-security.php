<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<div id="collapseSecurity" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-danger mb-0">
          <i class="fas fa-shield-alt me-2"></i>Security
        </h5>
      </div>

      <!-- Security Actions -->
      <h6 class="text-danger mb-3 mt-2">
        <i class="fas fa-tools me-1"></i> Security
      </h6>

      <div class="row g-3 mb-4">
        <!-- View Security Budget -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-list text-danger me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">View Security Budget</h6>
              </div>
              <p class="small text-muted">View security budgets for the selected period.</p>
              <button class="btn btn-outline-danger btn-sm mt-auto load-report" data-page="security-budget-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Upload Actuals - Monthly -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-upload text-danger me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload Actuals - Monthly</h6>
              </div>
              <p class="small text-muted">Upload/enter monthly actual security costs and shifts.</p>
              <button class="btn btn-outline-danger btn-sm mt-auto load-report" data-page="upload-actual-security.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Security Monthly Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-calendar-check text-danger me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Security Monthly Report</h6>
              </div>
              <p class="small text-muted">Enter/review monthly data, provisions, and amounts.</p>
              <button class="btn btn-outline-danger btn-sm mt-auto load-report" data-page="security-monthly-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Security Budget VS Actuals
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-danger me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Security Budget VS Actuals</h6>
              </div>
              <p class="small text-muted">Compare month-wise security actuals against budget.</p>
              <button class="btn btn-outline-danger btn-sm mt-auto load-report" data-page="security-cost-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div> -->
      </div>

    </div>
  </div>
</div>
<script>
(function ($) {
  let currentXHR = null;

  // Best-effort cache clear (safe if unsupported)
  async function clearAjaxCaches(){
    try { if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map(k => caches.delete(k)));
    }} catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  // Call this to load any PHP fragment fresh into #contentArea
  window.loadNoCache = async function(url, target = '#contentArea'){
    // abort previous
    if (currentXHR && currentXHR.readyState !== 4) { try { currentXHR.abort(); } catch(e){} }

    await clearAjaxCaches();

    const $t = $(target).html(
      '<div class="text-center p-4"><div class="spinner-border text-primary"></div> Loading…</div>'
    );

    const busted = url + (url.indexOf('?') > -1 ? '&' : '?') + '_ts=' + Date.now();

    currentXHR = $.ajax({
      url: busted,
      method: 'GET',
      cache: false,
      headers: {
        'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma': 'no-cache',
        'Expires': '0'
      }
    })
    .done(function (html) {
      $t.html(html);
    })
    .fail(function () {
      $t.html('<div class="alert alert-danger mt-3">Failed to load.</div>');
    });
  };

  // Make all jQuery GETs non-cached by default (extra safety)
  $.ajaxSetup({ cache: false });
})(jQuery);
</script>
<script>
// Load report dynamically into #contentArea
$(document).on('click', '.load-report', function () {
  var page = $(this).data('page');
  $('#contentArea').load(page);
});
</script>
