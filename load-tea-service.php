<div id="collapseTea" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-warning mb-0">
          <i class="fas fa-mug-hot me-2"></i>Tea Service - Head Office
        </h5>
      </div>

      <!-- Tea Service Actions -->
      <h6 class="text-success mb-3 mt-2">
        <i class="fas fa-concierge-bell me-1"></i> Tea Service
      </h6>

      <div class="row g-3 mb-4">
        <!-- Enter Tea Service -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-coffee text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Enter Tea Service</h6>
              </div>
              <p class="small text-muted">Add and manage monthly Head Office tea service entries.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="tea-service.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Tea Service Budget VS Actuals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-success me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Tea Service Budget VS Actuals</h6>
              </div>
              <p class="small text-muted">Compare month-wise tea service actuals against budget.</p>
              <button class="btn btn-outline-success btn-sm mt-auto load-report" data-page="tea-budget-vs-actual.php">
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
