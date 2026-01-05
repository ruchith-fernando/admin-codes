<div id="collapseVehicle" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-dark mb-0">
          <i class="fas fa-car me-2"></i>Vehicle Service & Maintenance
        </h5>
      </div>

      <!-- Vehicle Actions -->
      <h6 class="text-dark mb-3 mt-2">
        <i class="fas fa-tools me-1"></i> Vehicle
      </h6>

      <div class="row g-3 mb-4">

        <!-- Create Vehicle Information -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-car-side text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Create Vehicle Information</h6>
              </div>
              <p class="small text-muted">Register new vehicles with ownership details.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-information.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Verify and Approve -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-check text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Verify and Approve</h6>
              </div>
              <p class="small text-muted">Dual-control panel for reviewing and approving entries.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-approval-panel.php">
                <i class="fas fa-file-alt me-1"></i> Open Panel
              </button>
            </div>
          </div>
        </div>

        <!-- View Vehicle Information -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-list-alt text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">View Vehicle Information</h6>
              </div>
              <p class="small text-muted">Browse and search all registered vehicle records.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="view-vehicle-information.php">
                <i class="fas fa-file-alt me-1"></i> Open View
              </button>
            </div>
          </div>
        </div>

        <!-- Add Vehicle Maintenance Details -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-wrench text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Add Vehicle Maintenance Details</h6>
              </div>
              <p class="small text-muted">Log service, repairs, tires, battery and other costs.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-maintenance.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>

        <!-- Pending Vehicle Maintenance Approvals -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-tasks text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Vehicle Maintenance Approvals</h6>
              </div>
              <p class="small text-muted">Review and action maintenance requests.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-approvals-pro.php">
                <i class="fas fa-file-alt me-1"></i> Open Queue
              </button>
            </div>
          </div>
        </div>

        <!-- Vehicle History -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-history text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Vehicle History</h6>
              </div>
              <p class="small text-muted">Timeline of services, licenses, and maintenance.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-history.php">
                <i class="fas fa-file-alt me-1"></i> Open History
              </button>
            </div>
          </div>
        </div>

        <!-- Budget VS Actual -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-chart-line text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Budget VS Actual</h6>
              </div>
              <p class="small text-muted">Compare maintenance spend vs budget by month.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-budget-vs-actual.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Vehicle Maintenance Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100 border-0 rounded-3">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-invoice text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0 fw-bold">Vehicle Maintenance Report</h6>
              </div>
              <p class="small text-muted">Detailed listing of all maintenance records.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="vehicle-maintenance-report.php">
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
$(document).on('click', '.load-report', function () {
  var page = $(this).data('page');
  // Prevent infinite recursion when loading vehicle-approvals
  $('#contentArea').html('<div id="pageWrapper"></div>');
  $('#pageWrapper').load(page, function(){
    if (window.initPage) initPage();
  });
});
</script>
