<?php
// electricity-monthly-report.php
require_once 'connections/connection.php';

// Fixed months April 2025 -> March 2026
$start = strtotime("2025-04-01");
$end   = strtotime("2026-03-01");
$fixed_months = [];
while ($start <= $end) {
  $fixed_months[] = date("F Y", $start);
  $start = strtotime("+1 month", $start);
}

// Months with actual_units > 0 (for View Report dropdown)
$data_months = [];
$q = mysqli_query($conn, "
  SELECT DISTINCT month_applicable 
  FROM tbl_admin_actual_electricity 
  WHERE actual_units IS NOT NULL AND TRIM(actual_units) <> '' AND actual_units <> '0'
");
while ($r = mysqli_fetch_assoc($q)) $data_months[] = $r['month_applicable'];
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Electricity — Monthly Budget vs Actual</h5>

      <!-- View Report -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="elec_month_view" class="form-select">
          <option value="">-- Choose a month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="elec_csv_download_container" class="mb-3 d-none">
        <button class="btn btn-outline-primary" id="elec_download_csv_btn">⬇️ Download CSV</button>
      </div>

      <div id="elec_missing_view_branches" class="alert alert-warning d-none"></div>

      <div id="elec_report_section" class="table-responsive d-none"></div>

      <hr>

      <!-- Manual Entry -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to Enter Data</label>
        <select id="elec_month_manual" class="form-select">
          <option value="">-- Select Month --</option>
          <?php foreach ($fixed_months as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="elec_missing_manual_branches" class="alert alert-warning mt-3 d-none"></div>
      <div id="elec_provision_info" class="alert alert-info mt-3 d-none"></div>

      <div id="elec_manual_form" class="d-none">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:10%;">Branch Code</th>
              <th style="width:16%;">Branch Name</th>
              <th style="width:14%;">Account No</th>
              <th style="width:14%;">Bank Paid To</th>
              <th style="width:9%;">Units</th>
              <th style="width:12%;">Total Amount</th>
              <th style="width:9%;">Provision?</th>
              <th style="width:16%;">Provision Reason</th>
            </tr>
          </thead>
          <tbody id="elec_entry_rows">
            <tr>
              <td><input type="text" class="form-control elec_branch_code" maxlength="5" /></td>
              <td><input type="text" class="form-control elec_branch_name" readonly /></td>
              <td><input type="text" class="form-control elec_account_no" readonly /></td>
              <td><input type="text" class="form-control elec_bank_paid_to" readonly /></td>
              <td><input type="number" step="0.01" min="0.01" class="form-control elec_units" /></td>
              <td><input type="text" class="form-control elec_amount" /></td>
              <td>
                <select class="form-select elec_provision">
                  <option value="no" selected>No</option>
                  <option value="yes">Yes</option>
                </select>
              </td>
              <td><input type="text" class="form-control elec_provision_reason" placeholder="Optional" /></td>
            </tr>
          </tbody>
        </table>
        <button class="btn btn-success" id="elec_save_entry">Save Entry</button>
      </div>

      <div id="elec_status_msg" class="mt-3"></div>
    </div>
  </div>
</div>
<script src="electricity-monthly-report.js"></script>
