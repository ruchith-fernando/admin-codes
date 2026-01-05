<?php
// newspaper-monthly-report.php
require_once 'connections/connection.php';

// Fixed months April 2025 → March 2026
$start = strtotime("2025-04-01");
$end   = strtotime("2026-03-01");
$fixed_months = [];
while ($start <= $end) {
  $fixed_months[] = date("F Y", $start);
  $start = strtotime("+1 month", $start);
}

// Months with actual total_amount > 0
$data_months = [];
$q = mysqli_query($conn, "
  SELECT DISTINCT month_applicable 
  FROM tbl_admin_actual_newspaper 
  WHERE total_amount IS NOT NULL AND TRIM(total_amount) <> '' AND total_amount <> '0'
");
while ($r = mysqli_fetch_assoc($q)) $data_months[] = $r['month_applicable'];
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">News Paper — Monthly Budget vs Actual</h5>

      <!-- View Report -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="newspaper_month_view" class="form-select">
          <option value="">-- Choose a month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="newspaper_csv_download_container" class="mb-3 d-none">
        <button class="btn btn-outline-primary" id="newspaper_download_csv_btn">⬇️ Download CSV</button>
      </div>

      <div id="newspaper_missing_view_branches" class="alert alert-warning d-none"></div>
      <div id="newspaper_report_section" class="table-responsive d-none"></div>

      <hr>

      <!-- Manual Entry -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to Enter Data</label>
        <select id="newspaper_month_manual" class="form-select">
          <option value="">-- Select Month --</option>
          <?php foreach ($fixed_months as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="newspaper_missing_manual_branches" class="alert alert-warning mt-3 d-none"></div>
      <div id="newspaper_provision_info" class="alert alert-info mt-3 d-none"></div>

      <div id="newspaper_manual_form" class="d-none">
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
          <tbody id="newspaper_entry_rows">
            <tr>
              <td><input type="text" class="form-control newspaper_branch_code" maxlength="10" /></td>
              <td><input type="text" class="form-control newspaper_branch_name" readonly /></td>
              <td><input type="text" class="form-control newspaper_amount" /></td>
              <td>
                <select class="form-select newspaper_provision">
                  <option value="no" selected>No</option>
                  <option value="yes">Yes</option>
                </select>
              </td>
              <td><input type="text" class="form-control newspaper_provision_reason" placeholder="Optional" /></td>
            </tr>
          </tbody>
        </table>
        <button class="btn btn-success" id="newspaper_save_entry">Save Entry</button>
      </div>

      <div id="newspaper_status_msg" class="mt-3"></div>
    </div>
  </div>
</div>
<script src="newspaper-monthly-report.js"></script>
