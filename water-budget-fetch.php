<?php
// water-budget-fetch.php
include 'nocache.php';
include 'connections/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = isset($_SESSION['hris']) ? mysqli_real_escape_string($conn, $_SESSION['hris']) : '';

$category = 'Water';

/* ---------------- FY detection ---------------- */
$today = new DateTime();
$year  = (int)$today->format('Y');
$monthNum = (int)$today->format('m');

if ($monthNum >= 4) {
    $fy_start   = new DateTime("$year-04-01");
    $fy_end     = new DateTime(($year + 1) . "-03-01");
    $fy_label   = $year;
    $fy_display = "FY " . $year . "-" . substr($year + 1, -2);
} else {
    $fy_start   = new DateTime(($year - 1) . "-04-01");
    $fy_end     = new DateTime("$year-03-01");
    $fy_label   = $year - 1;
    $fy_display = "FY " . ($year - 1) . "-" . substr($year, -2);
}

/* ---------------- Month list ---------------- */
$months = [];
$start = clone $fy_start;
while ($start <= $fy_end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

/* ---------------- helpers ---------------- */
function fetchAssocOrEmpty($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return [];
    $row = $res->fetch_assoc();
    return $row ? $row : [];
}
function parseAmount($raw) {
    $t = trim((string)$raw);
    if ($t === '') return null;
    $clean = str_replace(',', '', $t);
    if (!is_numeric($clean)) return null;
    return (float)$clean;
}

/* =========================================================
   0) Months that have ANY actual rows (approved/pending/rejected)
========================================================= */
$monthsWithRows = [];
$resM = $conn->query("
    SELECT DISTINCT month_applicable
    FROM tbl_admin_actual_water
    WHERE month_applicable IS NOT NULL
      AND TRIM(month_applicable) <> ''
      AND approval_status IN ('approved','pending','rejected')
");
if ($resM) {
    while ($r = $resM->fetch_assoc()) {
        $monthsWithRows[trim($r['month_applicable'])] = true;
    }
}

/* =========================================================
   1) Master mapping (required connections per branch)
========================================================= */
$master = [];

$map_sql = "
    SELECT
        bw.branch_code,
        bw.water_type_id,
        bw.connection_no
    FROM tbl_admin_branch_water bw
    INNER JOIN tbl_admin_water_types wt
        ON wt.water_type_id = bw.water_type_id
    WHERE wt.is_active = 1
    ORDER BY bw.branch_code, bw.water_type_id, bw.connection_no
";
$map_res = $conn->query($map_sql);
if ($map_res) {
    while ($r = $map_res->fetch_assoc()) {
        $code = $r['branch_code'];
        $tid  = (int)$r['water_type_id'];
        $cno  = (int)($r['connection_no'] ?? 1);
        if ($cno <= 0) $cno = 1;

        if (!isset($master[$code])) {
            $master[$code] = ['required_keys' => []];
        }
        $master[$code]['required_keys'][] = $tid . '|' . $cno;
    }
}
foreach ($master as $code => $m) {
    $master[$code]['required_keys']  = array_values(array_unique($m['required_keys']));
    $master[$code]['required_count'] = count($master[$code]['required_keys']);
}

/* =========================================================
   2) Denominator branches: budget > 0 in FY AND in master mapping
========================================================= */
$budgetBranches = [];
$resBB = $conn->query("
    SELECT DISTINCT branch_code
    FROM tbl_admin_budget_water
    WHERE budget_year = '" . mysqli_real_escape_string($conn, (string)$fy_label) . "'
      AND amount IS NOT NULL
      AND amount > 0
");
if ($resBB) {
    while ($r = $resBB->fetch_assoc()) {
        $budgetBranches[$r['branch_code']] = true;
    }
}

$eligibleBranches = [];
foreach ($master as $code => $m) {
    if (isset($budgetBranches[$code])) {
        $eligibleBranches[$code] = $m;
    }
}
$total_branches = count($eligibleBranches);

/* ---------------- yearly budget cached ---------------- */
$yearly_budget = 0.0;
$budget_row = $conn->query("
    SELECT SUM(amount) AS budget_amount
    FROM tbl_admin_budget_water
    WHERE budget_year = '" . mysqli_real_escape_string($conn, (string)$fy_label) . "'
");
if ($budget_row && $b = $budget_row->fetch_assoc()) {
    $yearly_budget = (float)($b['budget_amount'] ?? 0);
}

/* =========================================================
   3) Month loop: actual = sum ONLY fully complete + approved branches
   ✅ If actual == 0 => skip month (your new requirement)
========================================================= */
$report = [];

foreach ($months as $mName) {

    if (!isset($monthsWithRows[$mName])) {
        continue;
    }

    $month_esc = mysqli_real_escape_string($conn, $mName);

    // actual rows map for this month (only eligible branches)
    $actualMap = [];
    $resA = $conn->query("
        SELECT branch_code, water_type_id, connection_no, approval_status, total_amount
        FROM tbl_admin_actual_water
        WHERE month_applicable = '{$month_esc}'
    ");
    if ($resA) {
        while ($a = $resA->fetch_assoc()) {
            $code = $a['branch_code'];
            if (!isset($eligibleBranches[$code])) continue;

            $tid = (int)($a['water_type_id'] ?? 0);
            $cno = (int)($a['connection_no'] ?? 1);
            if ($cno <= 0) $cno = 1;
            $key = $tid . '|' . $cno;

            $st  = strtolower(trim($a['approval_status'] ?? ''));
            $amt = parseAmount($a['total_amount'] ?? '');

            if (!isset($actualMap[$code])) $actualMap[$code] = [];
            $actualMap[$code][$key] = [
                'status' => $st,
                'amount' => $amt
            ];
        }
    }

    $completed = 0;
    $actual_sum_completed = 0.0;

    foreach ($eligibleBranches as $code => $mdata) {

        $required = $mdata['required_keys'] ?? [];
        $reqCount = (int)($mdata['required_count'] ?? 0);

        $pendingCount = 0;
        $missingCount = 0;
        $approvedOk   = 0;
        $sumApproved  = 0.0;

        foreach ($required as $reqKey) {

            $row = $actualMap[$code][$reqKey] ?? null;

            if (!$row) {
                $missingCount++;
                continue;
            }

            $st = $row['status'] ?? '';

            if ($st === 'deleted') {
                $missingCount++;
                continue;
            }

            if ($st === 'pending') {
                $pendingCount++;
                continue;
            }

            if ($st === 'approved') {
                $am = $row['amount'];
                if ($am !== null && $am > 0) {
                    $approvedOk++;
                    $sumApproved += $am;
                } else {
                    $missingCount++;
                }
                continue;
            }

            $missingCount++;
        }

        $isFullyApproved = ($reqCount > 0 && $approvedOk === $reqCount && $pendingCount === 0 && $missingCount === 0);

        if ($isFullyApproved) {
            $completed++;
            $actual_sum_completed += $sumApproved;
        }
    }

    $budget = $yearly_budget;
    $actual = (float)$actual_sum_completed;

    // ✅ skip month if actual is 0.00
    if ($actual <= 0) {
        continue;
    }

    $difference = $budget - $actual;
    $variance   = ($budget > 0) ? round(($difference / $budget) * 100) : null;

    // checkbox selection state
    $sel_row = fetchAssocOrEmpty($conn, "
        SELECT is_selected
        FROM tbl_admin_dashboard_month_selection
        WHERE category='" . mysqli_real_escape_string($conn, $category) . "'
          AND month_name='{$month_esc}'
          AND user_id='{$user_id}'
        LIMIT 1
    ");
    $selected = (($sel_row['is_selected'] ?? '') === 'yes') ? 'checked' : '';

    $report[] = [
        'month'          => $mName,
        'budget'         => $budget,
        'actual'         => $actual,
        'difference'     => $difference,
        'variance'       => $variance,
        'completed'      => $completed,
        'total_branches' => $total_branches,
        'checked'        => $selected,
        'over_budget'    => ($budget > 0 && $actual > $budget),
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

/* ✅ highlight month rows when Actual > Budget (Bootstrap stripes color TD, so target cells) */
.water-summary-table tbody tr.over-budget-row > * {
  background-color: #ffecec !important;
}
</style>

<div class="mb-2">
  <h5 class="text-primary fw-bold mb-4"><?= htmlspecialchars($fy_display) ?> Water Budget Summary</h5>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm text-center wide-table water-summary-table">
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

        $isFull = ((int)$row['total_branches'] > 0 && (int)$row['completed'] === (int)$row['total_branches']);
        $compClass = $isFull ? 'text-success fw-bold' : 'text-danger fw-bold';
        $completionText = "{$row['completed']} / {$row['total_branches']} completed";

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
        <td class="<?= $compClass ?>"><?= $completionText ?></td>
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
        <td colspan="3"></td>
      </tr>
      <tr class="fw-bold table-light">
        <td colspan="2">Overall Variance</td>
        <td colspan="6" class="<?= ($overall_variance !== null && $overall_variance < 0) ? 'text-danger' : '' ?>">
          <?= $overall_variance !== null ? $overall_variance.'%' : 'N/A' ?>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
