<?php
// printing-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$user_id = isset($_SESSION['hris']) ? mysqli_real_escape_string($conn, $_SESSION['hris']) : '';
$category = 'Printing & Stationary';

/* -----------------------------
   FY months (Apr 2025 → Mar 2026)
------------------------------*/
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

/* -----------------------------
   Total branches (use master list)
   safer than budget table because budget has 12 rows per branch now
------------------------------*/
$total_branches = 0;
$resTB = $conn->query("SELECT COUNT(*) AS total FROM tbl_admin_branch_printing");
if ($resTB && $rowTB = $resTB->fetch_assoc()) {
    $total_branches = (int)$rowTB['total'];
}

/* -----------------------------
   Helpers: money + variance formatting
------------------------------*/
function fmt_money($n) {
    return number_format((float)$n, 2);
}

// finance style: negative shown as (1,234.56)
function fmt_money_paren($n) {
    $n = (float)$n;
    if ($n < 0) return "(" . number_format(abs($n), 2) . ")";
    return number_format($n, 2);
}

$report = [];

/* -----------------------------
   Build report rows
------------------------------*/
foreach ($months as $month) {

    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    // actual total for month
    $actual_row = $conn->query("
        SELECT SUM(CAST(REPLACE(total_amount, ',', '') AS DECIMAL(15,2))) AS actual_amount
        FROM tbl_admin_actual_printing
        WHERE month_applicable = '$month_esc'
          AND TRIM(total_amount) <> ''
    ")->fetch_assoc();

    $actual = (float)($actual_row['actual_amount'] ?? 0);

    // keep your old behavior: skip months with no actual entries
    if ($actual <= 0) continue;

    // ✅ NEW: budget total for the SAME MONTH (budget_year stores month text now)
    $budget_row = $conn->query("
        SELECT SUM(amount) AS budget_amount
        FROM tbl_admin_budget_printing
        WHERE budget_year = '$month_esc'
    ")->fetch_assoc();

    $budget = (float)($budget_row['budget_amount'] ?? 0);

    // how many branches have entered actual values for the month
    $comp_row = $conn->query("
        SELECT COUNT(DISTINCT branch_code) AS completed
        FROM tbl_admin_actual_printing
        WHERE month_applicable = '$month_esc'
          AND TRIM(total_amount) <> ''
    ")->fetch_assoc();

    $completed = (int)($comp_row['completed'] ?? 0);

    // dashboard selection (keep same logic)
    $sel_row = $conn->query("
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category='$category_esc'
          AND month_name='$month_esc'
          AND user_id='$user_id'
        LIMIT 1
    ")->fetch_assoc();

    $selected = ($sel_row['is_selected'] ?? '') === 'yes' ? 'checked' : '';

    // difference + variance
    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    $report[] = [
        'month'          => $month,
        'budget'         => $budget,
        'actual'         => $actual,
        'difference'     => $difference,
        'variance'       => $variance,
        'completed'      => $completed,
        'total_branches' => $total_branches,
        'checked'        => $selected,
    ];
}
?>
<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
.wide-table { min-width: 980px; }

/* focus effect (your original) */
.report-row.row-focus{ background:#fff8d1 !important; animation:rowPulse 1.8s ease-out 0s 2; }
@keyframes rowPulse{ 0%{box-shadow:0 0 0 0 rgba(255,193,7,.55);} 70%{box-shadow:0 0 0 10px rgba(255,193,7,0);} 100%{box-shadow:0 0 0 0 rgba(255,193,7,0);} }

/* ✅ over budget highlight */
.report-row.over-budget-row > * {
  background-color: #ffecec !important;
}
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
        <th>Completion Status</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 1;
      $total_budget = 0;
      $total_actual = 0;
      $total_difference = 0;

      foreach ($report as $row):
        $total_budget     += $row['budget'];
        $total_actual     += $row['actual'];
        $total_difference += $row['difference'];

        // if actual > budget -> highlight row + show negative style
        $is_over = ($row['actual'] > $row['budget']);
        $rowClass = "report-row" . ($is_over ? " over-budget-row" : "");
      ?>
      <tr class="<?= $rowClass ?>"
          data-category="<?= htmlspecialchars($category) ?>"
          data-record="<?= htmlspecialchars($row['month']) ?>">

        <td><?= $i++ ?></td>

        <td><?= htmlspecialchars($row['month']) ?></td>

        <td><?= fmt_money($row['budget']) ?></td>

        <td><?= fmt_money($row['actual']) ?></td>

        <td class="<?= $row['difference'] < 0 ? 'text-danger fw-bold' : '' ?>">
          <?= fmt_money_paren($row['difference']) ?>
        </td>

        <td class="<?= ($row['variance'] !== null && $row['variance'] < 0) ? 'text-danger fw-bold' : '' ?>">
          <?= $row['variance'] !== null ? $row['variance'].'%' : 'N/A' ?>
        </td>

        <td><?= $row['completed'].' / '.$row['total_branches'].' completed' ?></td>

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
        <td><?= fmt_money($total_budget) ?></td>
        <td><?= fmt_money($total_actual) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger fw-bold' : '' ?>">
          <?= fmt_money_paren($total_difference) ?>
        </td>
        <td colspan="3"></td>
      </tr>

      <tr class="fw-bold table-light">
        <td colspan="2">Overall Variance</td>
        <td colspan="6" class="<?= ($overall_variance !== null && $overall_variance < 0) ? 'text-danger fw-bold' : '' ?>">
          <?= $overall_variance !== null ? $overall_variance.'%' : 'N/A' ?>
        </td>
      </tr>

    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
