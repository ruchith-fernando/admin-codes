<?php
// ajax-approve-license.php
session_start();
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
$approver = $_SESSION['hris']; // replace with actual session variable

$sql = "UPDATE tbl_admin_vehicle_licensing_insurance 
        SET status = 'Approved', approved_by = ?, approved_at = NOW() 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $approver, $id);

if ($stmt->execute()) {
    echo 'success';
} else {
    echo 'error';
}
?>
