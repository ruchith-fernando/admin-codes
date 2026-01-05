<?php
require_once 'connections/connection.php';
if (!isset($_POST['id'])) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$id = intval($_POST['id']);
$stmt = $conn->prepare("SELECT * FROM tbl_admin_vehicle_service WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning">Service record not found.</div>';
    exit;
}

$row = $result->fetch_assoc();
include 'fragments-form-service.php'; // Standard form fragment for service
?>
