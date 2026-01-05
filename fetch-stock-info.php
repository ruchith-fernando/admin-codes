<?php
// fetch-stock-info.php
require_once 'connections/connection.php';

if (!isset($_POST['item_code'])) {
    echo json_encode([]);
    exit;
}

$itemCode = $_POST['item_code'];

$sql = "
    SELECT 
        si.item_code,
        pm.item_description AS item_name,
        si.remaining_quantity AS stock_available,
        si.unit_price,
        si.sscl_amount,
        si.vat_amount,
        si.received_date
    FROM tbl_admin_stationary_stock_in si
    LEFT JOIN tbl_admin_print_stationary_master pm ON si.item_code = pm.item_code
    WHERE si.item_code = '$itemCode'
    ORDER BY si.received_date DESC
";

$result = mysqli_query($conn, $sql);
$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $unit_price = floatval($row['unit_price']);
        $sscl = floatval($row['sscl_amount']);
        $vat = floatval($row['vat_amount']);
        $stock_qty = floatval($row['stock_available']);

        $full_unit_price = $unit_price + $sscl + $vat;
        $stock_value = round($full_unit_price * $stock_qty, 2);

        $data[] = [
            'item_code'       => $row['item_code'],
            'item_name'       => $row['item_name'] ?? $row['item_code'],
            'stock_available' => $stock_qty,
            'unit_price'      => $unit_price,
            'sscl_amount'     => $sscl,
            'vat_amount'      => $vat,
            'received_date'   => $row['received_date'],
            'stock_value'     => $stock_value
        ];
    }
}

echo json_encode($data);
