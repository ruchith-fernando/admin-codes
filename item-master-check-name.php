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
function alertHtml($type, $msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.
    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
$item_name = trim($_POST['item_name'] ?? '');

if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
if ($item_name === '') { echo ''; exit; }

$stmt = $mysqli->prepare("SELECT i.item_code, i.record_status, g.gl_code
                          FROM tbl_admin_item i
                          JOIN tbl_admin_gl_account g ON g.gl_id = i.gl_id
                          WHERE i.item_name = ? LIMIT 1");
$stmt->bind_param("s", $item_name);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $status = htmlspecialchars($row['record_status']);
  $code = htmlspecialchars($row['item_code']);
  $gl = htmlspecialchars($row['gl_code']);
  echo alertHtml('warning', "Item Name already exists under Code <b>{$code}</b> (GL: <b>{$gl}</b>, Status: <b>{$status}</b>).");
} else {
  echo alertHtml('success', "Item Name is available.");
}
