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

$sql = "
  SELECT
    i.item_id,
    i.item_code,
    i.item_name,
    i.uom,
    i.is_active,
    CONCAT(g.gl_code,' - ',g.gl_name) AS gl_label,
    b.barcode_value
  FROM tbl_admin_item i
  JOIN tbl_admin_gl_account g ON g.gl_id = i.gl_id
  LEFT JOIN tbl_admin_item_barcode b ON b.item_id = i.item_id
  ORDER BY i.item_code ASC
";
$res = $mysqli->query($sql);
if (!$res) { echo json_encode(['ok'=>0,'error'=>'Query failed']); exit; }

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

echo json_encode(['ok'=>1,'rows'=>$rows]);
