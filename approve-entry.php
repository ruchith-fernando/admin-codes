<?php
// approve-entry.php
require_once 'connections/connection.php';
session_start();

header('Content-Type: application/json');

if (!in_array($_SESSION['user_level'], ['super-admin', 'verifier'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$id = $_POST['id'] ?? '';
$type = $_POST['type'] ?? '';
$user = $_SESSION['user'] ?? 'unknown';

if (!$id || !$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters.']);
    exit;
}

$success = false;
$approvedAt = date('Y-m-d H:i:s');

switch ($type) {
    case 'maintenance':
        $price = $_POST['price'] ?? '';
        $stmt = $conn->prepare("UPDATE tbl_admin_vehicle_maintenance SET price = ?, status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
        $stmt->bind_param("sssi", $price, $user, $approvedAt, $id);
        break;

    case 'service':
        $service_date = $_POST['service_date'] ?? '';
        $meter_reading = $_POST['meter_reading'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $stmt = $conn->prepare("UPDATE tbl_admin_vehicle_service SET service_date = ?, meter_reading = ?, amount = ?, status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
        $stmt->bind_param("sisssi", $service_date, $meter_reading, $amount, $user, $approvedAt, $id);
        break;

    case 'license':
        $revenue_date = $_POST['revenue_license_date'] ?? '';
        $revenue_amount = $_POST['revenue_license_amount'] ?? '';
        $insurance_amount = $_POST['insurance_amount'] ?? '';
        $stmt = $conn->prepare("UPDATE tbl_admin_vehicle_licensing_insurance SET revenue_license_date = ?, revenue_license_amount = ?, insurance_amount = ?, status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $revenue_date, $revenue_amount, $insurance_amount, $user, $approvedAt, $id);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type.']);
        exit;
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database update failed.']);
}

$stmt->close();
