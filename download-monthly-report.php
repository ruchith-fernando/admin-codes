<?php
require_once 'connections/connection.php';

$selectedMonth = $_GET['month'] ?? null;

if (!$selectedMonth) {
    die("Month not specified.");
}

// Get budget data
$budget_sql = "SELECT branch_code, branch, month_applicable AS month, no_of_shifts, rate, (no_of_shifts * rate) AS budgeted_amount
               FROM tbl_admin_budget_security WHERE month_applicable = ?";
$stmt = $conn->prepare($budget_sql);
$stmt->bind_param("s", $selectedMonth);
$stmt->execute();
$budget_result = $stmt->get_result();

$budget_data = [];
while ($row = $budget_result->fetch_assoc()) {
    $key = $row['branch_code'] . '_' . $row['month'];
    $budget_data[$key] = $row;
}

// Get actuals
$actual_sql = "SELECT branch_code, branch, month_applicable AS month, actual_shifts, total_amount AS actual_amount
               FROM tbl_admin_actual_security WHERE month_applicable = ?";
$stmt = $conn->prepare($actual_sql);
$stmt->bind_param("s", $selectedMonth);
$stmt->execute();
$actual_result = $stmt->get_result();

$report = [];
while ($row = $actual_result->fetch_assoc()) {
    $key = $row['branch_code'] . '_' . $row['month'];
    $report[$key] = array_merge($budget_data[$key] ?? [
        'branch_code' => $row['branch_code'],
        'branch' => $row['branch'],
        'month' => $row['month'],
        'no_of_shifts' => 0,
        'rate' => 0,
        'budgeted_amount' => 0
    ], $row);
}

// Fill in any missing budget entries
foreach ($budget_data as $key => $row) {
    if (!isset($report[$key])) {
        $report[$key] = array_merge($row, [
            'actual_shifts' => 0,
            'actual_amount' => 0
        ]);
    }
}

usort($report, function($a, $b) {
    return [$a['branch_code'], $a['month']] <=> [$b['branch_code'], $b['month']];
});

// Cumulative totals
$cumulative = [];
foreach ($report as &$row) {
    $code = $row['branch_code'];
    if (!isset($cumulative[$code])) {
        $cumulative[$code] = ['budget' => 0, 'actual' => 0];
    }
    $cumulative[$code]['budget'] += $row['budgeted_amount'];
    $cumulative[$code]['actual'] += $row['actual_amount'];

    $row['difference'] = $row['actual_amount'] - $row['budgeted_amount'];
    $row['cumulative_budget'] = $cumulative[$code]['budget'];
    $row['cumulative_actual'] = $cumulative[$code]['actual'];
    $row['cumulative_diff'] = $row['cumulative_actual'] - $row['cumulative_budget'];
}

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Budget_vs_Actual_Report_" . str_replace(' ', '_', $selectedMonth) . ".xls");

echo "<table border='1'>";
echo "<thead>
        <tr>
            <th>Branch Code</th>
            <th>Branch</th>
            <th>Month</th>
            <th>Budgeted Shifts</th>
            <th>Actual Shifts</th>
            <th>Rate</th>
            <th>Budgeted Amount</th>
            <th>Actual Amount</th>
            <th>Difference</th>
            <th>Cumulative Budget</th>
            <th>Cumulative Actual</th>
            <th>Cumulative Diff</th>
        </tr>
      </thead><tbody>";

foreach ($report as $row) {
    echo "<tr>
        <td>{$row['branch_code']}</td>
        <td>{$row['branch']}</td>
        <td>{$row['month']}</td>
        <td>{$row['no_of_shifts']}</td>
        <td>{$row['actual_shifts']}</td>
        <td>" . number_format($row['rate'], 2) . "</td>
        <td>" . number_format($row['budgeted_amount'], 2) . "</td>
        <td>" . number_format($row['actual_amount'], 2) . "</td>
        <td>" . number_format($row['difference'], 2) . "</td>
        <td>" . number_format($row['cumulative_budget'], 2) . "</td>
        <td>" . number_format($row['cumulative_actual'], 2) . "</td>
        <td>" . number_format($row['cumulative_diff'], 2) . "</td>
    </tr>";
}

echo "</tbody></table>";
exit;
?>
