<?php
include 'connections/connection.php';

$search = $_GET['search'] ?? '';
$search = mysqli_real_escape_string($conn, $search);

$sql = "SELECT DISTINCT s.item_code, m.item_description
  FROM tbl_admin_stationary_stock_in s
  JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
  WHERE s.item_code LIKE '%$search%' OR m.item_description LIKE '%$search%'
  ORDER BY s.item_code ASC
  LIMIT 20";

$result = mysqli_query($conn, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
  $data[] = [
    'item_code' => $row['item_code'],
    'item_name' => $row['item_description']
  ];
}

header('Content-Type: application/json');
echo json_encode($data);
