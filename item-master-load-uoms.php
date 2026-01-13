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
$mysqli = db();
if (!$mysqli) { http_response_code(500); echo ''; exit; }

$sql = "SELECT uom, uom_name
        FROM tbl_admin_uom
        WHERE is_active=1
        ORDER BY uom_name ASC";
$res = $mysqli->query($sql);
if (!$res) { echo ''; exit; }

while ($row = $res->fetch_assoc()) {
  $uom = strtoupper(trim($row['uom']));
  $name = $row['uom_name'];
  $uomH = htmlspecialchars($uom);
  $nameH = htmlspecialchars($name);
  echo "<option value=\"{$uomH}\">{$nameH} ({$uomH})</option>";
}
