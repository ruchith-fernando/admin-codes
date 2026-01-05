<?php
// branch-water-monthly-report.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-detect FY (April → March)
$current_year = date('Y');
$current_month = date('n');
$fy_start_year = ($current_month < 4) ? $current_year - 1 : $current_year;
$fy_end_year   = $fy_start_year + 1;

// Build month range April → March
$start = strtotime("{$fy_start_year}-04-01");
$end   = strtotime("{$fy_end_year}-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = date("F Y", $start);
    $start = strtotime("+1 month", $start);
}

// LOAD months with APPROVED data only
$data_months = [];
$q = mysqli_query($conn, "
    SELECT DISTINCT month_applicable 
    FROM tbl_admin_actual_water 
    WHERE approval_status = 'approved'
      AND total_amount IS NOT NULL 
      AND TRIM(total_amount) <> ''
      AND total_amount <> '0'
");
while ($r = mysqli_fetch_assoc($q)) {
    $data_months[] = $r['month_applicable'];
}

?>
<style>
  .water-entry-table .col-code { width: 8%; min-width: 80px; }
  .water-entry-table .col-branch { width: 22%; min-width: 180px; }
  .water-entry-table .col-type { width: 10%; min-width: 80px; }
  .water-entry-table .col-date { width: 12%; min-width: 120px; }
  .water-entry-table .col-days { width: 8%; min-width: 70px; }
  .water-entry-table .col-qty { width: 10%; min-width: 80px; }
  .water-entry-table .col-amount { width: 14%; min-width: 120px; text-align:right; }

  .water-entry-wrapper { width: 100%; }

  @media (max-width: 992px) {
    .water-entry-table th, .water-entry-table td { white-space: nowrap; }
  }
  @media (max-width: 768px) {
    .water-entry-wrapper { overflow-x: auto; }
    .water-entry-table { min-width: 900px; }
  }
  @media (max-width: 576px) {
    .water-entry-wrapper { overflow-x: auto; }
    .water-entry-table { min-width: 1000px; }
  }
</style>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">
        Water — Monthly Budget vs Actual (<?= $fy_start_year ?>–<?= $fy_end_year ?>)
      </h5>

      <!-- VIEW REPORT -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="water_month_view" class="form-select">
          <option value="">-- Choose a month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="water_missing_view_branches" class="alert alert-warning d-none"></div>
      <div id="water_report_section" class="table-responsive d-none"></div>

      <div id="water_csv_download_container" class="d-none mt-3">
        <button class="btn btn-secondary" id="water_download_csv_btn">Download CSV</button>
      </div>

      <hr>

      <!-- MANUAL ENTRY -->
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

      <div id="water_manual_form" class="d-none mt-3">

        <div class="table-responsive water-entry-wrapper">
          <table class="table table-bordered align-middle water-entry-table w-100">
            <thead class="table-light">
              <tr>
                <th class="col-code">Code</th>
                <th class="col-branch">Branch Name</th>
                <th class="col-type">Type</th>
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

        <button class="btn btn-success" id="water_save_entry">Save Entry</button>
      </div>

      <div id="water_status_msg" class="mt-3"></div>
    </div>
  </div>
</div>

<script src="branch-water-monthly-report.js"></script>
