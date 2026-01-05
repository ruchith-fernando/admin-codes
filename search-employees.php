<?php
require_once 'connections/connection.php';

$search = $_POST['search'] ?? '';

$sql = "SELECT hris, display_name FROM tbl_admin_employee_details 
        WHERE status = 'Active' AND (display_name LIKE ? OR hris LIKE ?)
        ORDER BY display_name ASC LIMIT 20";

$stmt = $conn->prepare($sql);
$param = "%$search%";
$stmt->bind_param("ss", $param, $param);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'hris' => $row['hris'],
        'display_name' => $row['display_name']
    ];
}

echo json_encode($data);
