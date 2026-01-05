<?php
// export-hr-report-dialog-excel.php
include 'connections/connection.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=hr-report-dialog.csv');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Billing Month', 'HRIS', 'Employee', 'Designation', 'NIC', 'Mobile Number',
    'Total Payable (Rs.)', 'Contribution (Rs.)', 'Salary Deduction (Rs.)'
]);

$sql = "
    SELECT 
        billing_month, hris_no, employee_name, designation,
        nic_no, mobile_number, total_amount_payable, contribution_amount
    FROM tbl_admin_hr_report_dialog_summary
    WHERE (
        ? = '' OR 
        mobile_number LIKE CONCAT('%', ?, '%') OR
        billing_month LIKE CONCAT('%', ?, '%') OR
        hris_no LIKE CONCAT('%', ?, '%') OR
        employee_name LIKE CONCAT('%', ?, '%') OR
        nic_no LIKE CONCAT('%', ?, '%')
    )
      AND hris_no REGEXP '^[0-9]+$'
    ORDER BY 
        CASE 
            WHEN billing_month REGEXP '^[A-Za-z]+-[0-9]{4}$' 
            THEN STR_TO_DATE(CONCAT('01-', billing_month), '%d-%M-%Y')
            ELSE NULL
        END DESC,
        billing_month DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $search, $search, $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $billingMonth = !empty($row['billing_month']) ? $row['billing_month'] : 'From Data Bucket';
    $totalPayable = (float)$row['total_amount_payable'];
    $contribution = (float)$row['contribution_amount'];
    $salaryDeduction = ($totalPayable <= 0 || $contribution > $totalPayable)
        ? 0 : $totalPayable - $contribution;

    fputcsv($output, [
        $billingMonth,
        '="' . str_pad($row['hris_no'], 6, "0", STR_PAD_LEFT) . '"',
        $row['employee_name'],
        $row['designation'],
        '="' . $row['nic_no'] . '"',
        '="' . $row['mobile_number'] . '"',
        number_format($totalPayable, 2, '.', ','),
        number_format($contribution, 2, '.', ','),
        number_format($salaryDeduction, 2, '.', ',')
    ]);
}

fclose($output);

// ✅ Log export success
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();
    $searchText = $search !== '' ? $search : 'All Records';
    $actionMessage = sprintf(
        '✅ Exported HR Dialog Excel | Search: %s | Rows exported: %d',
        $searchText,
        $result->num_rows ?? 0
    );
    userlog($actionMessage);
} catch (Throwable $e) {}

exit;
?>
