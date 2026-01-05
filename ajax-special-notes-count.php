<?php
// ajax-special-notes-count.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['hris'])) { echo json_encode(['count' => 0]); exit; }

require_once 'connections/connection.php';

$hris = mysqli_real_escape_string($conn, $_SESSION['hris']);

/*
 * Unread = recipients rows addressed to this user where is_read = 'no'.
 * We join to remarks mainly to ensure referential integrity; you can omit the join if you want.
 */
$sql = "
  SELECT COUNT(*) AS cnt
  FROM tbl_admin_remarks_recipients rr
  JOIN tbl_admin_remarks r ON r.id = rr.remark_id
  WHERE rr.recipient_hris = '{$hris}'
    AND rr.is_read = 'no'
";
$res  = mysqli_query($conn, $sql);
$row  = $res ? mysqli_fetch_assoc($res) : null;
$count = (int)($row['cnt'] ?? 0);

echo json_encode(['count' => $count]);
