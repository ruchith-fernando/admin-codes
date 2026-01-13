<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}
function currentUserId(){
  foreach (['user_id','userid','uid','id','USER_ID','UID'] as $k){
    if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
  }
  return 0;
}
header('Content-Type: application/json');

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo json_encode(['ok'=>0,'error'=>'DB connection not found']); exit; }

$item_id = (int)($_POST['item_id'] ?? 0);
$svg = trim($_POST['barcode_svg'] ?? '');
if ($item_id <= 0 || $svg === '') { echo json_encode(['ok'=>0,'error'=>'Invalid payload']); exit; }

$user_id = currentUserId();
if ($user_id <= 0) $user_id = 1;

$stmt = $mysqli->prepare("
  UPDATE tbl_admin_item_barcode
  SET barcode_svg=?, updated_by=?, updated_at=NOW()
  WHERE item_id=?
");
$stmt->bind_param("sii", $svg, $user_id, $item_id);
$stmt->execute();

echo json_encode(['ok'=>1]);
