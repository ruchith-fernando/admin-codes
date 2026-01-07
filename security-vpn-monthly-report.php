<?php
// security-vpn-monthly-report.php
require_once 'connections/connection.php';

/* ===========================
   AJAX HANDLERS (same file)
   =========================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch') {
  header('Content-Type: application/json; charset=UTF-8');

  $month = trim($_POST['month'] ?? '');
  if ($month === '') {
    echo json_encode(['error' => '⚠️ No month received']); exit;
  }

  // Fetch Budget (your table has month_name + amount per month)
  $budget = 0.0;
  $sql = "SELECT amount FROM tbl_admin_budget_security_vpn
          WHERE month_name = '".mysqli_real_escape_string($conn,$month)."' LIMIT 1";
  if ($r = mysqli_query($conn,$sql)) {
    if ($row = mysqli_fetch_assoc($r)) $budget = (float)$row['amount'];
  }

  // Fetch Actual
  $actual = 0.0; $is_prov = 'no'; $reason = '';
  $sql = "SELECT total_amount, is_provision, provision_reason
          FROM tbl_admin_actual_security_vpn
          WHERE month_name = '".mysqli_real_escape_string($conn,$month)."' LIMIT 1";
  if ($r = mysqli_query($conn,$sql)) {
    if ($row = mysqli_fetch_assoc($r)) {
      $actual  = (float)$row['total_amount'];
      $is_prov = (string)$row['is_provision'];
      $reason  = (string)($row['provision_reason'] ?? '');
    }
  }

  if ($budget == 0 && $actual == 0) {
    echo json_encode(['error' => "❌ No data found for $month"]); exit;
  }

  $variance = $actual - $budget;
  $table = "
  <table class='table table-bordered table-striped'>
    <thead class='table-light'>
      <tr>
        <th>Month</th>
        <th>Budget</th>
        <th>Actual</th>
        <th>Variance</th>
        <th>Provision?</th>
        <th>Reason</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>".htmlspecialchars($month)."</td>
        <td class='text-end'>".number_format($budget,2)."</td>
        <td class='text-end'>".number_format($actual,2)."</td>
        <td class='text-end ".($variance<0?'text-danger':'text-success')."'>".number_format($variance,2)."</td>
        <td>".(strtolower($is_prov)==='yes'?'Yes':'No')."</td>
        <td>".htmlspecialchars($reason)."</td>
      </tr>
    </tbody>
  </table>";

  echo json_encode(['table'=>$table]); exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'save') {
  header('Content-Type: application/json; charset=UTF-8');

  $month     = trim($_POST['month'] ?? '');
  $amount    = trim($_POST['amount'] ?? '');
  $provision = trim($_POST['provision'] ?? 'no');
  $reason    = trim($_POST['provision_reason'] ?? '');

  if ($month==='' || $amount==='') {
    echo json_encode(['success'=>false,'message'=>'Missing fields']); exit;
  }
  if (!is_numeric(str_replace(',','',$amount)) || (float)str_replace(',','',$amount) <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid amount']); exit;
  }
  $amount_num = (float)str_replace(',','',$amount);

  // Upsert by existence (no need for unique key)
  $month_esc = mysqli_real_escape_string($conn,$month);
  $check = mysqli_query($conn,"SELECT id FROM tbl_admin_actual_security_vpn WHERE month_name='$month_esc' LIMIT 1");
  if ($check && mysqli_num_rows($check) > 0) {
    $row = mysqli_fetch_assoc($check);
    $id  = (int)$row['id'];
    $sql = "UPDATE tbl_admin_actual_security_vpn
            SET total_amount='".mysqli_real_escape_string($conn,$amount_num)."',
                is_provision='".mysqli_real_escape_string($conn,$provision)."',
                provision_reason='".mysqli_real_escape_string($conn,$reason)."',
                provision_updated_at=NOW()
            WHERE id=$id";
  } else {
    $sql = "INSERT INTO tbl_admin_actual_security_vpn
              (month_name,total_amount,is_provision,provision_reason)
            VALUES(
              '$month_esc',
              '".mysqli_real_escape_string($conn,$amount_num)."',
              '".mysqli_real_escape_string($conn,$provision)."',
              '".mysqli_real_escape_string($conn,$reason)."'
            )";
  }

  if (mysqli_query($conn,$sql)) {
    echo json_encode(['success'=>true,'message'=>'Saved successfully.']); exit;
  } else {
    echo json_encode(['success'=>false,'message'=>'Database error: '.mysqli_error($conn)]); exit;
  }
}

/* ===========================
   PAGE (HTML + JS)
   =========================== */

// Build fixed months (Apr 2025 → Mar 2026)
$start = strtotime("2025-04-01");
$end   = strtotime("2026-03-01");
$fixed_months = [];
while ($start <= $end) {
  $fixed_months[] = date("F Y", $start);
  $start = strtotime("+1 month", $start);
}

// Months having actual entries
$data_months = [];
$q = mysqli_query($conn, "
  SELECT DISTINCT month_name 
  FROM tbl_admin_actual_security_vpn
  WHERE total_amount IS NOT NULL 
    AND TRIM(total_amount) <> '' 
    AND total_amount <> '0'
");
while ($r = mysqli_fetch_assoc($q)) $data_months[] = $r['month_name'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Security VPN — Monthly Budget vs Actual</title>
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> -->
</head>
<body class="p-3">
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Security VPN — Monthly Budget vs Actual</h5>

      <!-- View Report -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="vpn_month_view" class="form-select">
          <option value="">-- Choose a month --</option>
          <?php foreach ($fixed_months as $m): if (in_array($m, $data_months)): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div id="vpn_csv_download_container" class="mb-3 d-none">
        <button class="btn btn-outline-primary" id="vpn_download_csv_btn">⬇️ Download CSV</button>
      </div>

      <div id="vpn_report_section" class="table-responsive d-none"></div>
      <div id="vpn_status_msg" class="mt-3"></div>

      <hr>

      <!-- Manual Entry -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to Enter Data</label>
        <select id="vpn_month_manual" class="form-select">
          <option value="">-- Select Month --</option>
          <?php foreach ($fixed_months as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="vpn_manual_form" class="d-none">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Month</th>
              <th>Total Amount</th>
              <th>Provision?</th>
              <th>Provision Reason</th>
            </tr>
          </thead>
          <tbody id="vpn_entry_rows">
            <tr>
              <td id="vpn_selected_month" class="align-middle"></td>
              <td><input type="text" class="form-control vpn_amount" /></td>
              <td>
                <select class="form-select vpn_provision">
                  <option value="no" selected>No</option>
                  <option value="yes">Yes</option>
                </select>
              </td>
              <td><input type="text" class="form-control vpn_provision_reason" placeholder="Optional" /></td>
            </tr>
          </tbody>
        </table>
        <button class="btn btn-success" id="vpn_save_entry">💾 Save Entry</button>
      </div>
    </div>
  </div>
</div>
<script src="security-vpn-monthly-report.js?v=1"></script>
</body>
</html>
