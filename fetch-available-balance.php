<?php
include 'connections/connection.php';

$item_code = $_POST['item_code'] ?? '';
$response = ['status' => 'error', 'remaining_quantity' => 0];

if ($item_code !== '') {
    $sql = "SELECT SUM(remaining_quantity) AS total_balance
            FROM tbl_admin_stationary_stock_in
            WHERE item_code = '$item_code'";

    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $balance = $row['total_balance'] ?? 0;

    $response['status'] = 'success';
    $response['remaining_quantity'] = intval($balance);
}

echo json_encode($response);
