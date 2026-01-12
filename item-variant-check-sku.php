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
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$variant_code = strtoupper(trim($_POST['variant_code'] ?? ''));
if ($variant_code === '') { echo ''; exit; }

$stmt = $mysqli->prepare("SELECT v.variant_id, v.record_status, i.item_name
                          FROM tbl_admin_item_variant v
                          JOIN tbl_admin_item i ON i.item_id = v.item_id
                          WHERE v.variant_code = ? LIMIT 1");
$stmt->bind_param("s", $variant_code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
  $status = htmlspecialchars($row['record_status']);
  $item = htmlspecialchars($row['item_name']);
  echo alertHtml('warning', "SKU <b>{$variant_code}</b> already exists for <b>{$item}</b> (Status: <b>{$status}</b>).");
} else {
  echo alertHtml('success', "SKU <b>{$variant_code}</b> is available.");
}
