<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'connections/connection.php';

// Setup log file
$logFile = 'export-mobile-bill.log';
function logToFile($message) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
logToFile("Search Term: $search");

$where = "WHERE (MOBILE_Number LIKE '%$search%' 
        OR name_of_employee LIKE '%$search%' 
        OR Update_date LIKE '%$search%' 
        OR nic_no LIKE '%$search%' 
        OR hris_no LIKE '%$search%') 
        AND hris_no REGEXP '^[0-9]+\$'";

logToFile("WHERE Clause: $where");

$sql = "SELECT * FROM tbl_admin_mobile_bill_data 
        $where 
        ORDER BY STR_TO_DATE(CONCAT('01-', Update_date), '%d-%M-%Y') ASC";


$result = $conn->query($sql);
if (!$result) {
    logToFile("MySQL Error: " . $conn->error);
    exit("Query failed. Check log file.");
}

logToFile("Query executed. Rows returned: " . $result->num_rows);

$filename_search = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $search);
$filename = "mobile_bill_report" . ($filename_search ? "_$filename_search" : "") . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo "<table border='1'>";
echo "<tr>
        <th>Mobile Number</th><th>Previous Due</th><th>Payments</th><th>Total Usage</th><th>IDD</th>
        <th>Roaming</th><th>VAS</th><th>Discounts</th><th>Balance Adj.</th><th>Commitment Charges</th>
        <th>Late Payment</th><th>Gov Taxes</th><th>VAT</th><th>Add to Bill</th><th>Bill Charges</th>
        <th>Total Payable</th><th>Company Contribution</th><th>Voice Data</th>
        <th>Employee</th><th>Designation</th><th>Hierarchy</th><th>NIC</th><th>HRIS</th><th>Salary Deduction</th>
        <th>Billing Month</th>
      </tr>";

$row_count = 0;

while ($row = $result->fetch_assoc()) {
    $row_count++;

    $total = is_numeric($row['TOTAL_AMOUNT_PAYABLE']) ? $row['TOTAL_AMOUNT_PAYABLE'] : 0;
    $contribution = is_numeric($row['company_contribution']) ? $row['company_contribution'] : 0;
    $roaming = is_numeric($row['ROAMING']) ? $row['ROAMING'] : 0;
    $vas = is_numeric($row['VALUE_ADDED_SERVICES']) ? $row['VALUE_ADDED_SERVICES'] : 0;
    $addtobill = is_numeric($row['ADD_TO_BILL']) ? $row['ADD_TO_BILL'] : 0;

    $X = $total - $contribution;
    $Y = $roaming + $vas + $addtobill;
    $salary_deduction = ($X < $Y) ? $Y : $X;

    if (round($salary_deduction, 2) == 0) continue;

    echo "<tr>";
    echo "<td>{$row['MOBILE_Number']}</td>";
    echo "<td>" . number_format((float)($row['PREVIOUS_DUE_AMOUNT'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['PAYMENTS'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['TOTAL_USAGE_CHARGES'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['IDD'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)$roaming, 2) . "</td>";
    echo "<td>" . number_format((float)$vas, 2) . "</td>";
    echo "<td>" . number_format((float)($row['DISCOUNTS_BILL_ADJUSTMENTS'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['BALANCE_ADJUSTMENTS'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['COMMITMENT_CHARGES'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['LATE_PAYMENT_CHARGES'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['GOVERNMENT_TAXES_AND_LEVIES'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)($row['VAT'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)$addtobill, 2) . "</td>";
    echo "<td>" . number_format((float)($row['CHARGES_FOR_BILL_PERIOD'] ?? 0), 2) . "</td>";
    echo "<td>" . number_format((float)$total, 2) . "</td>";
    echo "<td>" . number_format((float)$contribution, 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['voice_data']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name_of_employee'] . ' - ' . $row['display_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['company_hierarchy']) . "</td>";
    echo "<td style='mso-number-format:\"\\@\"'>" . htmlspecialchars($row['nic_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['hris_no']) . "</td>";
    echo "<td>" . number_format((float)$salary_deduction, 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['Update_date']) . "</td>";
    echo "</tr>";
}

echo "</table>";
logToFile("Total rows processed for export: $row_count");
logToFile("Excel export completed successfully.");
exit;
?>
