<div id="collapsePostageStamps" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-success mb-0">
          <i class="fas fa-stamp me-2"></i>Postage & Stamps
        </h5>
      </div>

      <!-- Postage & Stamps Actions -->
      <h6 class="text-success mb-3 mt-2">
        <i class="fas fa-envelope-open-text me-1"></i> Postage & Stamps
      </h6>

      <div class="row g-3 mb-4">
        <!-- Topup Postage & Stamps -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-money-check text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Topup Postage & Stamps</h6>
              </div>
              <p class="small text-muted">Record cheque-based top-ups for postage & stamps.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="postage-cheque-entry.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Postage & Stamps - Actuals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-envelope text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Postage & Stamps - Actuals</h6>
              </div>
              <p class="small text-muted">Enter and manage monthly actual postage & stamp usage.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="actual-postage.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Postage & Stamps - Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-table text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Postage & Stamps - Report</h6>
              </div>
              <p class="small text-muted">Filter and view detailed monthly reports.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="postage-report-filter.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Budget VS Actuals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Postage & Stamps - Budget VS Actuals</h6>
              </div>
              <p class="small text-muted">Compare monthly actuals against budget with variance.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="postage-budget-vs-actual.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
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
