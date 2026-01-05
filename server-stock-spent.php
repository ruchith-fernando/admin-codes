<?php
include 'connections/connection.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$search = $_GET['search']['value'];
$start = $_GET['start'];
$length = $_GET['length'];

$where = "WHERE DATE_FORMAT(so.issued_date, '%Y-%m') = '$month'";
if (!empty($search)) {
    $where .= " AND (so.item_code LIKE '%$search%' OR pm.item_description LIKE '%$search%')";
}

$totalQuery = $conn->query("
    SELECT COUNT(DISTINCT so.item_code) AS total
    FROM tbl_admin_stationary_stock_out so
    LEFT JOIN tbl_admin_print_stationary_master pm ON so.item_code = pm.item_code
    $where
");
$totalFiltered = $totalQuery->fetch_assoc()['total'];

$dataQuery = $conn->query("
    SELECT 
        so.item_code,
        pm.item_description,
        SUM(so.quantity) AS total_issued,
        SUM(so.total_cost) AS spent
    FROM tbl_admin_stationary_stock_out so
    LEFT JOIN tbl_admin_print_stationary_master pm ON so.item_code = pm.item_code
    $where
    GROUP BY so.item_code
    ORDER BY total_issued DESC
    LIMIT $start, $length
");

$data = [];
while ($row = $dataQuery->fetch_assoc()) {
    $data[] = [
        $row['item_code'],
        $row['item_description'],
        number_format($row['total_issued']),
        number_format($row['spent'], 2)
    ];
}

echo json_encode([
    "draw" => intval($_GET['draw']),
    "recordsTotal" => intval($totalFiltered),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
?>
