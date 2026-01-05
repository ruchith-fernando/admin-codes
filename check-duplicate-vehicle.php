<?php
require_once 'connections/connection.php';

header('Content-Type: application/json');

$vehicle_number = trim($_POST['vehicle_number'] ?? '');
$response = ['exists' => false];

if ($vehicle_number !== '') {
    $stmt = $conn->prepare("
        SELECT vehicle_type, assigned_user, status, vehicle_category 
        FROM tbl_admin_vehicle 
        WHERE vehicle_number = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $vehicle_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response['exists'] = true;
        $response['vehicle'] = $row;
    }
    $stmt->close();
}

echo json_encode($response);
exit;
