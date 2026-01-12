<?php
// gl-master-check-code.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

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
$gl_code = strtoupper(trim($_POST['gl_code'] ?? ''));

if (!$mysqli) { echo json_encode(['ok'=>false,'error'=>'DB connection not found.']); exit; }
if ($gl_code === '') { echo json_encode(['ok'=>true,'available'=>false,'html'=>'']); exit; }

$stmt = $mysqli->prepare("SELECT gl_id, gl_name FROM tbl_admin_gl_account WHERE gl_code=? LIMIT 1");
$stmt->bind_param("s", $gl_code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
  $name = htmlspecialchars($row['gl_name']);
  echo json_encode([
    'ok' => true,
    'available' => false,
    'html' => alertHtml('warning', "GL Code <b>{$gl_code}</b> already exists: <b>{$name}</b>.")
  ]);
} else {
  echo json_encode([
    'ok' => true,
    'available' => true,
    'html' => alertHtml('success', "GL Code <b>{$gl_code}</b> is available.")
  ]);
}
