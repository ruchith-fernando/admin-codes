<?php
// load-graphs.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<div id="collapseGraphs" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-info mb-0">
          <i class="fas fa-chart-line me-2"></i>Analytics & Graphs
        </h5>
      </div>

      <!-- Operations Graphs -->
      <h6 class="text-info mb-3 mt-2">
        <i class="fas fa-project-diagram me-1"></i> Operations Graphs
      </h6>

      <div class="row g-3 mb-4">

        <!-- Electricity -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-bolt text-warning me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Electricity</h6>
              </div>
              <p class="small text-muted">Usage & spend trends, month-wise comparisons, variance vs budget.</p>
              <button class="btn btn-outline-warning btn-sm mt-auto load-report" data-page="electricity-graph-report.php">
                <i class="fas fa-chart-area me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Telephone Bills -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-phone-volume text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Telephone Bills</h6>
              </div>
              <p class="small text-muted">Budget vs actuals with Dialog, CDMA, and SLT breakdown.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="telephone-graph-report.php">
                <i class="fas fa-chart-line me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Security -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-shield-alt text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Security</h6>
              </div>
              <p class="small text-muted">Shifts vs amount, month-to-date progress, branch-wise comparison.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="security-graph-report.php">
                <i class="fas fa-chart-bar me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Courier -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-shipping-fast text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Courier</h6>
              </div>
              <p class="small text-muted">Monthly courier costs, vendor mix, weight/zone analysis.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="graph-courier.php">
                <i class="fas fa-chart-line me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Transport -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-bus-alt text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Transport</h6>
              </div>
              <p class="small text-muted">Staff transport cost trends, route utilization & recovery.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="staff-transport-graph-report.php">
                <i class="fas fa-chart-pie me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Water -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-tint text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Water</h6>
              </div>
              <p class="small text-muted">Consumption vs cost trends and anomalies by month.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="graph-water.php">
                <i class="fas fa-chart-area me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Vehicle Repairs -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-tools text-danger me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Vehicle Repairs</h6>
              </div>
              <p class="small text-muted">Repair spend by type (Battery/Tire/AC/Other) & vehicle-wise trends.</p>
              <button class="btn btn-outline-danger btn-sm mt-auto load-report" data-page="vehicle-graph-report.php">
                <i class="fas fa-chart-bar me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Tea Service -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-mug-hot text-warning me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Tea Service</h6>
              </div>
              <p class="small text-muted">Monthly tea service budget vs actual totals.</p>
              <button class="btn btn-outline-warning btn-sm mt-auto load-report" data-page="tea-service-graph-report.php">
                <i class="fas fa-chart-line me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Photocopy -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-copy text-dark me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Photocopy</h6>
              </div>
              <p class="small text-muted">Monthly photocopy budget vs actual totals.</p>
              <button class="btn btn-outline-dark btn-sm mt-auto load-report" data-page="photocopy-graph-report.php">
                <i class="fas fa-chart-line me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

        <!-- Postage & Stamps -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-envelope text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Postage & Stamps</h6>
              </div>
              <p class="small text-muted">Budget vs actual spend on postage and stamps.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="postage-graph-report.php">
                <i class="fas fa-chart-area me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<style>
/* Simple skeleton to avoid flicker while loading modules */
.module-skeleton {
  padding: 2rem 1rem;
}
.skel-line {
  height: 14px;
  width: 100%;
  margin-bottom: 10px;
  border-radius: 8px;
  background: linear-gradient(90deg, #eee 25%, #f7f7f7 37%, #eee 63%);
  background-size: 400% 100%;
  animation: skel 1.2s ease-in-out infinite;
}
.skel-line.w-25 { width: 25%; }
.skel-line.w-50 { width: 50%; }
.skel-line.w-75 { width: 75%; }
.skel-card {
  height: 240px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f5f5f5 25%, #fafafa 37%, #f5f5f5 63%);
  background-size: 400% 100%;
  animation: skel 1.2s ease-in-out infinite;
}
@keyframes skel { 0%{background-position:100% 50%} 100%{background-position:0 50%} }
</style>

<script>
(function($){
  // Bind once to prevent duplicate handlers on re-inserts
  if (!window.__graphsBound) {
    window.__graphsBound = true;

    let currentXHR = null;

    function stripInlineScripts(html){
      try { return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ''); } catch(e){ return html; }
    }

    function showSkeleton(){
      $('#contentArea').html(`
        <div class="module-skeleton">
          <div class="row g-3">
            <div class="col-12"><div class="skel-line w-25"></div></div>
            <div class="col-md-6"><div class="skel-card"></div></div>
            <div class="col-md-6"><div class="skel-card"></div></div>
            <div class="col-md-6"><div class="skel-card"></div></div>
            <div class="col-md-6"><div class="skel-card"></div></div>
          </div>
        </div>
      `);
    }

    async function loadModule(url){
      // Abort any in-flight request
      if (currentXHR && currentXHR.readyState !== 4) {
        try { currentXHR.abort(); } catch(e){}
      }

      showSkeleton();

      // Add a light cache-buster per request (no global ajaxSetup)
      const busted = url + (url.indexOf('?') > -1 ? '&' : '?') + '_ts=' + Date.now();

      currentXHR = $.ajax({
        url: busted,
        method: 'GET',
        cache: false,
        dataType: 'html'
      });

      currentXHR.done(function(html){
        // Inject once, after fully received
        $('#contentArea').html(stripInlineScripts(html));

        // Optional hooks the subpage might expose
        if (typeof window.runDashboardChart === 'function') {
          try { window.runDashboardChart(); } catch(e){ console.error(e); }
        }
        if (typeof window.initPage === 'function') {
          try { window.initPage(); } catch(e){ console.error(e); }
        }
      });

      currentXHR.fail(function(xhr){
        $('#contentArea').html(
          '<div class="alert alert-danger mt-3">Failed to load (' + xhr.status + ').</div>'
        );
      });
    }

    // Single, clean delegated handler (no flicker, no duplicates)
    $(document).on('click.graphs', '.load-report', function(){
      const page = $(this).data('page');
      if (!page) return;
      loadModule(page);
    });
  }
})(jQuery);
</script>
