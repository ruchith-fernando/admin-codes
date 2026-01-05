<!-- ajax-delete-maintenance.php -->
<?php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;
if (!$id) exit(json_encode(['status'=>'error','message'=>'Invalid ID.']));

$stmt = $conn->prepare("UPDATE tbl_admin_vehicle_maintenance SET status = 'Deleted', deleted_by = ?, deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $_SESSION['hris'], $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'message'=>'Failed to update.']);
}
?>
