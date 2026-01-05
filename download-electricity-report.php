<?php
require_once 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['month'])) {
    die("Invalid request.");
}

$month = $_POST['month'];

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"Electricity_Report_{$month}.xls\"");
header("Pragma: no-cache");
header("Expires: 0");

function renderExcelSection($title, $records, $totals) {
    echo "<h3>{$title}</h3>";
    echo "<table border='1'>";
    echo "<tr>
        <th>Branch Code</th>
        <th>Branch</th>
        <th>Payment Bank</th>
        <th>Account No</th>
        <th>Bill From Date</th>
        <th>To Bill</th>
        <th>Total Amount</th>
        <th>No. of Days</th>
        <th>Units</th>
        <th>Paid Amount</th>
        <th>Chq Number & Date</th>
        <th>Ar./Cr.</th>
    </tr>";

    foreach ($records as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['branch']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bank_paid_to']) . "</td>";
        echo "<td>" . htmlspecialchars($row['account_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bill_from_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['bill_to_date']) . "</td>";
        echo "<td>" . number_format(floatval($row['total_amount']), 2) . "</td>";
        echo "<td>" . htmlspecialchars($row['number_of_days']) . "</td>";
        echo "<td>" . htmlspecialchars($row['actual_units']) . "</td>";
        echo "<td>" . number_format(floatval($row['paid_amount']), 2) . "</td>";
        echo "<td>" . htmlspecialchars($row['cheque_number']) . "<br>" . htmlspecialchars($row['cheque_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ar_cr']) . "</td>";
        echo "</tr>";
    }

    echo "<tr style='font-weight:bold; background:#f2f2f2'>
        <td colspan='6' align='right'>Total:</td>
        <td>" . number_format($totals['total_amount'], 2) . "</td>
        <td></td>
        <td>" . number_format($totals['units']) . "</td>
        <td>" . number_format($totals['paid_amount'], 2) . "</td>
        <td colspan='2'></td>
    </tr>";
    echo "</table><br><br>";
}

$stmt = $conn->prepare("
    SELECT 
        a.branch_code,
        b.branch_name AS branch,
        b.bank_paid_to,
        b.account_no,
        a.bill_from_date,
        a.bill_to_date,
        a.total_amount,
        a.number_of_days,
        a.actual_units,
        a.paid_amount,
        a.cheque_number,
        a.cheque_date,
        a.ar_cr
    FROM tbl_admin_actual_electricity a
    LEFT JOIN tbl_admin_branch_electricity b ON a.branch_code = b.branch_code
    WHERE a.month_applicable = ?
    ORDER BY a.branch_code
");
$stmt->bind_param("s", $month);
$stmt->execute();
$result = $stmt->get_result();

$records_numeric = [];
$records_C = [];
$records_B = [];
$records_Y = [];

$totals = [
    'numeric' => ['total_amount' => 0, 'units' => 0, 'paid_amount' => 0],
    'C' => ['total_amount' => 0, 'units' => 0, 'paid_amount' => 0],
    'B' => ['total_amount' => 0, 'units' => 0, 'paid_amount' => 0],
    'Y' => ['total_amount' => 0, 'units' => 0, 'paid_amount' => 0],
];

while ($row = $result->fetch_assoc()) {
    $code = $row['branch_code'];
    if (is_numeric($code)) {
        $records_numeric[] = $row;
        $totals['numeric']['total_amount'] += floatval($row['total_amount']);
        $totals['numeric']['units'] += floatval($row['actual_units']);
        $totals['numeric']['paid_amount'] += floatval($row['paid_amount']);
    } elseif (str_starts_with($code, 'C')) {
        $records_C[] = $row;
        $totals['C']['total_amount'] += floatval($row['total_amount']);
        $totals['C']['units'] += floatval($row['actual_units']);
        $totals['C']['paid_amount'] += floatval($row['paid_amount']);
    } elseif (str_starts_with($code, 'B')) {
        $records_B[] = $row;
        $totals['B']['total_amount'] += floatval($row['total_amount']);
        $totals['B']['units'] += floatval($row['actual_units']);
        $totals['B']['paid_amount'] += floatval($row['paid_amount']);
    } elseif (str_starts_with($code, 'Y')) {
        $records_Y[] = $row;
        $totals['Y']['total_amount'] += floatval($row['total_amount']);
        $totals['Y']['units'] += floatval($row['actual_units']);
        $totals['Y']['paid_amount'] += floatval($row['paid_amount']);
    }
}

usort($records_numeric, fn($a, $b) => intval($a['branch_code']) <=> intval($b['branch_code']));

renderExcelSection("Numeric Branch Codes (1, 2, 3, ...)", $records_numeric, $totals['numeric']);
renderExcelSection("Branch Codes Starting with C", $records_C, $totals['C']);
renderExcelSection("Branch Codes Starting with B", $records_B, $totals['B']);
renderExcelSection("Branch Codes Starting with Y", $records_Y, $totals['Y']);

?>
