<?php
include 'connections/connection.php';

$user = $_POST['user'] ?? 'Unknown';
$action = $_POST['action'] ?? 'No action given';

$stmt = $conn->prepare("INSERT INTO tbl_admin_user_logs (user, action) VALUES (?, ?)");
$stmt->bind_param("ss", $user, $action);
$stmt->execute();
$stmt->close();
$conn->close();
?>
