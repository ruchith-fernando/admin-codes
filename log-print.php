<?php
session_start();
include "connections/connection.php";

$docNumber = $_POST['docNumber'];
$hris = $_POST['hris'];
$copies = intval($_POST['copies'] ?? 1);
$user = $_SESSION['username'] ?? 'unknown';

$sql = "INSERT INTO tbl_admin_secure_print_logs (document_number, requested_by_hris, printed_by, datetime_printed, copies_printed)
        VALUES (?, ?, ?, NOW(), ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $docNumber, $hris, $user, $copies);
$stmt->execute();
?>
