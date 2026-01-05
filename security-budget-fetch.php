<?php
// security-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$category = 'Security Charges';
$user_id  = isset($_SESSION['hris']) ? $_SESSION['hris'] : '';
$user_id_esc = mysqli_real_escape_string($conn, $user_id);

// ✅ Financial Year (AUTO) - Apr to Mar (Sri Lanka style)
$months = [];

$tz = new DateTimeZone('Asia/Colombo'); // change if your FY timezone differs
$now = new DateTime('now', $tz);

$fyStartMonth = 4; // April = 4  (change this if your FY starts in a different month)

// If current month is Apr or later -> FY starts this year Apr 1
// Else -> FY starts last year Apr 1
$fyStartYear = ((int)$now->format('n') >= $fyStartMonth)
    ? (int)$now->format('Y')
    : ((int)$now->format('Y') - 1);

$start = new DateTime($fyStartYear . '-' . str_pad($fyStartMonth, 2, '0', STR_PAD_LEFT) . '-01', $tz);
$end   = (clone $start)->modify('+11 months'); // Apr..Mar (12 months total)

$cursor = clone $start;
while ($cursor <= $end) {
    $months[] = $cursor->format('F Y');
    $cursor->modify('+1 month');
}


// Helper: fetch a single numeric value
function fetch_one_num($conn, $sql, $key) {
    $q = mysqli_query($conn, $sql);
    if (!$q) return 0;
    $r = mysqli_fetch_assoc($q);
    return isset($r[$key]) ? (float)$r[$key] : 0;
}

$report = [];

