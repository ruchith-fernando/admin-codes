<?php
// security-vpn-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = isset($_SESSION['hris']) ? mysqli_real_escape_string($conn, $_SESSION['hris']) : '';

$category = 'Security VPN';

/* FY months (April 2025 to March 2026) */
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

/* ---------------- Fetch report ---------------- */
$report = [];
foreach ($months as $month) {
    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    /* 🔹 Fetch actual for the month */
    $actual_row = $conn->query("
        SELECT SUM(total_amount) AS actual_amount
        FROM tbl_admin_actual_security_vpn
        WHERE month_name = '$month_esc'
    ")->fetch_assoc();
    $actual = (float)($actual_row['actual_amount'] ?? 0);

    /* ⛔ Skip months with no actuals entered */
    if ($actual <= 0) continue;

    /* 🔹 Fetch budget per month */
    $budget_row = $conn->query("
        SELECT amount AS budget_amount
        FROM tbl_admin_budget_security_vpn
        WHERE month_name = '$month_esc'
        LIMIT 1
    ")->fetch_assoc();
    $budget = (float)($budget_row['budget_amount'] ?? 0);

    /* 🔹 Dashboard selection (user saved months) */
    $sel_row = $conn->query("
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection 
        WHERE category='$category_esc' 
          AND month_name='$month_esc'
          AND user_id='$user_id'
        LIMIT 1
    ")->fetch_assoc();
    $selected = ($sel_row['is_selected'] ?? '') === 'yes' ? 'checked' : '';

    /* 🔹 Difference & Variance */
    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    $report[] = [
        'month'      => $month,
        'budget'     => $budget,
        'actual'     => $actual,
        'difference' => $difference,
        'variance'   => $variance,
        'checked'    => $selected,
    ];
}
?>
<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
.wide-table { min-width: 980px; }
.report-row.row-focus{ background:#fff8d1 !important; animation:rowPulse 1.8s ease-out 0s 2; }
@keyframes rowPulse{ 0%{box-shadow:0 0 0 0 rgba(255,193,7,.55);} 70%{box-shadow:0 0 0 10px rgba(255,193,7,0);} 100%{box-shadow:0 0 0 0 rgba(255,193,7,0);} }
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm text-center wide-table">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Month</th>
        <th>Budgeted Amount (Rs)</th>
        <th>Actual Amount (Rs)</th>
        <th>Difference (Rs)</th>
        <th>Variance (%)</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 1; 
      $total_budget = $total_actual = $total_difference = 0;
      foreach ($report as $row):
        $total_budget     += $row['budget'];
        $total_actual     += $row['actual'];
        $total_difference += $row['difference'];
      ?>
      <tr class="report-row" data-category="<?= htmlspecialchars($category) ?>" data-record="<?= htmlspecialchars($row['month']) ?>">
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['month']) ?></td>
        <td><?= number_format($row['budget'], 2) ?></td>
        <td><?= number_format($row['actual'], 2) ?></td>
        <td class="<?= $row['difference'] < 0 ? 'text-danger fw-bold' : '' ?>">
          <?= number_format($row['difference'], 2) ?>
        </td>
        <td class="<?= ($row['variance'] !== null && $row['variance'] < 0) ? 'text-danger fw-bold' : '' ?>">
          <?= $row['variance'] !== null ? $row['variance'].'%' : 'N/A' ?>
        </td>
        <td class="toggle-cell">
          <div class="toggle-wrap">
            <div class="form-check form-switch">
              <input class="form-check-input month-checkbox" type="checkbox"
                     data-month="<?= htmlspecialchars($row['month']) ?>"
                     data-category="<?= htmlspecialchars($category) ?>"
                     <?= $row['checked'] ?>>
            </div>
            <button class="btn btn-sm btn-outline-primary open-remarks"
                    data-category="<?= htmlspecialchars($category) ?>"
                    data-record="<?= htmlspecialchars($row['month']) ?>">
              💬
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php
      $overall_variance = ($total_budget > 0) ? round(($total_difference / $total_budget) * 100) : null;
      ?>
      <tr class="fw-bold table-light">
        <td colspan="2">Total</td>
        <td><?= number_format($total_budget, 2) ?></td>
        <td><?= number_format($total_actual, 2) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger' : '' ?>"><?= number_format($total_difference, 2) ?></td>
        <td colspan="2"></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="2">Overall Variance</td>
        <td colspan="5" class="<?= ($overall_variance !== null && $overall_variance < 0) ? 'text-danger' : '' ?>">
          <?= $overall_variance !== null ? $overall_variance.'%' : 'N/A' ?>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
