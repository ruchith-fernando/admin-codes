<?php
// ajax-special-notes-mark-read.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['hris'])) { echo json_encode(['ok' => false]); exit; }

require_once 'connections/connection.php';

$hris = mysqli_real_escape_string($conn, $_SESSION['hris']);

$sql = "
  UPDATE tbl_admin_remarks_recipients
  SET is_read = 'yes', read_at = NOW()
  WHERE recipient_hris = '{$hris}' AND is_read = 'no'
";
$ok = mysqli_query($conn, $sql);

echo json_encode(['ok' => (bool)$ok]);
