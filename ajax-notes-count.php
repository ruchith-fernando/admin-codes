<?php
// ajax-notes-count.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['hris'])) { echo json_encode(['count'=>0]); exit; }

require_once 'connections/connection.php';

$meEsc = mysqli_real_escape_string($con, $_SESSION['hris']);
$sql   = "SELECT COUNT(*) AS c
          FROM tbl_admin_remarks_recipients
          WHERE recipient_hris = '$meEsc' AND is_read = 'no'";
$res   = mysqli_query($con, $sql);
$row   = mysqli_fetch_assoc($res);
echo json_encode(['count' => (int)($row['c'] ?? 0)]);
