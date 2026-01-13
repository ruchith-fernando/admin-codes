<?php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}
$mysqli = db();
if (!$mysqli) { http_response_code(500); echo json_encode(['ok'=>0,'error'=>'DB connection not found']); exit; }

$item_id = (int)($_POST['item_id'] ?? 0);
if ($item_id <= 0) { echo json_encode(['ok'=>0,'error'=>'Invalid item_id']); exit; }

$stmt = $mysqli->prepare("SELECT item_id, gl_id, item_code, item_name, uom, item_type_id, is_active, maker_note
                          FROM tbl_admin_item
                          WHERE item_id=? LIMIT 1");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$row = $res->fetch_assoc()) { echo json_encode(['ok'=>0,'error'=>'Item not found']); exit; }

echo json_encode(['ok'=>1,'item'=>$row]);
