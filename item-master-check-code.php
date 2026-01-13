<?php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}
function alertHtml($type, $msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$item_code = strtoupper(trim($_POST['item_code'] ?? ''));
$item_id = (int)($_POST['item_id'] ?? 0);
if ($item_code === '') { echo ''; exit; }

$stmt = $mysqli->prepare("SELECT item_id, item_name, record_status FROM tbl_admin_item WHERE item_code=? LIMIT 1");
$stmt->bind_param("s", $item_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  if ((int)$row['item_id'] === $item_id) {
    echo alertHtml('success', "Item Code <b>{$item_code}</b> is yours (editing).");
  } else {
    $name = htmlspecialchars($row['item_name']);
    $status = htmlspecialchars($row['record_status']);
    echo alertHtml('warning', "Item Code <b>{$item_code}</b> already exists: <b>{$name}</b> (Status: <b>{$status}</b>).");
  }
} else {
  echo alertHtml('success', "Item Code <b>{$item_code}</b> is available.");
}
