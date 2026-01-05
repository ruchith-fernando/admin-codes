<?php
// tea-branches-monthly-report.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-detect FY (April → March) like Water
$current_year  = date('Y');
$current_month = date('n');
$fy_start_year = ($current_month < 4) ? $current_year - 1 : $current_year;
$fy_end_year   = $fy_start_year + 1;

// April→March list
$start = strtotime("{$fy_start_year}-04-01");
$end   = strtotime("{$fy_end_year}-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = date("F Y", $start);
    $start = strtotime("+1 month", $start);
}

// Months with APPROVED data
$data_months = [];
$q = mysqli_query($conn, "
    SELECT DISTINCT month_applicable
    FROM tbl_admin_actual_tea_branches
    WHERE total_amount IS NOT NULL
      AND TRIM(total_amount) <> ''
      AND total_amount <> '0'
      AND approval_status = 'approved'
");
while ($r = mysqli_fetch_assoc($q)) $data_months[] = $r['month_applicable'];
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">
        Tea Branches — Monthly Budget vs Actual (<?= $fy_start_year ?>–<?= $fy_end_year ?>)
      </h5>

      <!-- VIEW REPORT -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="tea_branches_month_view" class="form-select">
          <option value="">-- Choose a Month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="tea_branches_missing_view_branches" class="alert alert-warning d-none"></div>
      <div id="tea_branches_report_section" class="table-responsive d-none"></div>

      <div id="tea_branches_csv_download_container" class="d-none mt-3">
        <button class="btn btn-secondary" id="tea_branches_download_csv_btn">Download CSV</button>
      </div>

      <hr>

      <!-- Manual Entry -->
<div class="mb-3">
  <label class="form-label fw-bold">Select Month to Enter Data</label>
  <select id="tea_branches_month_manual" class="form-select">
    <option value="">-- Select Month --</option>
    <?php foreach ($fixed_months as $m): ?>
      <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div id="tea_branches_missing_manual_branches" class="alert alert-warning mt-3 d-none"></div>
<div id="tea_branches_provision_info" class="alert alert-info mt-3 d-none"></div>

<div id="tea_branches_manual_form" class="d-none">

  <table class="table table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:15%;">Branch Code</th>
        <th style="width:25%;">Branch Name</th>
        <th style="width:20%;">Total Amount</th>
        <th style="width:10%;">Provision?</th>
        <th style="width:30%;">Provision Reason</th>
      </tr>
    </thead>
    <tbody id="tea_branches_entry_rows"></tbody>
  </table>

  <button type="button" class="btn btn-outline-primary me-2" id="tea_branches_add_row">Add Row</button>
  <button type="button" class="btn btn-success" id="tea_branches_save_entry">Save Entry</button>
</div>

<div id="tea_branches_status_msg" class="mt-3"></div>

    </div>
  </div>
</div>

<script src="tea-branches-monthly-report.js?v=1"></script>
