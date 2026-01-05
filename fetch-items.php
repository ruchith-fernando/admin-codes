<?php
// fetch-items.php
include 'connections/connection.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$data = [];

if ($term !== '') {
    $stmt = $conn->prepare("SELECT item_code, item_description 
                            FROM tbl_admin_print_stationary_master 
                            WHERE item_code LIKE CONCAT('%', ?, '%') 
                            OR item_description LIKE CONCAT('%', ?, '%') 
                            ORDER BY item_description ASC LIMIT 20");
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Get available stock from tbl_admin_stationary_stock_in
        $stockStmt = $conn->prepare("SELECT SUM(remaining_quantity) AS balance 
                                     FROM tbl_admin_stationary_stock_in 
                                     WHERE item_code = ?");
        $stockStmt->bind_param("s", $row['item_code']);
        $stockStmt->execute();
        $stockRes = $stockStmt->get_result();
        $stock = $stockRes->fetch_assoc();
        $available = (int)($stock['balance'] ?? 0);

        $data[] = [
            'id' => $row['item_code'],
            'text' => $row['item_code'] . ' - ' . $row['item_description'] . ' (Stock: ' . $available . ')'
        ];
    }
}

echo json_encode(['results' => $data]);
