<?php
// water-monthly-report.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-detect FY (April → March)
$current_year = date('Y');
$current_month = date('n');
$fy_start_year = ($current_month < 4) ? $current_year - 1 : $current_year;
$fy_end_year = $fy_start_year + 1;

// Create April→March month list
$start = strtotime("{$fy_start_year}-04-01");
$end   = strtotime("{$fy_end_year}-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = date("F Y", $start);
    $start = strtotime("+1 month", $start);
}

// Load months with approved data
$data_months = [];
$q = mysqli_query($conn, "
    SELECT DISTINCT month_applicable 
    FROM tbl_admin_actual_water 
    WHERE total_amount IS NOT NULL 
      AND TRIM(total_amount) <> '' 
      AND total_amount <> '0'
      AND approval_status = 'approved'
");
while ($r = mysqli_fetch_assoc($q)) {
    $data_months[] = $r['month_applicable'];
}
?>
<style>
.water-entry-wrapper { width: 100%; overflow-x: auto; }
.water-entry-table { width: 100%; min-width: 1400px; table-layout: auto; }
.water-entry-table th { white-space: nowrap; vertical-align: middle; }
.water-entry-table td { white-space: nowrap; vertical-align: middle; }
#water_total_row { max-width: 100%; margin-right: 0; }
#water_total_amount { width: 100%; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">
        Water — Monthly Budget vs Actual (<?= $fy_start_year ?>–<?= $fy_end_year ?>)
      </h5>

      <!-- SELECT MONTH - VIEW REPORT -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="water_month_view" class="form-select">
          <option value="">-- Choose a Month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="water_missing_view_branches" class="alert alert-warning d-none"></div>
      <div id="water_csv_download_container" class="d-none mt-3 mb-4">
        <button class="btn btn-primary" id="water_download_csv_btn">Download CSV</button>
      </div>
      <div id="water_report_section" class="table-responsive d-none"></div>

      <hr>

      <!-- SELECT MONTH - MANUAL ENTRY -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to Enter Data</label>
        <select id="water_month_manual" class="form-select">
          <option value="">-- Select Month --</option>
          <?php foreach ($fixed_months as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="water_missing_manual_branches" class="alert alert-warning mt-3 d-none"></div>
      <div id="water_provision_info" class="alert alert-info mt-3 d-none"></div>

      <!-- MANUAL ENTRY FORM -->
      <div id="water_manual_form" class="d-none mt-3">

        <div class="table-responsive water-entry-wrapper mb-3">
          <table class="table table-bordered align-middle water-entry-table">
            <thead class="table-light">
              <tr>
                <th class="col-code">Code</th>
                <th class="col-branch">Branch Name</th>
                <th class="col-type">Type</th>

                <!-- ✅ moved next to Type -->
                <th class="col-prov">Provision?</th>

                <th class="col-date">From</th>
                <th class="col-date">To</th>
                <th class="col-days">Days</th>
                <th class="col-qty">Qty</th>
                <th class="col-amount">Amount</th>
              </tr>
            </thead>

            <tbody id="water_entry_rows"></tbody>

          </table>
        </div>

        <button type="button" id="water_add_row" class="btn btn-outline-primary me-2">
            Add Row
        </button>

        <button type="button" id="water_save_entry" class="btn btn-success">
            Save Entry
        </button>

      </div>

      <div id="water_status_msg" class="mt-3"></div>

    </div>
  </div>
</div>

<script src="water-monthly-report.js?v=12"></script>
