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

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo ''; exit; }

$sql = "SELECT gl_id, gl_code, gl_name
        FROM tbl_admin_gl_account
        WHERE record_status = 'APPROVED'
        ORDER BY gl_code ASC";
$res = $mysqli->query($sql);
if (!$res) { echo ''; exit; }

while ($row = $res->fetch_assoc()) {
  $id = (int)$row['gl_id'];
  $name = htmlspecialchars($row['gl_code'].' - '.$row['gl_name']);
  echo "<option value=\"{$id}\">{$name}</option>";
}
