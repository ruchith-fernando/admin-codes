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
$gl_code = strtoupper(trim($_POST['gl_code'] ?? ''));

if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
if ($gl_code === '') { echo ''; exit; }

$stmt = $mysqli->prepare("SELECT gl_id, gl_name, record_status FROM tbl_admin_gl_account WHERE gl_code = ? LIMIT 1");
$stmt->bind_param("s", $gl_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $status = htmlspecialchars($row['record_status']);
  $name = htmlspecialchars($row['gl_name']);
  echo alertHtml('warning', "GL Code <b>{$gl_code}</b> already exists: <b>{$name}</b> (Status: <b>{$status}</b>).");
} else {
  echo alertHtml('success', "GL Code <b>{$gl_code}</b> is available.");
}
