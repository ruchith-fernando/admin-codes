<?php
// printing-monthly-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json; charset=UTF-8');

$month = trim($_POST['month'] ?? '');
if ($month === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

// In this module, tbl_admin_budget_printing.budget_year stores values like "April 2025"
$budget_key = $month;

/* -----------------------
   Actual data
------------------------ */
$actuals = [];
$provisions = [];

$actual_sql = "SELECT branch_code, branch, total_amount, is_provision, provision_reason
    FROM tbl_admin_actual_printing
    WHERE month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'";
$actual_res = mysqli_query($conn, $actual_sql);

if ($actual_res) {
    while ($row = mysqli_fetch_assoc($actual_res)) {
        $actuals[$row['branch_code']] = $row;

        if (strtolower(trim($row['is_provision'] ?? 'no')) === 'yes') {
            $provisions[] = ($row['branch'] ?? '') . " (" . ($row['branch_code'] ?? '') . ")";
        }
    }
}

/* -----------------------
   Budget data (match month text)
------------------------ */
$budget = [];

$budget_sql = "SELECT branch_code, branch_name, amount AS monthly_amount
    FROM tbl_admin_budget_printing
    WHERE budget_year = '" . mysqli_real_escape_string($conn, $budget_key) . "'";
$budget_res = mysqli_query($conn, $budget_sql);

if ($budget_res) {
    while ($row = mysqli_fetch_assoc($budget_res)) {
        $budget[$row['branch_code']] = $row;
    }
}

/* -----------------------
   Master branch list
------------------------ */
$master = [];

$branch_sql = "SELECT branch_code, branch_name FROM tbl_admin_branch_printing";
$branch_res = mysqli_query($conn, $branch_sql);

if ($branch_res) {
    while ($row = mysqli_fetch_assoc($branch_res)) {
        $master[$row['branch_code']] = $row['branch_name'];
    }
}

/* -----------------------
   Merge all branch codes
------------------------ */
$branches = array_unique(array_merge(
    array_keys($master),
    array_keys($budget),
    array_keys($actuals)
));
sort($branches);

/* -----------------------
   Build report table HTML + totals row
   - highlight rows where Actual > Budget
   - show negative variance in parentheses
------------------------ */

function fmt_money($n) {
    return number_format((float)$n, 2);
}

// Variance shown as (x.xx) when Actual > Budget, else normal positive number
function fmt_variance($budget, $actual) {
    $budget = (float)$budget;
    $actual = (float)$actual;

    if ($actual > $budget) {
        $diff = $actual - $budget;
        return "<span class='text-danger fw-bold'>(" . fmt_money($diff) . ")</span>";
    }
    $diff = $budget - $actual; // remaining budget
    return fmt_money($diff);
}

$table_html = '';
$total_budget = 0.0;
$total_actual = 0.0;

// light red row style
$table_css = "
<style>
  .printing-report-table tr.over-budget-row > * {
    background-color: #ffecec !important; /* light red */
  }
</style>
";

if (!empty($branches)) {

    $table_html .= $table_css;
    $table_html .= "<table class='table table-bordered table-striped printing-report-table'>";
    $table_html .= "<thead class='table-light'>
        <tr>
            <th>Branch Code</th>
            <th>Branch Name</th>
            <th class='text-end'>Budget (Monthly)</th>
            <th class='text-end'>Actual</th>
            <th class='text-end'>Variance</th>
            <th>Provision?</th>
        </tr>
    </thead><tbody>";

    foreach ($branches as $code) {

        $branch_name = $master[$code]
            ?? ($budget[$code]['branch_name'] ?? ($actuals[$code]['branch'] ?? '-'));

        $b_amt   = isset($budget[$code])  ? (float)($budget[$code]['monthly_amount'] ?? 0) : 0;
        $a_amt   = isset($actuals[$code]) ? (float)($actuals[$code]['total_amount'] ?? 0) : 0;
        $is_prov = isset($actuals[$code]) ? ($actuals[$code]['is_provision'] ?? 'no') : 'no';

        $total_budget += $b_amt;
        $total_actual += $a_amt;

        $overBudget = ($a_amt > $b_amt);
        $rowClass   = $overBudget ? "over-budget-row" : "";

        $table_html .= "<tr class='{$rowClass}'>";
        $table_html .= "<td>" . htmlspecialchars($code) . "</td>";
        $table_html .= "<td>" . htmlspecialchars($branch_name) . "</td>";
        $table_html .= "<td class='text-end'>" . fmt_money($b_amt) . "</td>";
        $table_html .= "<td class='text-end'>" . fmt_money($a_amt) . "</td>";
        $table_html .= "<td class='text-end'>" . fmt_variance($b_amt, $a_amt) . "</td>";
        $table_html .= "<td>" . (strtolower($is_prov) === 'yes' ? 'Yes' : 'No') . "</td>";
        $table_html .= "</tr>";
    }

    // totals row (same variance style)
    $table_html .= "
        <tr class='table-secondary fw-bold'>
            <td colspan='2'>Total</td>
            <td class='text-end'>" . fmt_money($total_budget) . "</td>
            <td class='text-end'>" . fmt_money($total_actual) . "</td>
            <td class='text-end'>" . fmt_variance($total_budget, $total_actual) . "</td>
            <td></td>
        </tr>
    ";

    $table_html .= "</tbody></table>";
}

/* -----------------------
   Missing branches list
------------------------ */
$missing = [];

foreach ($master as $code => $bname) {
    if (!isset($actuals[$code]) || (float)($actuals[$code]['total_amount'] ?? 0) <= 0) {
        $missing[] = $bname . " (" . $code . ")";
    }
}

echo json_encode([
    'table'      => $table_html,
    'missing'    => $missing,
    'provisions' => $provisions
]);
exit;
