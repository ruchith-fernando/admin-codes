<div id="collapseElectricity" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-primary mb-0">
          <i class="fas fa-lightbulb me-2"></i>Electricity
        </h5>
      </div>

      <!-- Electricity Actions -->
      <h6 class="text-primary mb-3 mt-2">
        <i class="fas fa-bolt me-1"></i> Electricity
      </h6>

      <div class="row g-3 mb-4">
        <!-- Initial Electricity Bill Entry -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-invoice text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Initial Electricity Bill Entry</h6>
              </div>
              <p class="small text-muted">Add new month-wise electricity bill entries.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-initial-entry.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Cheque Details -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-money-check text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Cheque Details</h6>
              </div>
              <p class="small text-muted">Record cheque information linked to bills.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-cheque-entry.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Full Report - Monthly -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-table text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Full Report - Monthly</h6>
              </div>
              <p class="small text-muted">View detailed monthly electricity bill report.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-full-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Monthly Budget Vs Actual -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Monthly Budget Vs Actual</h6>
              </div>
              <p class="small text-muted">Compare actuals against budget for each month.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-budget-vs-actual.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Overview
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-list text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Overview</h6>
              </div>
              <p class="small text-muted">High-level summary of electricity spending.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-overview.php">
                <i class="fas fa-file-alt me-1"></i> Open Overview
              </button>
            </div>
          </div>
        </div> -->

        <!-- Graph -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-bar text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Graph</h6>
              </div>
              <p class="small text-muted">Visual trends of usage and cost over time.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="electricity-graph-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Graph
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<script>
// Load report dynamically into #contentArea
$(document).on('click', '.load-report', function () {
  var page = $(this).data('page');
  $('#contentArea').load(page);
});
</script>
