<?php
// photocopy-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = isset($_SESSION['hris']) ? mysqli_real_escape_string($conn, $_SESSION['hris']) : '';

$category = 'Photocopies';

// ✅ Fixed budget per month
$monthly_budget = 750000.00;

/* ---------------- FY detection (Apr -> Mar) ---------------- */
$today = new DateTime();
$year  = (int)$today->format('Y');
$monthNum = (int)$today->format('m');

if ($monthNum >= 4) {
    $fy_from = $year;
    $fy_to   = $year + 1;
} else {
    $fy_from = $year - 1;
    $fy_to   = $year;
}

$fy_start    = new DateTime($fy_from . "-04-01");
$fy_end      = new DateTime($fy_to   . "-03-01");       // last month start in FY (March)
$fy_end_excl = (clone $fy_end)->modify('+1 month');     // exclusive end boundary

$fy_budget_year = $fy_from . "-04_to_" . $fy_to . "-03";
$fy_display     = "FY " . $fy_from . "-" . substr($fy_to, -2);

/* ---------------- Month list ---------------- */
$months = [];
$cursor = clone $fy_start;
while ($cursor <= $fy_end) {
    $monthStart = $cursor->format('Y-m-01');
    $nextStart  = (clone $cursor)->modify('+1 month')->format('Y-m-01');

    $months[] = [
        'start' => $monthStart,
        'next'  => $nextStart,
        'label' => $cursor->format('F Y'),
    ];

    $cursor->modify('+1 month');
}

/* ---------------- helpers ---------------- */
function fetchAssocOrEmpty($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return [];
    $row = $res->fetch_assoc();
    return $row ? $row : [];
}

/* =========================================================
   Months that have ANY actual rows in FY
========================================================= */
$fyStartSql   = mysqli_real_escape_string($conn, $fy_start->format('Y-m-01'));
$fyEndExclSql = mysqli_real_escape_string($conn, $fy_end_excl->format('Y-m-01'));

$monthsWithRows = [];
$resM = $conn->query("
    SELECT DISTINCT DATE_FORMAT(month_applicable, '%M %Y') AS month_label
    FROM tbl_admin_actual_photocopy
    WHERE month_applicable >= '{$fyStartSql}'
      AND month_applicable <  '{$fyEndExclSql}'
");
if ($resM) {
    while ($r = $resM->fetch_assoc()) {
        $monthsWithRows[trim($r['month_label'])] = true;
    }
}

/* =========================================================
   Month loop: fixed monthly budget vs monthly actual
   ✅ Skip month if actual == 0
========================================================= */
$report = [];

foreach ($months as $m) {

    $monthLabel = $m['label'];
    if (!isset($monthsWithRows[$monthLabel])) continue;

    $monthStartEsc = mysqli_real_escape_string($conn, $m['start']);
    $monthNextEsc  = mysqli_real_escape_string($conn, $m['next']);

    /* ---------------- Monthly Actual ---------------- */
    $actual = 0.0;
    $aSumRes = $conn->query("
        SELECT SUM(total_amount) AS actual_amount
        FROM tbl_admin_actual_photocopy
        WHERE month_applicable >= '{$monthStartEsc}'
          AND month_applicable <  '{$monthNextEsc}'
    ");
    if ($aSumRes && $a = $aSumRes->fetch_assoc()) {
        $actual = (float)($a['actual_amount'] ?? 0);
    }

    // ✅ Skip month if actual is 0.00
    if ($actual <= 0) continue;

    /* ---------------- Fixed Budget ---------------- */
    $budget = (float)$monthly_budget;

    /* ---------------- Variance ---------------- */
    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    /* ---------------- checkbox selection state ---------------- */
    $monthLabelEsc = mysqli_real_escape_string($conn, $monthLabel);
    $sel_row = fetchAssocOrEmpty($conn, "
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category='" . mysqli_real_escape_string($conn, $category) . "'
          AND month_name='{$monthLabelEsc}'
          AND user_id='{$user_id}'
        LIMIT 1
    ");
    $selected = (($sel_row['is_selected'] ?? '') === 'yes') ? 'checked' : '';

    $report[] = [
        'month'       => $monthLabel,
        'budget'      => $budget,
        'actual'      => $actual,
        'difference'  => $difference,
        'variance'    => $variance,
        'checked'     => $selected,
        'over_budget' => ($budget > 0 && $actual > $budget),
    ];
}

/* =========================================================
   ✅ Totals (calculated once, always works)
========================================================= */
$month_count = count($report);

$total_budget = $month_count * (float)$monthly_budget;

$total_actual = 0.0;
foreach ($report as $r) {
    $total_actual += (float)($r['actual'] ?? 0);
}

$total_difference = $total_budget - $total_actual;
$overall_variance = ($total_budget > 0) ? round(($total_difference / $total_budget) * 100) : null;
?>

<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
.wide-table { min-width: 980px; }
.photocopy-summary-table tbody tr.over-budget-row > * { background-color: #ffecec !important; }
</style>

<div class="mb-2">
  <h5 class="text-primary fw-bold mb-4"><?= htmlspecialchars($fy_display) ?> Photocopies Budget Summary</h5>
  <!-- <div class="text-muted small">Budget Year Key: <strong><?= htmlspecialchars($fy_budget_year) ?></strong></div>
  <div class="text-muted small">Fixed Monthly Budget: <strong>Rs <?= number_format($monthly_budget, 2) ?></strong></div> -->
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm text-center wide-table photocopy-summary-table">
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
      foreach ($report as $row):
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
