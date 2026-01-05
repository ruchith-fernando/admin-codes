<?php
require_once 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE tbl_admin_vehicle_licensing_insurance SET reminder_completed = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: dashboard.php"); // or wherever the user should return
exit;
