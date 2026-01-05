<?php
// ajax-get-balance.php
include 'connections/connection.php';

header('Content-Type: application/json');

$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

if ($item_code === '') {
    echo json_encode(['available' => 0]);
    exit;
}

$sql = "SELECT SUM(remaining_quantity) AS available 
        FROM tbl_admin_stationary_stock_in 
        WHERE item_code = ? AND remaining_quantity > 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $item_code);
$stmt->execute();
$result = $stmt->get_result();

$row = $result->fetch_assoc();
$available = (int)($row['available'] ?? 0);

echo json_encode(['available' => $available]);
