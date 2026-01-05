<div id="collapsePhotoCopy" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-info mb-0">
          <i class="fas fa-copy me-2"></i>Photocopies
        </h5>
      </div>

      <!-- Photocopy Actions -->
      <h6 class="text-info mb-3 mt-2">
        <i class="fas fa-print me-1"></i> Photocopy
      </h6>

      <div class="row g-3 mb-4">
        <!-- Enter Photocopy - Actuals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-copy text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Enter Photocopy - Actuals</h6>
              </div>
              <p class="small text-muted">Record monthly photocopy usage and costs.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="photocopy-entry.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Photocopy Budget VS Actuals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Photocopy Budget VS Actuals</h6>
              </div>
              <p class="small text-muted">Compare month-wise actuals against planned budget.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="photocopy-budget-report.php">
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
