<?php
// ajax-approve-vehicle.php
session_start();
require_once 'connections/connection.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SESSION['user_role'] !== 'super_admin') {
    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $approved_by = $_SESSION['username'];
    $approved_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE tbl_admin_vehicle SET status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $approved_by, $approved_at, $id);

    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Vehicle approved'];
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
