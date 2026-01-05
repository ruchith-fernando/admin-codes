<?php
include 'connections/connection.php';

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = "WHERE t1.MOBILE_NUMBER LIKE '%$search%' 
    OR t2.name_of_employee LIKE '%$search%' 
    OR t1.Update_date LIKE '%$search%' 
    OR t2.nic_no LIKE '%$search%' 
    OR t2.hris_no LIKE '%$search%'";

$sql = "SELECT t1.*, 
           t2.voice_data, 
           t2.name_of_employee, 
           t2.designation, 
           t2.company_hierarchy, 
           t2.nic_no, 
           t2.hris_no, 
           CONCAT(t2.name_of_employee, ' - ', t2.display_name) AS full_display_name,
           (
                SELECT c.contribution_amount 
                FROM tbl_admin_hris_contributions c 
                WHERE c.hris_no = t2.hris_no 
                  AND c.mobile_no = t1.MOBILE_Number 
                  AND STR_TO_DATE(CONCAT('01-', c.effective_from), '%d-%M-%Y') <= STR_TO_DATE(CONCAT('01-', t1.Update_date), '%d-%M-%Y')
                ORDER BY STR_TO_DATE(CONCAT('01-', c.effective_from), '%d-%M-%Y') DESC 
                LIMIT 1
           ) AS company_contribution
    FROM tbl_admin_mobile_bill_data t1 
    LEFT JOIN tbl_admin_mobile_issues t2 ON t1.MOBILE_Number = t2.mobile_no 
    $where";

$result = $conn->query($sql);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=mobile_bill_report.xls");

echo "<table border='1'>";
echo "<tr>
        <th>Mobile Number</th><th>Previous Due</th><th>Payments</th><th>Total Usage</th><th>IDD</th>
        <th>Roaming</th><th>VAS</th><th>Discounts</th><th>Balance Adj.</th><th>Commitment Charges</th>
        <th>Late Payment</th><th>Gov Taxes</th><th>VAT</th><th>Add to Bill</th><th>Bill Charges</th>
        <th>Total Payable</th><th>Company Contribution</th><th>Voice Data</th>
        <th>Employee</th><th>Designation</th><th>Hierarchy</th><th>NIC</th><th>HRIS</th><th>Salary Deduction</th>
        <th>Billing Month</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    // Skip if HRIS is not fully numeric
    if (!ctype_digit($row['hris_no'])) {
        continue;
    }

    $company_contribution = floatval($row['company_contribution']);
    $X = $row['TOTAL_AMOUNT_PAYABLE'] - $company_contribution;
    $Y = $row['ROAMING'] + $row['VALUE_ADDED_SERVICES'] + $row['ADD_TO_BILL'];
    $salary_deduction = ($X < $Y) ? $Y : $X;

    // Skip if salary deduction is 0 or negative
    if ($salary_deduction <= 0) {
        continue;
    }

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
    echo "<td>" . number_format($company_contribution, 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['voice_data']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_display_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['company_hierarchy']) . "</td>";
    echo "<td style='mso-number-format:\"\\@\"'>" . htmlspecialchars($row['nic_no']) . "</td>"; 
    echo "<td>" . htmlspecialchars($row['hris_no']) . "</td>";
    echo "<td>" . number_format($salary_deduction, 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['Update_date']) . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
?>
