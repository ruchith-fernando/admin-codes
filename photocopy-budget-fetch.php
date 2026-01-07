<?php
// photocopy-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$category = 'Photocopies';

$user_id  = isset($_SESSION['hris']) ? $_SESSION['hris'] : '';
$user_id_esc = mysqli_real_escape_string($conn, $user_id);

// ✅ Fixed budget per month
$monthly_budget = 750000.00;

// ✅ Financial Year (AUTO) - Apr to Mar (Sri Lanka style)
$tz = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);

$fyStartMonth = 4; // April

$fyStartYear = ((int)$now->format('n') >= $fyStartMonth)
    ? (int)$now->format('Y')
    : ((int)$now->format('Y') - 1);

$fy_from = $fyStartYear;
$fy_to   = $fyStartYear + 1;

$fy_display = "FY " . $fy_from . "-" . substr((string)$fy_to, -2);

// FY start = Apr 1, FY end = Mar 1 (start of last month), exclusive end = Apr 1
$fy_start    = new DateTime($fy_from . "-04-01", $tz);
$fy_end      = new DateTime($fy_to   . "-03-01", $tz);
$fy_end_excl = (clone $fy_end)->modify('+1 month');

// Month list with date boundaries (for date-range SUM queries)
$months = [];
$cursor = clone $fy_start;
while ($cursor <= $fy_end) {
    $monthStart = $cursor->format('Y-m-01');
    $nextStart  = (clone $cursor)->modify('+1 month')->format('Y-m-01');

    $months[] = [
        'label' => $cursor->format('F Y'),  // matches dashboard month_name format
        'start' => $monthStart,
        'next'  => $nextStart,
    ];

    $cursor->modify('+1 month');
}

// Helper: fetch a single numeric value
function fetch_one_num($conn, $sql, $key) {
    $q = mysqli_query($conn, $sql);
    if (!$q) return 0;
    $r = mysqli_fetch_assoc($q);
    return isset($r[$key]) ? (float)$r[$key] : 0;
}

if (!function_exists('slugify_row')) {
  function slugify_row($s){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($s ?? '')); }
}

// ------------------------------------------------------------
// Optional optimization: only consider months that have rows in FY
// (using date boundaries, since photocopy month_applicable is a date)
// ------------------------------------------------------------
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

$report = [];

foreach ($months as $m) {

    $monthLabel = $m['label'];

    // If there are no rows for this month in FY, skip (keeps table clean)
    if (!isset($monthsWithRows[$monthLabel])) continue;

    $monthStartEsc = mysqli_real_escape_string($conn, $m['start']);
    $monthNextEsc  = mysqli_real_escape_string($conn, $m['next']);

    // ------------------------------------------------------------
    // Actual total (date range)
    // ------------------------------------------------------------
    $actual = fetch_one_num($conn, "
        SELECT COALESCE(SUM(total_amount), 0) AS actual_amount
        FROM tbl_admin_actual_photocopy
        WHERE month_applicable >= '{$monthStartEsc}'
          AND month_applicable <  '{$monthNextEsc}'
    ", 'actual_amount');

    // If no actual at all, skip this month (same as Security behavior)
    if ($actual <= 0) continue;

    // ------------------------------------------------------------
    // Fixed budget
    // ------------------------------------------------------------
    $budget = (float)$monthly_budget;

    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    // ------------------------------------------------------------
    // Selection (per-user) - EXACTLY like Security
    // ------------------------------------------------------------
    $month_esc    = mysqli_real_escape_string($conn, $monthLabel);
    $category_esc = mysqli_real_escape_string($conn, $category);

    $selected_row = $conn->query("
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category   = '{$category_esc}'
          AND month_name = '{$month_esc}'
          AND user_id    = '{$user_id_esc}'
        LIMIT 1
    ");
    $selected_assoc = $selected_row ? $selected_row->fetch_assoc() : null;
    $selected = (($selected_assoc['is_selected'] ?? '') === 'yes') ? 'checked' : '';

    $report[] = [
        'month'      => $monthLabel,
        'budget'     => $budget,
        'actual'     => $actual,
        'difference' => $difference,
        'variance'   => $variance,
        'checked'    => $selected,
        'over_budget'=> ($budget > 0 && $actual > $budget),
    ];
}

// Totals (same style as Security)
$total_budget = 0.0;
$total_actual = 0.0;
$total_difference = 0.0;

foreach ($report as $r) {
    $total_budget     += (float)$r['budget'];
    $total_actual     += (float)$r['actual'];
    $total_difference += (float)$r['difference'];
}
$total_variance = ($total_budget > 0) ? round(($total_difference / $total_budget) * 100) : null;
?>

<style>
.table .form-switch { padding-left: 0; min-height: 0; }
.form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
.toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
.wide-table { min-width: 980px; }

.photocopy-summary-table tbody tr.over-budget-row > * { background-color: #ffecec !important; }

.report-row.row-focus{
  background:#fff8d1 !important;
  animation:rowPulse 1.8s ease-out 0s 2;
}
@keyframes rowPulse{
  0%{ box-shadow:0 0 0 0 rgba(255,193,7,.55); }
  70%{ box-shadow:0 0 0 10px rgba(255,193,7,0); }
  100%{ box-shadow:0 0 0 0 rgba(255,193,7,0); }
}
</style>

<div class="mb-2">
  <h5 class="text-primary fw-bold mb-4"><?= htmlspecialchars($fy_display) ?> Photocopies Budget Summary</h5>
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
      <?php $i = 1; foreach ($report as $row): ?>
      <?php
        $trClass = "report-row";
        if (!empty($row['over_budget'])) $trClass .= " over-budget-row";
        $switchId = 'month_switch_' . str_replace(' ', '_', $row['month']); // same pattern as Security
      ?>
      <tr
        class="<?= $trClass ?>"
        data-category="<?= htmlspecialchars($category) ?>"
        data-record="<?= htmlspecialchars($row['month']) ?>"
        id="row-<?= slugify_row($category.'-'.$row['month']) ?>"
      >
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
            <div class="form-check form-switch m-0">
              <input
                type="checkbox"
                class="form-check-input month-checkbox"
                role="switch"
                id="<?= htmlspecialchars($switchId) ?>"
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
        <td><?= number_format($total_budget, 2) ?></td>
        <td><?= number_format($total_actual, 2) ?></td>
        <td class="<?= $total_difference < 0 ? 'text-danger' : '' ?>">
          <?= number_format($total_difference, 2) ?>
        </td>
        <td></td>
        <td></td>
      </tr>

      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td class="<?= ($total_variance !== null && $total_variance < 0) ? 'text-danger' : '' ?>">
          <?= $total_variance !== null ? $total_variance.'%' : 'N/A' ?>
        </td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
