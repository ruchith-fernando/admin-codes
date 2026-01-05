<?php
// electricity-budger-fetch.php
include 'nocache.php'; 
include 'connections/connection.php'; 


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = isset($_SESSION['hris']) ? mysqli_real_escape_string($conn, $_SESSION['hris']) : '';

$category = 'Electricity Charges';

/* FY months */
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) { $months[] = $start->format('F Y'); $start->modify('+1 month'); }

/* total branches for completion */
$total_branches = 0;
$resTB = $conn->query("SELECT COUNT(*) AS total FROM tbl_admin_branch_electricity");
if ($resTB && $rowTB = $resTB->fetch_assoc()) { $total_branches = (int)$rowTB['total']; }

$report = [];
foreach ($months as $month) {
    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    $actual_row = $conn->query("
        SELECT SUM(CAST(REPLACE(total_amount, ',', '') AS DECIMAL(15,2))) AS actual_amount
        FROM tbl_admin_actual_electricity 
        WHERE month_applicable = '$month_esc' AND TRIM(total_amount) != ''
    ")->fetch_assoc();
    $actual = (float)($actual_row['actual_amount'] ?? 0);
    if ($actual <= 0) continue;

    $budget_row = $conn->query("
        SELECT SUM(amount) AS budget_amount
        FROM tbl_admin_electricity_monthly_budget
        WHERE budget_month = '$month_esc'
    ")->fetch_assoc();
    $budget = (float)($budget_row['budget_amount'] ?? 0);

    $comp_row = $conn->query("
        SELECT COUNT(DISTINCT branch_code) AS completed
        FROM tbl_admin_actual_electricity
        WHERE month_applicable = '$month_esc' AND TRIM(total_amount) != ''
    ")->fetch_assoc();
    $completed = (int)($comp_row['completed'] ?? 0);

    $sel_row = $conn->query("
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection 
        WHERE category='$category_esc' 
          AND month_name='$month_esc'
          AND user_id='$user_id'
        LIMIT 1
    ")->fetch_assoc();
    $selected = ($sel_row['is_selected'] ?? '') === 'yes' ? 'checked' : '';

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
        <th>Completion Status</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 1; $total_budget = $total_actual = $total_difference = 0;
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
        <td class="<?= $row['completed'] < $row['total_branches'] ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
          <?= $row['completed'].'/'.$row['total_branches'] ?> Completed
        </td>
        <td class="toggle-cell">
          <div class="toggle-wrap">
            <div class="form-check form-switch m-0">
              <input
                type="checkbox"
                class="form-check-input month-checkbox"
                role="switch"
                id="month_switch_<?= htmlspecialchars(str_replace(' ', '_', $row['month'])) ?>"
                data-category="<?= htmlspecialchars($category) ?>"
                data-month="<?= htmlspecialchars($row['month']) ?>"
                <?= $row['checked'] ?> >
            </div>

            <button class="btn btn-sm btn-outline-secondary open-remarks"
                    data-category="<?= htmlspecialchars($category) ?>"
                    data-record="<?= htmlspecialchars($row['month']) ?>">💬</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php $total_variance = $total_budget > 0 ? round(($total_difference / $total_budget) * 100) : null; ?>
      <tr class="fw-bold table-light">
        <td colspan="2" class="text-end">Total</td>
        <td><?= number_format($total_budget, 2) ?></td>
        <td><?= number_format($total_actual, 2) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger' : '' ?>">
          <?= number_format($total_difference, 2) ?>
        </td>
        <td></td>
        <td colspan="2"></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td class="<?= ($total_variance !== null && $total_variance < 0) ? 'text-danger' : '' ?>">
          <?= $total_variance !== null ? $total_variance.'%' : 'N/A' ?>
        </td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
