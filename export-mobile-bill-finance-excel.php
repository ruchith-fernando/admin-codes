<?php
include 'connections/connection.php';

$update_date = isset($_GET['update_date']) ? $conn->real_escape_string($_GET['update_date']) : '';
$where = "WHERE Update_date = '$update_date'";

// Main data (excluding 765055020 and negatives)
$sql = "SELECT * FROM tbl_admin_mobile_bill_data 
        $where 
        AND MOBILE_Number != '765055020' 
        AND TOTAL_AMOUNT_PAYABLE >= 0";
$result = $conn->query($sql);

// 765055020 record
$sql_765 = "SELECT TOTAL_AMOUNT_PAYABLE, CHARGES_FOR_BILL_PERIOD 
            FROM tbl_admin_mobile_bill_data 
            WHERE MOBILE_Number = '765055020' AND Update_date = '$update_date'";
$mobile_data = $conn->query($sql_765);
$initial_total_payable = 0;
$initial_total_charges = 0;
if ($mobile_data->num_rows > 0) {
    $row765 = $mobile_data->fetch_assoc();
    $initial_total_payable = $row765['TOTAL_AMOUNT_PAYABLE'];
    $initial_total_charges = $row765['CHARGES_FOR_BILL_PERIOD'];
}

// Negative records
$sql_negative = "SELECT * FROM tbl_admin_mobile_bill_data 
                 WHERE TOTAL_AMOUNT_PAYABLE < 0 AND Update_date = '$update_date'";
$negative_result = $conn->query($sql_negative);
$negative_total = 0;
$negative_rows = [];
while ($neg_row = $negative_result->fetch_assoc()) {
    $negative_total += $neg_row['TOTAL_AMOUNT_PAYABLE'];
    $negative_rows[] = $neg_row;
}

$adjusted_total = $initial_total_payable + $negative_total;

// Prepare download
$filename = 'mobile_bill_report_finance';
if (!empty($update_date)) {
    $filename .= '_' . str_replace(' ', '_', $update_date);
}
$filename .= '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename");

// Table Header
echo "<table border='1'>";
echo "<tr>
        <th>Mobile Number</th><th>Previous Due</th><th>Payments</th><th>Total Usage</th><th>IDD</th>
        <th>Roaming</th><th>VAS</th><th>Discounts</th><th>Balance Adj.</th><th>Commitment Charges</th>
        <th>Late Payment</th><th>Gov Taxes</th><th>VAT</th><th>Add to Bill</th><th>Bill Charges</th>
        <th>Total Payable</th><th>Company Contribution</th><th>Voice Data</th>
        <th>Employee</th><th>Designation</th><th>Hierarchy</th><th>NIC</th><th>HRIS</th><th>Billing Month</th>
      </tr>";

$total_charges = 0;
$total_payable = 0;

// Main rows
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['MOBILE_Number']}</td>";
    echo "<td>" . number_format($row['PREVIOUS_DUE_AMOUNT'], 2) . "</td>";
    echo "<td>" . number_format($row['PAYMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['TOTAL_USAGE_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['IDD'], 2) . "</td>";
    echo "<td>" . number_format($row['ROAMING'], 2) . "</td>";
    echo "<td>" . number_format($row['VALUE_ADDED_SERVICES'], 2) . "</td>";
    echo "<td>" . number_format($row['DISCOUNTS_BILL_ADJUSTMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['BALANCE_ADJUSTMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['COMMITMENT_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['LATE_PAYMENT_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['GOVERNMENT_TAXES_AND_LEVIES'], 2) . "</td>";
    echo "<td>" . number_format($row['VAT'], 2) . "</td>";
    echo "<td>" . number_format($row['ADD_TO_BILL'], 2) . "</td>";
    echo "<td>" . number_format($row['CHARGES_FOR_BILL_PERIOD'], 2) . "</td>";
    echo "<td>" . number_format($row['TOTAL_AMOUNT_PAYABLE'], 2) . "</td>";
    echo "<td>" . (is_numeric($row['company_contribution']) ? number_format($row['company_contribution'], 2) : 'Not Set') . "</td>";
    echo "<td>" . htmlspecialchars($row['voice_data']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name_of_employee']) . " - " . htmlspecialchars($row['display_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['company_hierarchy']) . "</td>";
    echo "<td style='mso-number-format:\"\\@\"'>" . htmlspecialchars($row['nic_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['hris_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Update_date']) . "</td>";
    echo "</tr>";

    $total_charges += $row['CHARGES_FOR_BILL_PERIOD'];
    $total_payable += $row['TOTAL_AMOUNT_PAYABLE'];
}

// Negative rows
foreach ($negative_rows as $row) {
    echo "<tr>";
    echo "<td>{$row['MOBILE_Number']}</td>";
    echo "<td>" . number_format($row['PREVIOUS_DUE_AMOUNT'], 2) . "</td>";
    echo "<td>" . number_format($row['PAYMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['TOTAL_USAGE_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['IDD'], 2) . "</td>";
    echo "<td>" . number_format($row['ROAMING'], 2) . "</td>";
    echo "<td>" . number_format($row['VALUE_ADDED_SERVICES'], 2) . "</td>";
    echo "<td>" . number_format($row['DISCOUNTS_BILL_ADJUSTMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['BALANCE_ADJUSTMENTS'], 2) . "</td>";
    echo "<td>" . number_format($row['COMMITMENT_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['LATE_PAYMENT_CHARGES'], 2) . "</td>";
    echo "<td>" . number_format($row['GOVERNMENT_TAXES_AND_LEVIES'], 2) . "</td>";
    echo "<td>" . number_format($row['VAT'], 2) . "</td>";
    echo "<td>" . number_format($row['ADD_TO_BILL'], 2) . "</td>";
    echo "<td>" . number_format($row['CHARGES_FOR_BILL_PERIOD'], 2) . "</td>";
    echo "<td>" . number_format($row['TOTAL_AMOUNT_PAYABLE'], 2) . "</td>";
    echo "<td>" . (is_numeric($row['company_contribution']) ? number_format($row['company_contribution'], 2) : 'Not Set') . "</td>";
    echo "<td>" . htmlspecialchars($row['voice_data']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name_of_employee']) . " - " . htmlspecialchars($row['display_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['company_hierarchy']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nic_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['hris_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Update_date']) . "</td>";
    echo "</tr>";
}

// Summary
echo "<tr>
        <td colspan='15'><strong>Adjusted Total for 765055020</strong></td>
        <td><strong>" . number_format($initial_total_charges, 2) . "</strong></td>
        <td><strong>" . number_format($adjusted_total, 2) . "</strong></td>
        <td colspan='8'></td>
      </tr>";

echo "<tr>
        <td colspan='15'><strong>Total</strong></td>
        <td><strong>" . number_format($total_charges, 2) . "</strong></td>
        <td><strong>" . number_format($total_payable, 2) . "</strong></td>
        <td colspan='8'></td>
      </tr>";

$total_payable_to_dialog = $total_payable + $adjusted_total;

echo "<tr>
        <td colspan='15'><strong>Total Payable to Dialog</strong></td>
        <td></td>
        <td><strong>" . number_format($total_payable_to_dialog, 2) . "</strong></td>
        <td colspan='8'></td>
      </tr>";

echo "</table>";
exit;
?>
