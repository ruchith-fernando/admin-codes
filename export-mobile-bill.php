<?php
include 'connections/connection.php';

$type = $_GET['type'];
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = "WHERE t1.MOBILE_NUMBER LIKE '%$search%' OR t2.name_of_employee LIKE '%$search%'";

$sql = "SELECT t1.*, 
        t2.company_contribution, t2.voice_data, t2.name_of_employee, t2.designation, 
        t2.company_hierarchy, t2.nic_no, t2.hris_no 
        FROM tbl_admin_mobile_bill_data t1 
        LEFT JOIN tbl_admin_mobile_issues t2 
        ON t1.MOBILE_NUMBER = t2.mobile_no 
        $where";

$result = $conn->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=mobile_bill_report.csv');
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, [
    'Mobile Number', 'Previous Due', 'Payments', 'Total Usage', 'IDD', 'Roaming', 'VAS', 'Discounts',
    'Balance Adj.', 'Commitment Charges', 'Late Payment', 'Gov Taxes', 'VAT', 'Add to Bill', 'Bill Charges',
    'Total Payable', 'Company Contribution', 'Voice Data', 'Employee', 'Designation', 'Hierarchy', 'NIC', 'HRIS', 'Salary Deduction'
]);

// Data rows
while($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['MOBILE_Number'], $row['PREVIOUS_DUE_AMOUNT'], $row['PAYMENTS'], $row['TOTAL_USAGE_CHARGES'],
        $row['IDD'], $row['ROAMING'], $row['VALUE_ADDED_SERVICES'], $row['DISCOUNTS_BILL_ADJUSTMENTS'],
        $row['BALANCE_ADJUSTMENTS'], $row['COMMITMENT_CHARGES'], $row['LATE_PAYMENT_CHARGES'],
        $row['GOVERNMENT_TAXES_AND_LEVIES'], $row['VAT'], $row['ADD_TO_BILL'], $row['CHARGES_FOR_BILL_PERIOD'],
        $row['TOTAL_AMOUNT_PAYABLE'], $row['company_contribution'], $row['voice_data'], $row['name_of_employee'],
        $row['designation'], $row['company_hierarchy'], $row['nic_no'], $row['hris_no'], $salary_deduction
    ]);
}

fclose($output);
exit;
?>
