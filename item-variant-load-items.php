<?php
// item-variant-load-attributes.php
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

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo ''; exit; }

$sql = "SELECT item_id, item_code, item_name
        FROM tbl_admin_item
        WHERE record_status = 'APPROVED' AND is_active = 1
        ORDER BY item_name ASC";
$res = $mysqli->query($sql);
if (!$res) { echo ''; exit; }

while ($row = $res->fetch_assoc()) {
  $id = (int)$row['item_id'];
  $label = htmlspecialchars($row['item_name'].' ('.$row['item_code'].')');
  echo "<option value=\"{$id}\">{$label}</option>";
}