foreach ($months as $month) {
    $month_esc    = mysqli_real_escape_string($conn, $month);
    $category_esc = mysqli_real_escape_string($conn, $category);

    // ------------------------------------------------------------
    // Budget total (handles multiple rows per branch_code too)
    // ------------------------------------------------------------
    $budget = fetch_one_num($conn, "
        SELECT COALESCE(SUM(b.no_of_shifts * b.rate), 0) AS budget_amount
        FROM tbl_admin_budget_security b
        WHERE b.month_applicable = '{$month_esc}'
          AND b.branch NOT LIKE '%Point Close%'
    ", 'budget_amount');

    // ------------------------------------------------------------
    // Actual total = firmwise (NON-2000) + invoices (2000)
    // ------------------------------------------------------------

    // NON-2000 actual (approved), exclude active 2000 branches
    $actual_non2000 = fetch_one_num($conn, "
        SELECT COALESCE(SUM(a.total_amount), 0) AS actual_amount
        FROM tbl_admin_actual_security_firmwise a
        LEFT JOIN tbl_admin_security_2000_branches s
               ON s.branch_code = a.branch_code
              AND s.active = 'yes'
        WHERE a.month_applicable = '{$month_esc}'
          AND a.approval_status = 'approved'
          AND s.branch_code IS NULL
    ", 'actual_amount');

    // 2000 invoices actual (approved)
    $actual_2000 = fetch_one_num($conn, "
        SELECT COALESCE(SUM(i.amount), 0) AS actual_amount
        FROM tbl_admin_actual_security_2000_invoices i
        WHERE i.month_applicable = '{$month_esc}'
          AND i.approval_status = 'approved'
    ", 'actual_amount');

    $actual = $actual_non2000 + $actual_2000;

    // If no actual at all, skip this month (same behavior you had)
    if ($actual <= 0) continue;

    // ------------------------------------------------------------
    // Total branches in budget for THIS month (distinct branch_code)
    // ------------------------------------------------------------
    $total_branches = (int)fetch_one_num($conn, "
        SELECT COUNT(DISTINCT b.branch_code) AS total
        FROM tbl_admin_budget_security b
        WHERE b.month_applicable = '{$month_esc}'
          AND b.branch NOT LIKE '%Point Close%'
    ", 'total');

    // ------------------------------------------------------------
    // Completed branches = distinct branch_code with approved actual
    // (firmwise non-2000 union invoices 2000) limited to budget month
    // ------------------------------------------------------------
    $completed = (int)fetch_one_num($conn, "
        SELECT COUNT(DISTINCT x.branch_code) AS completed
        FROM (
            SELECT a.branch_code
            FROM tbl_admin_actual_security_firmwise a
            JOIN tbl_admin_budget_security b
              ON b.branch_code = a.branch_code
             AND b.month_applicable = '{$month_esc}'
             AND b.branch NOT LIKE '%Point Close%'
            LEFT JOIN tbl_admin_security_2000_branches s
              ON s.branch_code = a.branch_code
             AND s.active = 'yes'
            WHERE a.month_applicable = '{$month_esc}'
              AND a.approval_status = 'approved'
              AND s.branch_code IS NULL

            UNION

            SELECT i.branch_code
            FROM tbl_admin_actual_security_2000_invoices i
            JOIN tbl_admin_budget_security b
              ON b.branch_code = i.branch_code
             AND b.month_applicable = '{$month_esc}'
             AND b.branch NOT LIKE '%Point Close%'
            LEFT JOIN tbl_admin_security_2000_branches s
              ON s.branch_code = i.branch_code
             AND s.active = 'yes'
            WHERE i.month_applicable = '{$month_esc}'
              AND i.approval_status = 'approved'
              AND s.branch_code IS NOT NULL
        ) x
    ", 'completed');

    // ------------------------------------------------------------
    // Selection (per-user)
    // ------------------------------------------------------------
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

<?php
if (!function_exists('slugify_row')) {
  function slugify_row($s){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($s ?? '')); }
}
?>

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
        <td class="<?= $row['completed'] < $row['total_branches'] ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
          <?= (int)$row['completed'].'/'.(int)$row['total_branches'] ?> Completed
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

<hr class="my-4">

<!-- ✅ Select month to view detailed report -->
<!-- <div class="mt-2">
  <div class="d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label mb-1">Select month to view branch-wise report</label>
      <select id="security-detail-month" class="form-select form-select-sm">
        <?php foreach ($months as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-sm btn-outline-primary" id="load-security-detail">View Report</button>
  </div>

  <div id="security-detail-area" class="mt-3"></div>
</div> -->

<!-- Remarks Modal (unchanged) -->
<div class="modal fade"
     id="remarksModal"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Remarks for <span id="modalRecordLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-none" id="remarks-alert" role="alert"></div>
        <div id="remark-history" class="mb-3 border p-3 bg-light" style="max-height: 200px; overflow-y: auto;"></div>

        <label class="form-label">Send To (optional)</label>
        <select id="remark-recipients" class="form-select" multiple></select>
        <div class="form-text">Choose one or more people to notify.</div>

        <textarea id="new-remark" class="form-control mb-2" rows="3" placeholder="Enter your remark..."></textarea>
        <input type="hidden" id="remark-category">
        <input type="hidden" id="remark-record">
        <button class="btn btn-success" id="save-remark">Save Remark</button>
      </div>
    </div>
  </div>
</div>
<script>
  // ✅ Month detail loader (uses your existing security-monthly-fetch.php)
  $('#contentArea').on('click' + NS, '#load-security-detail', function(){
    const month = $('#security-detail-month').val();
    const $area = $('#security-detail-area');

    if (!month) return;

    $area.html(
      '<div class="text-center py-3">' +
        '<div class="spinner-border text-primary"></div>' +
        '<div class="small text-muted mt-2">Loading month report...</div>' +
      '</div>'
    );

    $.post('security-monthly-fetch.php', { month: month, firm_id: 0 }, function(res){
      if (res && res.table) {
        $area.html(res.table);
      } else {
        $area.html('<div class="alert alert-warning">No data returned for this month.</div>');
      }
    }, 'json').fail(function(jqXHR){
      $area.html('<div class="alert alert-danger">Failed to load month report (' + jqXHR.status + ').</div>');
    });
  });


</script>