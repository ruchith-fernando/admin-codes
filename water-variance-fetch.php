<?php
require_once 'connections/connection.php';
header("Content-Type: application/json");

$month = trim($_POST['month'] ?? '');

if ($month === '') {
    echo json_encode(['table' => '', 'error' => 'No month selected']);
    exit;
}

$sql = "
    SELECT 
        a.branch_code,
        a.branch AS branch_name,
        a.total_amount AS actual_amount,
        b.amount AS budget_amount
    FROM tbl_admin_actual_water a
    LEFT JOIN tbl_admin_budget_water b 
        ON a.branch_code = b.branch_code
    WHERE a.month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
      AND a.approval_status = 'approved'
";

$res = mysqli_query($conn, $sql);

// collect rows first
$rows = [];
while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
}

// NATURAL SORT branch_code (2,3,4,10,11,12,51,51-2...)
usort($rows, function($a, $b){
    return strnatcmp($a['branch_code'], $b['branch_code']);
});

$table_html = "
<table class='table table-bordered table-striped align-middle'>
<thead class='table-light'>
<tr>
    <th>Branch Code</th>
    <th>Branch Name</th>
    <th class='text-end'>Actual</th>
    <th class='text-end'>Budget</th>
    <th class='text-end'>Variance</th>
</tr>
</thead>
<tbody>
";

$total_actual = 0;
$total_budget = 0;
$total_variance = 0;

foreach ($rows as $r) {

    $actual = (float)preg_replace('/[^0-9.\-]/', '', $r['actual_amount']);
    $budget = (float)$r['budget_amount'];
    $variance = $actual - $budget;

    $total_actual += $actual;
    $total_budget += $budget;
    $total_variance = $total_budget - $total_actual;

    $table_html .= "
    <tr>
        <td>{$r['branch_code']}</td>
        <td>{$r['branch_name']}</td>
        <td class='text-end'>" . number_format($actual, 2) . "</td>
        <td class='text-end'>" . number_format($budget, 2) . "</td>
        <td class='text-end " . ($variance < 0 ? 'text-danger' : 'text-success') . "'>
            " . number_format($variance, 2) . "
        </td>
    </tr>";
}

$table_html .= "
<tr class='table-secondary fw-bold'>
    <td colspan='2'>TOTAL</td>
    <td class='text-end'>" . number_format($total_actual, 2) . "</td>
    <td class='text-end'>" . number_format($total_budget, 2) . "</td>
    <td class='text-end'>" . number_format($total_variance, 2) . "</td>
</tr>
</tbody>
</table>
";

echo json_encode(['table'=>$table_html]);
?>
