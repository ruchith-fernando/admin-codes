<?php
include 'connections/connection.php';

if (isset($_GET['item_code'])) {
    $item_code = $_GET['item_code'];

    $stmt = $conn->prepare("SELECT SUM(remaining_quantity) as balance FROM tbl_admin_stationary_stock_in WHERE item_code = ?");
    $stmt->bind_param("s", $item_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(['balance' => $row['balance'] ?? 0]);
}
?>
