<?php
// ajax-postage-budget-vs-actual.php  (CLEAN: HTML ONLY, no totals JS)

include 'nocache.php';
require_once 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$category = 'Postage & Stamps';

// per-user selection
$user_id = isset($_SESSION['hris']) ? $_SESSION['hris'] : '';
$user_id_esc = mysqli_real_escape_string($conn, $user_id);

// FY Apr->Mar (Asia/Colombo)
$tz  = new DateTimeZone('Asia/Colombo');
$now = new DateTimeImmutable('now', $tz);
$y   = (int)$now->format('Y');
$m   = (int)$now->format('n');

if ($m >= 4) {
  $fyStart   = new DateTimeImmutable("$y-04-01", $tz);
  $fyEndExcl = $fyStart->modify('+1 year');
} else {
  $fyStart   = new DateTimeImmutable(($y - 1) . '-04-01', $tz);
  $fyEndExcl = $fyStart->modify('+1 year');
}

$fyStartStr = $fyStart->format('Y-m-d');
$fyEndStr   = $fyEndExcl->format('Y-m-d'); // exclusive

/* ---------- ACTUALS (months list ONLY where actual exists) ---------- */
$actualByMonth = [];
$completedByMonth = [];
$months = [];

$qA = $conn->query("
  SELECT
    applicable_month AS month_year,
    SUM(ABS(debits)) AS actual_amount,
    COUNT(DISTINCT enterd_brn) AS completed,
    MIN(dateoftran) AS first_date
  FROM tbl_admin_actual_branch_gl_postage
  WHERE dateoftran >= '$fyStartStr'
    AND dateoftran < '$fyEndStr'
    AND debits <> 0
    AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
  GROUP BY applicable_month
  HAVING SUM(ABS(debits)) > 0
  ORDER BY first_date
");

if ($qA) {
  while ($r = $qA->fetch_assoc()) {
    $mm = trim((string)$r['month_year']);
    $months[] = $mm;
    $actualByMonth[$mm] = (float)($r['actual_amount'] ?? 0);
    $completedByMonth[$mm] = (int)($r['completed'] ?? 0);
  }
}

if (!count($months)) {
  echo '<div class="alert alert-warning">No actuals found for the current financial year.</div>';
  exit;
}

$inMonths = implode(", ", array_map(
  fn($mm) => "'" . $conn->real_escape_string($mm) . "'",
  $months
));

/* ---------- BUDGETS ---------- */
$budgetByMonth = array_fill_keys($months, 0.0);
$totalBranchesByMonth = array_fill_keys($months, 0);

$qB = $conn->query("
  SELECT
    applicable_month,
    SUM(budget_amount) AS budget_amount,
    COUNT(DISTINCT branch_code) AS total_branches
  FROM tbl_admin_budget_postage
  WHERE applicable_month IN ($inMonths)
  GROUP BY applicable_month
");

if ($qB) {
  while ($r = $qB->fetch_assoc()) {
    $mm = trim((string)$r['applicable_month']);
    if (isset($budgetByMonth[$mm])) {
      $budgetByMonth[$mm] = (float)($r['budget_amount'] ?? 0);
      $totalBranchesByMonth[$mm] = (int)($r['total_branches'] ?? 0);
    }
  }
}

/* ---------- Selection (PER USER) ---------- */
$selectedMap = array_fill_keys($months, '');
$categoryEsc = $conn->real_escape_string($category);

$qSel = $conn->query("
  SELECT month_name, is_selected
  FROM tbl_admin_dashboard_month_selection
  WHERE category = '$categoryEsc'
    AND user_id  = '$user_id_esc'
    AND month_name IN ($inMonths)
");

if ($qSel) {
  while ($r = $qSel->fetch_assoc()) {
    $mm = trim((string)$r['month_name']);
    if (isset($selectedMap[$mm])) {
      $selectedMap[$mm] = (strtolower($r['is_selected'] ?? '') === 'yes') ? 'checked' : '';
    }
  }
}

/* ---------- Build report rows ---------- */
$report = [];
foreach ($months as $month) {
  $actual = (float)($actualByMonth[$month] ?? 0.0);
  if ($actual <= 0) continue;

  $budget     = (float)($budgetByMonth[$month] ?? 0.0);
  $difference = $budget - $actual;
  $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

  $report[] = [
    'month'          => $month,
    'budget'         => $budget,
    'actual'         => $actual,
    'difference'     => $difference,
    'variance'       => $variance,
    'completed'      => (int)($completedByMonth[$month] ?? 0),
    'total_branches' => (int)($totalBranchesByMonth[$month] ?? 0),
    'checked'        => $selectedMap[$month] ?? '',
    'over_budget'    => ($budget > 0 && $actual > $budget),
  ];
}

// Initial totals (ALL rows) - only for initial render
$total_budget     = array_sum(array_column($report, 'budget'));
$total_actual     = array_sum(array_column($report, 'actual'));
$total_difference = array_sum(array_column($report, 'difference'));
$total_variance   = $total_budget > 0 ? round(($total_difference / $total_budget) * 100) : null;
?>

<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
.wide-table { min-width: 980px; }
.postage-summary-table tbody tr.over-budget-row > * { background-color: #ffecec !important; }
</style>

<div class="table-responsive">
  <table id="postage_table" class="table table-bordered table-sm text-center wide-table postage-summary-table">
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
      <?php if (!count($report)): ?>
        <tr><td colspan="8" class="text-muted">No records with actuals for the current financial year.</td></tr>
      <?php else: $i=1; foreach ($report as $row): ?>
        <?php
          $trClass = "report-row";
          if (!empty($row['over_budget'])) $trClass .= " over-budget-row";
        ?>
        <tr class="<?= $trClass ?>"
            data-category="<?= htmlspecialchars($category) ?>"
            data-record="<?= htmlspecialchars($row['month']) ?>">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['month']) ?></td>
          <td><?= number_format($row['budget'], 2) ?></td>
          <td><?= number_format($row['actual'], 2) ?></td>

          <td class="<?= ($row['difference'] < 0 ? 'text-danger fw-bold' : '') ?>">
            <?= number_format($row['difference'], 2) ?>
          </td>

          <td class="<?= (($row['variance'] !== null && $row['variance'] < 0) ? 'text-danger fw-bold' : '') ?>">
            <?= $row['variance'] !== null ? $row['variance'].'%' : 'N/A' ?>
          </td>

          <td>
            <?php
              $tb = (int)$row['total_branches'];
              echo $tb > 0
                ? ((int)$row['completed'] . ' / ' . $tb . ' completed')
                : ((int)$row['completed'] . ' completed');
            ?>
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
        <td><span id="total_budget"><?= number_format($total_budget, 2) ?></span></td>
        <td><span id="total_actual"><?= number_format($total_actual, 2) ?></span></td>
        <td id="total_difference_td" class="<?= $total_difference < 0 ? 'text-danger fw-bold' : '' ?>">
          <span id="total_difference"><?= number_format($total_difference, 2) ?></span>
        </td>
        <td></td><td></td><td></td>
      </tr>

      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td id="total_variance_td" class="<?= ($total_variance !== null && $total_variance < 0) ? 'text-danger fw-bold' : '' ?>">
          <span id="total_variance"><?= $total_variance !== null ? $total_variance.'%' : 'N/A' ?></span>
        </td>
        <td colspan="2"></td>
      </tr>

      <?php endif; ?>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
