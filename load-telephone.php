<div id="collapseTelephone" class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-info mb-0">
          <i class="fas fa-phone me-2"></i>Telephone Bills
        </h5>
      </div>

      <!-- Dialog Bills -->
      <h6 class="text-info mb-3 mt-2">
        <i class="fas fa-mobile-alt me-1"></i> Dialog / CDMA Bills
      </h6>

      <div class="row g-3 mb-4">
        <!-- View Staff Information -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-users text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">View Staff Information</h6>
              </div>
              <p class="small text-muted">Browse staff details linked to Dialog numbers.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="view-employee-details.php">
                <i class="fas fa-file-alt me-1"></i> Open View
              </button>
            </div>
          </div>
        </div>

        <!-- Upload Monthly Dialog Bill -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-upload text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload Monthly Dialog Bill</h6>
              </div>
              <p class="small text-muted">Import the monthly bill PDF/CSV for processing.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="upload-bill.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- Dialog Bill Report - HR -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-list text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Dialog Bill Report - HR</h6>
              </div>
              <p class="small text-muted">HR view of allocations, usage, and variances.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="mobile-bill-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Dialog Bill Report - Finance -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-coins text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Dialog Bill Report - Finance</h6>
              </div>
              <p class="small text-muted">Finance-focused report with costs and recoveries.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="mobile-bill-report-finance.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- Upload Staff Info - Active -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-plus text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload Staff Info - Active</h6>
              </div>
              <p class="small text-muted">Bulk upload active staff allocations and details.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="upload-employee-data.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- Upload Staff Info - Resigned -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-times text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload Staff Info - Resigned</h6>
              </div>
              <p class="small text-muted">Import details for resigned/inactive staff.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="upload-employee-resigned-data.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- Update Company Contribution - Individual -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-hand-holding-usd text-info me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Update Company Contribution - Individual</h6>
              </div>
              <p class="small text-muted">Adjust company contribution for a single employee.</p>
              <button class="btn btn-outline-info btn-sm mt-auto load-report" data-page="update-contribution.php">
                <i class="fas fa-file-alt me-1"></i> Open Form
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- SLT Fixed Line -->
      <h6 class="text-primary mb-3 mt-2">
        <i class="fas fa-phone-square-alt me-1"></i> SLT Fixed Line
      </h6>

      <div class="row g-3 mb-4">
        <!-- Convert HTM to PDF -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-code text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Convert HTM to PDF</h6>
              </div>
              <p class="small text-muted">Convert SLT HTM bill pages into a single PDF.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="html-to-single-page-pdf.php">
                <i class="fas fa-file-alt me-1"></i> Open Tool
              </button>
            </div>
          </div>
        </div>

        <!-- Upload SLT Monthly Bill -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-upload text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload SLT Monthly Bill</h6>
              </div>
              <p class="small text-muted">Upload the monthly SLT fixed-line bill for processing.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="slt-upload-form.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- SLT Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-check text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">SLT Report</h6>
              </div>
              <p class="small text-muted">Detailed SLT report with per-connection allocations.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="slt-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- SLT Report - Group by Month -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-layer-group text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">SLT Report - Group by Branch</h6>
              </div>
              <p class="small text-muted">Summary view grouped by your SLT configuration.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="slt-branch-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Summary
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- CDMA Bills -->
      <h6 class="text-secondary mb-3 mt-2">
        <i class="fas fa-broadcast-tower me-1"></i> CDMA Bills
      </h6>

      <div class="row g-3 mb-4">
        <!-- Upload Monthly Dialog Bill (CDMA) -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-file-upload text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Upload CDMA Monthly Dialog Bill</h6>
              </div>
              <p class="small text-muted">Upload monthly CDMA bill files for processing.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="upload-cdma-bill.php">
                <i class="fas fa-file-alt me-1"></i> Open Upload
              </button>
            </div>
          </div>
        </div>

        <!-- CDMA - Report -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-clipboard-list text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">CDMA - Report</h6>
              </div>
              <p class="small text-muted">Detailed CDMA report per connection with allocations.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="cdma-report.php">
                <i class="fas fa-file-alt me-1"></i> Open Report
              </button>
            </div>
          </div>
        </div>

        <!-- CDMA Report - Group by Department -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-layer-group text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">CDMA Report - Group by Department</h6>
              </div>
              <p class="small text-muted">Summary totals grouped by “Allocated To”.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="cdma-report-group-by-suffix.php">
                <i class="fas fa-file-alt me-1"></i> Open Summary
              </button>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-layer-group text-secondary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">CDMA Report - Group by Contract</h6>
              </div>
              <p class="small text-muted">Summary totals grouped by “Allocated To”.</p>
              <button class="btn btn-outline-secondary btn-sm mt-auto load-report" data-page="cdma-report-contract.php">
                <i class="fas fa-file-alt me-1"></i> Open Summary
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
