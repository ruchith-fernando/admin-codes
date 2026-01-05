<?php
// ajax-get-shops.php
require_once 'connections/connection.php';

$term = trim($_GET['term'] ?? '');

if ($term !== '') {
  $stmt = $conn->prepare("
    SELECT id, shop_name
    FROM tbl_admin_shop_name
    WHERE shop_name LIKE CONCAT('%', ?, '%')
    ORDER BY shop_name ASC
    LIMIT 20
  ");
  $stmt->bind_param("s", $term);
} else {
  $stmt = $conn->prepare("
    SELECT id, shop_name
    FROM tbl_admin_shop_name
    ORDER BY shop_name ASC
    LIMIT 20
  ");
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = [
    'id'   => $row['shop_name'], // keep as shop_name for tags + consistent insert
    'text' => $row['shop_name']
  ];
}

$stmt->close();
header('Content-Type: application/json');
echo json_encode($data);
