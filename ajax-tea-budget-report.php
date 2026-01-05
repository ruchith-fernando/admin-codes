<?php
// ajax-tea-budget-report.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'connections/connection.php';

$category = 'Tea Service - Head Office';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = $_SESSION['hris'] ?? '';
$user_id_esc = mysqli_real_escape_string($conn, (string)$user_id);

// ✅ OT floor ID (hardcoded)
$ot_floor_id = 12;

// ✅ Total active floors EXCLUDING OT (for completion status denominator)
$floorRow = $conn->query("
    SELECT COUNT(*) AS c
    FROM tbl_admin_floors
    WHERE is_active=1
      AND id <> $ot_floor_id
")->fetch_assoc();
$total_floors = (int)($floorRow['c'] ?? 0);

// ✅ Financial Year Months (2025 Apr -> 2026 Mar)
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

$report = [];
$total_budget_init = 0; $total_actual_init = 0; $total_difference_init = 0;

foreach ($months as $month) {

    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    /**
     * ✅ Actuals:
     * - actual_amount: SUM of ALL approved (including OT if present)
     * - approved_floors_all: used only to decide whether to show the month
     * - approved_floors_no_ot: used for completion numerator (excludes OT)
     */
    $approved_row = $conn->query("
        SELECT
            COALESCE(SUM(grand_total),0) AS actual_amount,
            COUNT(DISTINCT floor_id)     AS approved_floors_all,
            COUNT(DISTINCT CASE WHEN floor_id <> $ot_floor_id THEN floor_id END) AS approved_floors_no_ot
        FROM tbl_admin_tea_service_hdr
        WHERE month_year = '$month_esc'
          AND approval_status = 'approved'
    ")->fetch_assoc();

    $actual            = (float)($approved_row['actual_amount'] ?? 0);
    $approvedFloorsAll = (int)($approved_row['approved_floors_all'] ?? 0);
    $approvedFloors    = (int)($approved_row['approved_floors_no_ot'] ?? 0);

    // ✅ Show only months that have ANY approved records (even if OT-only)
    if ($approvedFloorsAll <= 0) continue;

    // ✅ Budget
    $budget_row = $conn->query("
        SELECT COALESCE(SUM(budget_amount),0) AS budget_amount
        FROM tbl_admin_budget_tea_service
        WHERE month_year = '$month_esc'
    ")->fetch_assoc();
    $budget = (float)($budget_row['budget_amount'] ?? 0);

    // ✅ Selected in dashboard — PER USER (HRIS)
    $sel_sql = "
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category='$category_esc'
          AND month_name='$month_esc'
    ";
    if ($user_id_esc !== '') {
        $sel_sql .= " AND user_id='$user_id_esc' ";
    }
    $sel_sql .= " LIMIT 1";
    $selected_row = $conn->query($sel_sql)->fetch_assoc();
    $selected = ($selected_row['is_selected'] ?? '') === 'yes' ? 'checked' : '';

    // ✅ Variance
    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    // ✅ Completion status (excludes OT)
    $completionText = ($total_floors > 0)
        ? ($approvedFloors . " / " . $total_floors . " Floors")
        : "N/A";

    $report[] = [
        'month'          => $month,
        'budget'         => $budget,
        'actual'         => $actual,
        'difference'     => $difference,
        'variance'       => $variance,
        'checked'        => $selected,
        'approvedFloors' => $approvedFloors,
        'totalFloors'    => $total_floors,
        'completionText' => $completionText
    ];

    // Footer totals based on pre-checked rows
    if ($selected) {
        $total_budget_init     += $budget;
        $total_actual_init     += $actual;
        $total_difference_init += $difference;
    }
}

if (!function_exists('slugify_row')) {
    function slugify_row($s){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($s ?? '')); }
}

$total_variance_init = ($total_budget_init > 0) ? round(($total_difference_init / $total_budget_init) * 100) : null;
?>
<style>
#report-content .table .form-switch { padding-left: 0; min-height: 0; }
#report-content .form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
#report-content .toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }

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
        <th>Actual Amount (Rs) <small class="text-muted d-block">(Approved only)</small></th>
        <th>Difference (Rs)</th>
        <th>Variance (%)</th>
        <th>Completion Status</th>
        <th>Select / Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($report as $row): ?>
      <tr
        class="report-row"
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

        <!-- ✅ Completion Status (OT excluded) -->
        <td><?= htmlspecialchars($row['completionText']) ?></td>

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
        <td><?= number_format($total_budget_init, 2) ?></td>
        <td><?= number_format($total_actual_init, 2) ?></td>
        <td class="<?= $total_difference_init < 0 ? 'text-danger' : '' ?>">
          <?= number_format($total_difference_init, 2) ?>
        </td>
        <td></td>
        <td colspan="2"></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="5" class="text-end">Total Variance (%)</td>
        <td class="<?= ($total_variance_init !== null && $total_variance_init < 0) ? 'text-danger' : '' ?>">
          <?= $total_variance_init !== null ? $total_variance_init.'%' : 'N/A' ?>
        </td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
