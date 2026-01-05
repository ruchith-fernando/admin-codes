<?php
// ajax-delete-rejected-service.php
session_start();
require_once 'connections/connection.php';

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$recordId = $_POST['id'];
$current_user = $_SESSION['hris'] ?? '';

if (empty($current_user)) {
    http_response_code(401);
    echo "Unauthorized.";
    exit;
}

// Only allow deletion of rejected records entered by the current user
$sql = "DELETE FROM tbl_admin_vehicle_service 
        WHERE id = ? AND status = 'Rejected' AND entered_by = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $recordId, $current_user);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "Rejected service record deleted successfully.";
    } else {
        echo "Record not found or permission denied.";
    }
} else {
    http_response_code(500);
    echo "Database error. Please try again.";
}

$stmt->close();
$conn->close();
?>
