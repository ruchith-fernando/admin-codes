<?php
// ajax-approve-update-vehicle.php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $approved_by = $_SESSION['hris'] ?? null;

    if (!$approved_by) {
        echo json_encode(['status' => 'error', 'message' => 'HRIS not found in session.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT sr_number, vehicle_number FROM tbl_admin_vehicle WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();

    if (!$vehicle) {
        echo json_encode(['status' => 'error', 'message' => 'Vehicle not found.']);
        exit;
    }

    $sr_number = $vehicle['sr_number'];
    $vehicle_number = $vehicle['vehicle_number'];

    $stmt = $conn->prepare("UPDATE tbl_admin_vehicle 
                            SET status = 'Approved', approved_by = ?, approved_at = NOW()
                            WHERE id = ?");
    $stmt->bind_param("si", $approved_by, $id);

    if ($stmt->execute()) {
        $response = [
            'status' => 'success',
            'message' => 'Vehicle approved successfully.',
            'sr_number' => $sr_number,
            'vehicle_number' => $vehicle_number
        ];
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
}

echo json_encode($response);
exit;
