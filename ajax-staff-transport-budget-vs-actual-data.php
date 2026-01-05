<?php
// ajax-staff-transport-budget-vs-actual-data.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache'); header('Expires: 0');

require_once 'connections/connection.php';

$category = 'Staff Transport';

// Session user (for per-user month selection, like Tea)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = $_SESSION['hris'] ?? '';
$user_id_esc = mysqli_real_escape_string($conn, (string)$user_id);

/** ✅ Financial Year: Apr → Mar (current FY window) */
$now = new DateTime('now');
$fy_start = ($now->format('n') >= 4)
  ? new DateTime($now->format('Y') . '-04-01')
  : new DateTime(($now->format('Y') - 1) . '-04-01');
$fy_end = (clone $fy_start)->modify('+1 year -1 day');

$fy_months = [];
$ptr = clone $fy_start;
while ($ptr <= $fy_end) {
  $fy_months[] = $ptr->format('F Y');
  $ptr->modify('+1 month');
}

/** ✅ Load budget (month -> amount) */
$budgets = [];
$q = $conn->query("SELECT budget_month, budget_amount FROM tbl_admin_budget_staff_transport");
while ($r = $q->fetch_assoc()) {
  $budgets[$r['budget_month']] = (float)$r['budget_amount'];
}

/** ✅ Load actuals separately */
// Kangaroo
$kangaroo = []; // month => amount
$qk = $conn->query("
  SELECT DATE_FORMAT(date, '%M %Y') AS month, SUM(total) AS amount
  FROM tbl_admin_kangaroo_transport
  GROUP BY month
");
while ($r = $qk->fetch_assoc()) {
  $m = $r['month'];
  if (in_array($m, $fy_months, true)) {
    $kangaroo[$m] = (float)$r['amount'];
  }
}

// PickMe
$pickme = []; // month => amount
$qp = $conn->query("
  SELECT
    DATE_FORMAT(STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p'), '%M %Y') AS month,
    SUM(total_fare) AS amount
  FROM tbl_admin_pickme_data
  WHERE pickup_time IS NOT NULL AND pickup_time != ''
  GROUP BY month
");
while ($r = $qp->fetch_assoc()) {
  $m = $r['month'];
  if (in_array($m, $fy_months, true)) {
    $pickme[$m] = (float)$r['amount'];
  }
}

/** ✅ Union months with any actuals */
$months_with_actuals = array_values(array_unique(array_merge(array_keys($kangaroo), array_keys($pickme))));
usort($months_with_actuals, function($a,$b){
  return strtotime("1 $a") <=> strtotime("1 $b");
});

/** ✅ Build rows (skip when total actual <= 0) */
$report = [];
$tot_budget_init = 0; 
$tot_pickme_init = 0;
$tot_kangaroo_init = 0;
$tot_actual_init = 0; 
$tot_diff_init = 0;

foreach ($months_with_actuals as $month) {
  $pm  = (float)($pickme[$month]   ?? 0);
  $kg  = (float)($kangaroo[$month] ?? 0);
  $tot = $pm + $kg;
  if ($tot <= 0) continue;

  $budget = (float)($budgets[$month] ?? 0);

  // ✅ Selected in dashboard — PER USER (HRIS)
  $month_esc = mysqli_real_escape_string($conn, $month);
  $cat_esc   = mysqli_real_escape_string($conn, $category);
  $sel_sql   = "
    SELECT is_selected
    FROM tbl_admin_dashboard_month_selection
    WHERE category='$cat_esc' AND month_name='$month_esc'
  ";
  if ($user_id_esc !== '') {
    $sel_sql .= " AND user_id='$user_id_esc' ";
  }
  $sel_sql .= " LIMIT 1";
  $sel = $conn->query($sel_sql)->fetch_assoc();
  $checked = (isset($sel['is_selected']) && strtolower($sel['is_selected'])==='yes') ? 'checked' : '';

  $diff   = $budget - $tot;
  $varPct = $budget > 0 ? round(($diff / $budget) * 100) : null;

  $report[] = [
    'month'      => $month,
    'budget'     => $budget,
    'pickme'     => $pm,
    'kangaroo'   => $kg,
    'total'      => $tot,
    'difference' => $diff,
    'variance'   => $varPct,
    'checked'    => $checked
  ];

  // Initial footer sums reflect only pre-checked rows
  if ($checked) {
    $tot_budget_init   += $budget;
    $tot_pickme_init   += $pm;
    $tot_kangaroo_init += $kg;
    $tot_actual_init   += $tot;
    $tot_diff_init     += $diff;
  }
}

$total_variance_init = ($tot_budget_init > 0) ? round(($tot_diff_init / $tot_budget_init) * 100) : null;

/** 🔧 Helper for predictable row ids */
if (!function_exists('slugify_row')) {
  function slugify_row($s){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($s ?? '')); }
}
?>
<style>
/* Scope styles to #report-content */
#report-content .table .form-switch { padding-left: 0; min-height: 0; }
#report-content .form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
#report-content .toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }

/* Optional row highlight */
#report-content .report-row.row-focus{
  background:#fff8d1 !important;
  animation:rowPulse 1.8s ease-out 0s 2;
}
@keyframes rowPulse{
  0%{ box-shadow:0 0 0 0 rgba(255,193,7,.55); }
  70%{ box-shadow:0 0 0 10px rgba(255,193,7,0); }
  100%{ box-shadow:0 0 0 0 rgba(255,193,7,0); }
}
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm text-center wide-table">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Month</th>
        <th>Budgeted Amount (Rs)</th>
        <th>PickMe (Rs)</th>
        <th>Kangaroo (Rs)</th>
        <th>Total Actual (Rs)</th>
        <th>Difference (Rs)</th>
        <th>Variance (%)</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!count($report)): ?>
        <tr><td colspan="9" class="text-muted">No records for the current financial year.</td></tr>
      <?php else: ?>
        <?php $i=1; foreach ($report as $row): ?>
          <tr
            class="report-row"
            data-category="<?= htmlspecialchars($category) ?>"
            data-record="<?= htmlspecialchars($row['month']) ?>"
            id="row-<?= slugify_row($category.'-'.$row['month']) ?>"
          >
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['month']) ?></td>
            <td><?= number_format($row['budget'], 2) ?></td>
            <td><?= number_format($row['pickme'], 2) ?></td>
            <td><?= number_format($row['kangaroo'], 2) ?></td>
            <td><?= number_format($row['total'], 2) ?></td>
            <td class="<?= $row['difference'] < 0 ? 'text-danger fw-bold' : '' ?>">
              <?= number_format($row['difference'], 2) ?>
            </td>
            <td class="<?= ($row['variance'] !== null && $row['variance'] < 0) ? 'text-danger fw-bold' : '' ?>">
              <?= $row['variance'] !== null ? $row['variance'].'%' : 'N/A' ?>
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
                    <?= $row['checked'] ?>>
                </div>

                <button class="btn btn-sm btn-outline-secondary open-remarks"
                        data-category="<?= htmlspecialchars($category) ?>"
                        data-record="<?= htmlspecialchars($row['month']) ?>">💬</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <tr class="fw-bold table-light">
          <td colspan="2" class="text-end">Total</td>
          <td><?= number_format($tot_budget_init, 2) ?></td>
          <td><?= number_format($tot_pickme_init, 2) ?></td>
          <td><?= number_format($tot_kangaroo_init, 2) ?></td>
          <td><?= number_format($tot_actual_init, 2) ?></td>
          <td class="<?= $tot_diff_init < 0 ? 'text-danger' : '' ?>">
            <?= number_format($tot_diff_init, 2) ?>
          </td>
          <td></td>
          <td></td>
        </tr>
        <tr class="fw-bold table-light">
          <td colspan="5" class="text-end">Total Variance (%)</td>
          <td class="<?= ($total_variance_init !== null && $total_variance_init < 0) ? 'text-danger' : '' ?>">
            <?= $total_variance_init !== null ? $total_variance_init.'%' : 'N/A' ?>
          </td>
          <td colspan="3"></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
