<?php
// get-users.php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json');

$current = $_SESSION['hris'] ?? '';

$res = $conn->query("SELECT hris, name FROM tbl_admin_users ORDER BY name ASC");
$out = [];
while ($row = $res->fetch_assoc()) {
  if ($row['hris'] === $current) continue; // optional: hide self
  $out[] = ['hris' => $row['hris'], 'name' => $row['name']];
}
echo json_encode($out);
