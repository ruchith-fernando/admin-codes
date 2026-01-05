<?php
session_start();
require_once 'connections/connection.php';

$hris_id = isset($_SESSION['hris']) ? $_SESSION['hris_id'] : 'unknown';
$report_name = isset($_POST['report_name']) ? $_POST['report_name'] : 'unspecified';
$downloaded_at = date("Y-m-d H:i:s");

$query = "INSERT INTO tbl_admin_download_log (hris_id, downloaded_at, report_name)
          VALUES ('$hris_id', '$downloaded_at', '$report_name')";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => mysqli_error($conn)]);
}
?>
