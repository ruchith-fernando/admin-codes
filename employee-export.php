<?php
include 'connections/connection.php';

$search = trim($_GET['search'] ?? '');

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=employee_export.csv");

$output = fopen("php://output", "w");

// CSV Header Row
fputcsv($output, [
    "HRIS", "EPF", "Name", "Display", "Designation",
    "Location", "NIC", "Category", "Emp Category",
    "Status", "Date Joined", "Date Resigned"
]);

// Build WHERE clause
$where = "1";
$params = [];
$types  = "";

if ($search !== "") {
    $cols = [
        'hris','epf_no','name_of_employee','display_name','designation',
        'location','nic_no','category','employment_categories','status'
    ];
    $likes = [];
    foreach ($cols as $c) $likes[] = "$c LIKE CONCAT('%', ?, '%')";
    $where = "(" . implode(" OR ", $likes) . ")";

    $types  = str_repeat("s", count($cols));
    $params = array_fill(0, count($cols), $search);
}

// Query data
$sql = "
    SELECT hris, epf_no, name_of_employee, display_name, designation,
           location, nic_no, category, employment_categories, status,
           date_joined, date_resigned
    FROM tbl_admin_employee_details
    WHERE $where
    ORDER BY CAST(hris AS UNSIGNED)
";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Write rows to CSV
while ($row = $result->fetch_assoc()) {
    // Fix invalid resigned date
    if ($row['date_resigned'] == '0000-00-00') {
        $row['date_resigned'] = "";
    }
    fputcsv($output, $row);
}

fclose($output);
exit;
