<?php
// printing-monthly-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = isset($_POST['month']) ? trim($_POST['month']) : '';
if ($month === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

// Extract budget year from selected month (e.g. "June 2025" → 2025)
$budget_year = date("Y", strtotime("1 " . $month));

// --- Actual Data ---
$actual_sql = "
    SELECT branch_code, branch, total_amount, is_provision, provision_reason
    FROM tbl_admin_actual_printing
    WHERE month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
";
$actual_res = mysqli_query($conn, $actual_sql);
$actuals = [];
$provisions = [];
while ($row = mysqli_fetch_assoc($actual_res)) {
    $actuals[$row['branch_code']] = $row;
    if (strtolower($row['is_provision']) === 'yes') {
        $provisions[] = $row['branch'] . " (" . $row['branch_code'] . ")";
    }
}

// --- Budget Data (auto convert yearly → monthly) ---
$budget_sql = "
    SELECT branch_code, branch_name, (amount) AS monthly_amount
    FROM tbl_admin_budget_printing
    WHERE budget_year = '" . mysqli_real_escape_string($conn, $budget_year) . "'
";
$budget_res = mysqli_query($conn, $budget_sql);
$budget = [];
while ($row = mysqli_fetch_assoc($budget_res)) {
    $budget[$row['branch_code']] = $row;
}

// --- Master Branch List ---
$branch_sql = "SELECT branch_code, branch_name FROM tbl_admin_branch_printing";
$branch_res = mysqli_query($conn, $branch_sql);
$master = [];
while ($row = mysqli_fetch_assoc($branch_res)) {
    $master[$row['branch_code']] = $row['branch_name'];
}

// --- Merge branch codes from ALL sources ---
$branches = array_unique(array_merge(
    array_keys($master),
    array_keys($budget),
    array_keys($actuals)
));
sort($branches);

// --- Build Table ---
$table_html = '';
if (!empty($branches)) {
    $table_html .= "<table class='table table-bordered table-striped'>";
    $table_html .= "<thead class='table-light'>
        <tr>
            <th>Branch Code</th>
            <th>Branch Name</th>
            <th>Budget (Monthly)</th>
            <th>Actual</th>
            <th>Variance</th>
            <th>Provision?</th>
        </tr>
    </thead><tbody>";

    foreach ($branches as $code) {
        $branch_name = $master[$code] 
            ?? ($budget[$code]['branch_name'] ?? ($actuals[$code]['branch'] ?? '-'));

        $b_amt = isset($budget[$code]) ? (float)$budget[$code]['monthly_amount'] : 0;
        $a_amt = isset($actuals[$code]) ? (float)$actuals[$code]['total_amount'] : 0;
        $is_prov = isset($actuals[$code]) ? $actuals[$code]['is_provision'] : 'no';

        $variance = $a_amt - $b_amt;

        $table_html .= "<tr>";
        $table_html .= "<td>".htmlspecialchars($code)."</td>";
        $table_html .= "<td>".htmlspecialchars($branch_name)."</td>";
        $table_html .= "<td class='text-end'>".number_format($b_amt,2)."</td>";
        $table_html .= "<td class='text-end'>".number_format($a_amt,2)."</td>";
        $table_html .= "<td class='text-end'>".number_format($variance,2)."</td>";
        $table_html .= "<td>".(strtolower($is_prov)==='yes' ? 'Yes' : 'No')."</td>";
        $table_html .= "</tr>";
    }

    $table_html .= "</tbody></table>";
}

// --- Find Missing Branches ---
$missing = [];
foreach ($master as $code => $bname) {
    if (!isset($actuals[$code]) || (float)$actuals[$code]['total_amount'] <= 0) {
        $missing[] = $bname . " (" . $code . ")";
    }
}

echo json_encode([
    'table' => $table_html,
    'missing' => $missing,
    'provisions' => $provisions
]);
