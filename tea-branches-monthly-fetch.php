<?php
// tea-branches-monthly-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = isset($_POST['month']) ? trim($_POST['month']) : '';
if ($month === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

$monthEsc = mysqli_real_escape_string($conn, $month);
$budget_year = date("Y", strtotime("1 " . $month));

/* =========================
   CSS (make table tighter)
========================= */
$css = "
<style>
  .tea-report-table { table-layout: fixed; width: 100%; }
  .tea-report-table th, .tea-report-table td { padding: .45rem .55rem; font-size: .92rem; }
  .tea-report-table th { white-space: nowrap; }

  .tea-report-table .col-code   { width: 90px; }
  .tea-report-table .col-branch { width: 260px; white-space: normal !important; word-break: break-word; }
  .tea-report-table .col-money  { width: 160px; }
  .tea-report-table td.text-end { white-space: nowrap; }

  /* highlight over-budget ONLY when approved actual exists */
  .tea-report-table tbody tr.over-budget-row > * {
    background-color: #ffecec !important;
  }
</style>
";

/* =========================
   ACTUALS (include approval_status)
========================= */
$actuals = [];   // [branch_code] => row
$pending = [];   // list: Branch (Code)
$provisions = []; // provisional list

$actual_sql = "
    SELECT branch_code, branch, total_amount, approval_status, is_provision, provision_reason
    FROM tbl_admin_actual_tea_branches
    WHERE month_applicable = '{$monthEsc}'
";
$actual_res = mysqli_query($conn, $actual_sql);

if ($actual_res) {
    while ($row = mysqli_fetch_assoc($actual_res)) {

        $code = trim((string)$row['branch_code']);
        if ($code === '') continue;

        $actuals[$code] = $row;

        $st = strtolower(trim((string)($row['approval_status'] ?? 'pending')));
        if ($st === 'pending') {
            $pending[] = ($row['branch'] ?? '-') . " (" . $code . ")";
        }

        if (strtolower(trim((string)($row['is_provision'] ?? 'no'))) === 'yes') {
            $provisions[] = ($row['branch'] ?? '-') . " (" . $code . ")";
        }
    }
}

/* =========================
   BUDGET (monthly per branch)
========================= */
$budget = []; // [branch_code] => monthly_amount
$budget_sql = "
    SELECT branch_code, (amount) AS monthly_amount
    FROM tbl_admin_budget_tea_branches
    WHERE budget_year = '" . mysqli_real_escape_string($conn, $budget_year) . "'
";
$budget_res = mysqli_query($conn, $budget_sql);
if ($budget_res) {
    while ($row = mysqli_fetch_assoc($budget_res)) {
        $budget[trim((string)$row['branch_code'])] = (float)($row['monthly_amount'] ?? 0);
    }
}

/* =========================
   MASTER BRANCH LIST
========================= */
$master = []; // [branch_code] => branch_name
$branch_sql = "SELECT branch_code, branch_name FROM tbl_admin_branch_tea_branches";
$branch_res = mysqli_query($conn, $branch_sql);
if ($branch_res) {
    while ($row = mysqli_fetch_assoc($branch_res)) {
        $master[trim((string)$row['branch_code'])] = $row['branch_name'];
    }
}

/* =========================
   MERGE BRANCHES (master + budget + actual)
========================= */
$branches = array_unique(array_merge(
    array_keys($master),
    array_keys($budget),
    array_keys($actuals)
));
sort($branches);

/* =========================
   BUILD TABLE + TOTALS
   - Budget total: all rows shown
   - Actual total: approved only
   - Variance total: approved only
========================= */
$table_html = $css;
$table_html .= "<table class='table table-bordered tea-report-table'>";
$table_html .= "<thead class='table-light'>
<tr>
  <th class='col-code'>Branch Code</th>
  <th class='col-branch'>Branch Name</th>
  <th class='col-money text-end'>Budget (Monthly)</th>
  <th class='col-money text-end'>Actual (Approved / Pending)</th>
  <th class='col-money text-end'>Variance</th>
</tr>
</thead><tbody>";

$total_budget_all     = 0.0;
$total_actual_approved= 0.0;
$total_variance_approved = 0.0;

foreach ($branches as $code) {

    $branch_name = $master[$code]
        ?? ($actuals[$code]['branch'] ?? '-');

    $b_amt = (float)($budget[$code] ?? 0);
    $total_budget_all += $b_amt;

    // actual status + amount
    $rowClass = "";
    $actualCell = "<span class='text-muted'>-</span>";
    $varianceCell = "<span class='text-muted'>-</span>";

    $st = '';
    $a_amt = null;

    if (isset($actuals[$code])) {
        $st = strtolower(trim((string)($actuals[$code]['approval_status'] ?? 'pending')));

        $rawAmt = trim((string)($actuals[$code]['total_amount'] ?? ''));
        $clean  = str_replace(',', '', $rawAmt);
        if ($clean !== '' && is_numeric($clean)) $a_amt = (float)$clean;

        if ($st === 'approved' && $a_amt !== null) {

            $actualCell = "<b>" . number_format($a_amt, 2) . "</b>";

            $variance = $a_amt - $b_amt;
            $varianceCell = number_format($variance, 2);

            // totals (approved only)
            $total_actual_approved += $a_amt;
            $total_variance_approved += $variance;

            if ($a_amt > $b_amt) $rowClass = "over-budget-row";

        } elseif ($st === 'pending') {
            $actualCell = "<span class='text-danger fw-bold'>Pending</span>";
        } elseif ($st === 'rejected') {
            $actualCell = "<span class='text-danger fw-bold'>Rejected</span>";
        } elseif ($st === 'deleted') {
            $actualCell = "<span class='text-muted'>Deleted</span>";
        }
    }

    $table_html .= "<tr class='{$rowClass}'>";
    $table_html .= "<td>" . htmlspecialchars($code) . "</td>";
    $table_html .= "<td class='col-branch'>" . htmlspecialchars($branch_name) . "</td>";
    $table_html .= "<td class='text-end'>" . number_format($b_amt, 2) . "</td>";
    $table_html .= "<td class='text-end'>{$actualCell}</td>";
    $table_html .= "<td class='text-end'>{$varianceCell}</td>";
    $table_html .= "</tr>";
}

/* TOTAL ROW (always) */
$table_html .= "
<tr class='table-secondary fw-bold'>
  <td colspan='2'>Total</td>
  <td class='text-end'>" . number_format($total_budget_all, 2) . "</td>
  <td class='text-end'>" . number_format($total_actual_approved, 2) . "</td>
  <td class='text-end'>" . number_format($total_variance_approved, 2) . "</td>
</tr>
";

$table_html .= "</tbody></table>";

/* =========================
   MISSING list (same as before)
   - missing = not approved actual (no row OR pending/rejected/deleted OR amount <=0)
========================= */
$missing = [];
foreach ($master as $code => $bname) {

    if (!isset($actuals[$code])) {
        $missing[] = $bname . " (" . $code . ")";
        continue;
    }

    $st = strtolower(trim((string)($actuals[$code]['approval_status'] ?? 'pending')));

    $rawAmt = trim((string)($actuals[$code]['total_amount'] ?? ''));
    $clean  = str_replace(',', '', $rawAmt);
    $amtNum = (is_numeric($clean) ? (float)$clean : 0);

    // treat anything not approved with amount>0 as missing for reporting completeness
    if ($st !== 'approved' || $amtNum <= 0) {
        $missing[] = $bname . " (" . $code . ")";
    }
}

echo json_encode([
    'table'       => $table_html,
    'missing'     => $missing,
    'pending'     => $pending,
    'pending_count' => count(array_unique($pending)),
    'provisions'  => $provisions
]);
