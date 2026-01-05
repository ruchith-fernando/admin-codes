<?php
include 'connections/connection.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$search = $_GET['search']['value'];
$start = $_GET['start'];
$length = $_GET['length'];

$where = "WHERE DATE_FORMAT(si.received_date, '%Y-%m') <= '$month'";
if (!empty($search)) {
    $where .= " AND (si.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}

$totalQuery = $conn->query("
    SELECT COUNT(DISTINCT si.item_code) AS total
    FROM tbl_admin_stationary_stock_in si
    LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
    $where
");
$totalFiltered = $totalQuery->fetch_assoc()['total'];

$dataQuery = $conn->query("
    SELECT 
        si.item_code,
        pm.item_description,
        SUM(si.remaining_quantity) AS remaining_qty,
        SUM(si.remaining_quantity * si.unit_price) AS stock_value
    FROM tbl_admin_stationary_stock_in si
    LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
    $where
    GROUP BY si.item_code
    ORDER BY remaining_qty DESC
    LIMIT $start, $length
");

$data = [];
while ($row = $dataQuery->fetch_assoc()) {
    $data[] = [
        $row['item_code'],
        $row['item_description'],
        number_format($row['remaining_qty']),
        number_format($row['stock_value'], 2)
    ];
}

echo json_encode([
    "draw" => intval($_GET['draw']),
    "recordsTotal" => intval($totalFiltered),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
?>
