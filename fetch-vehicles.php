<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$data = [];
$sql = "SELECT id, sr_number, vehicle_number, vehicle_type, vehicle_category, assigned_user, year_of_manufacture, status 
        FROM tbl_admin_vehicle ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>
