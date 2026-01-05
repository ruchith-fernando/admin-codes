<div id="collapseStaffTransport" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-secondary mb-0">
          <i class="fas fa-bus me-2"></i>Staff Transport
        </h5>
      </div>

      <!-- Staff Transport Actions -->
      <h6 class="text-secondary mb-3 mt-2">
        <i class="fas fa-route me-1"></i> Staff Transport
      </h6>

      <div class="row g-3 mb-4">
        <!-- Staff Transport - Kangaroo -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-bus text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Staff Transport - Kangaroo</h6>
              </div>
              <p class="small text-muted">Enter monthly Kangaroo transport records and costs.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="staff-transport-entry.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Staff Transport - PickMe -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-taxi text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Staff Transport - PickMe</h6>
              </div>
              <p class="small text-muted">Upload and process monthly PickMe statements.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="upload-pickme.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- Staff Transport - Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-table text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Staff Transport - Report</h6>
              </div>
              <p class="small text-muted">View detailed monthly staff transport report.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="staff-transport-report.php">
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
                <i class="fas fa-chart-line text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Budget VS Actuals</h6>
              </div>
              <p class="small text-muted">Compare monthly actuals vs budget with variance.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="staff-transport-budget-vs-actual-report.php">
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
