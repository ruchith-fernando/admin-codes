<?php
include 'connections/connection.php';

$month = $_GET['month'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$item_code = $_GET['item_code'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Get latest VAT and SSCL rates
$rate_result = $conn->query("SELECT vat_percentage, sscl_percentage FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
$rate_row = $rate_result->fetch_assoc();
$vat_rate = floatval($rate_row['vat_percentage'] ?? 0);
$sscl_rate = floatval($rate_row['sscl_percentage'] ?? 0);

$in_query = "SELECT
    item_code,
    (SELECT item_description FROM tbl_admin_print_stationary_master pm WHERE pm.item_code = si.item_code LIMIT 1) AS description,
    'IN' AS type,
    quantity,
    unit_price,
    received_date AS date,
    '' AS branch_name,
    si.created_by AS created_by,
    NULL AS total_cost,
    sscl_amount,
    vat_amount
FROM tbl_admin_stationary_stock_in si
WHERE 1";

$out_query = "SELECT
    item_code,
    (SELECT item_description FROM tbl_admin_print_stationary_master pm WHERE pm.item_code = so.item_code LIMIT 1) AS description,
    'OUT' AS type,
    quantity,
    (total_cost / quantity) AS unit_price,
    issued_date AS date,
    branch_name,
    so.created_by AS created_by,
    total_cost,
    sscl_amount,
    vat_amount
FROM tbl_admin_stationary_stock_out so
WHERE 1";

if ($month) {
    $in_query .= " AND DATE_FORMAT(received_date, '%Y-%m') = '" . $conn->real_escape_string($month) . "'";
    $out_query .= " AND DATE_FORMAT(issued_date, '%Y-%m') = '" . $conn->real_escape_string($month) . "'";
}
if ($date_from && $date_to) {
    $in_query .= " AND received_date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "'";
    $out_query .= " AND issued_date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "'";
}
if ($item_code) {
    $in_query .= " AND item_code = '" . $conn->real_escape_string($item_code) . "'";
    $out_query .= " AND item_code = '" . $conn->real_escape_string($item_code) . "'";
}

if ($type_filter === 'IN') {
    $query = $in_query;
} elseif ($type_filter === 'OUT') {
    $query = $out_query;
} else {
    $query = "$in_query UNION ALL $out_query";
}

$query .= " ORDER BY item_code, date ASC";
$result = $conn->query($query);

// Excel headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=stock_ledger_report.xls");

// Table headers
echo "<table border='1'>";
echo "<tr>
    <th>Item Code</th>
    <th>Description</th>
    <th>Type</th>
    <th>Qty</th>
    <th>Unit Price</th>
    <th>Sub Value</th>
    <th>SSCL</th>
    <th>VAT</th>
    <th>Total Value</th>
    <th>Received / Issued Date</th>
    <th>Branch</th>
    <th>Entered By</th>
</tr>";

// Table rows
while ($row = $result->fetch_assoc()) {
    $qty = floatval($row['quantity']);
    $unit_price = floatval($row['unit_price']);
    $sub_value = $qty * $unit_price;

    // Always calculate SSCL and VAT based on correct logic
    $sscl = round(($sub_value * $sscl_rate) / 100, 2);
    $vat = round((($sub_value + $sscl) * $vat_rate) / 100, 2);
    $total_value = $sub_value + $sscl + $vat;

    echo "<tr>";
    echo "<td>{$row['item_code']}</td>";
    echo "<td>{$row['description']}</td>";
    echo "<td>{$row['type']}</td>";
    echo "<td>" . number_format($qty) . "</td>";
    echo "<td>" . number_format($unit_price, 2) . "</td>";
    echo "<td>" . number_format($sub_value, 2) . "</td>";
    echo "<td>" . number_format($sscl, 2) . "</td>";
    echo "<td>" . number_format($vat, 2) . "</td>";
    echo "<td>" . number_format($total_value, 2) . "</td>";
    echo "<td>{$row['date']}</td>";
    echo "<td>{$row['branch_name']}</td>";
    echo "<td>{$row['created_by']}</td>";
    echo "</tr>";
}

echo "</table>";
?>
