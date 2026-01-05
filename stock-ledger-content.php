<?php
// stock-ledger-content.php
session_start();
include 'connections/connection.php';

$month = $_GET['month'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$item_code = $_GET['item_code'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$in_query = "SELECT item_code,
    (SELECT item_description FROM tbl_admin_print_stationary_master pm WHERE pm.item_code = si.item_code LIMIT 1) AS description,
    'IN' AS type,
    quantity,
    unit_price,
    (quantity * unit_price) AS total_value,
    received_date AS date,
    '' AS branch_name,
    si.created_by AS created_by,
    sscl_amount,
    vat_amount
FROM tbl_admin_stationary_stock_in si
WHERE 1";

$out_query = "SELECT item_code,
    (SELECT item_description FROM tbl_admin_print_stationary_master pm WHERE pm.item_code = so.item_code LIMIT 1) AS description,
    'OUT' AS type,
    approved_quantity AS quantity,
    unit_price,
    total_cost AS total_value,
    issued_date AS date,
    branch_name,
    so.created_by AS created_by,
    sscl_amount,
    vat_amount
FROM tbl_admin_stationary_stock_out so
WHERE dual_control_status = 'approved'";

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

$total_query = "SELECT COUNT(*) as total FROM ($query) as combined";
$total_result = $conn->query($total_query);
$total = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total / $limit);

$paged_query = "SELECT * FROM ($query) AS combined ORDER BY item_code, date ASC LIMIT $limit OFFSET $offset";
$result = $conn->query($paged_query);

// Fetch rates
$rate_result = $conn->query("SELECT vat_percentage, sscl_percentage FROM tbl_admin_vat_sscl_rates ORDER BY effective_date DESC LIMIT 1");
$rate_row = $rate_result->fetch_assoc();
$vat_rate = floatval($rate_row['vat_percentage'] ?? 0);
$sscl_rate = floatval($rate_row['sscl_percentage'] ?? 0);

$total_qty = $total_sub = $total_sscl = $total_vat = $total_value = 0;

echo '<div class="table-responsive table-scrollable">';
echo '<table class="table table-bordered font-size">';
echo '<thead class="table-light">
<tr>
<th>Item Code</th><th>Description</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Sub Value</th>
<th>SSCL</th><th>VAT</th><th>Total Value</th><th>Date</th><th>Branch</th><th>Entered By</th>
</tr></thead><tbody>';

while ($row = $result->fetch_assoc()) {
    $qty = floatval($row['quantity'] ?? 0);
    $unit_price = floatval($row['unit_price'] ?? 0);
    $sub_value = $qty * $unit_price;
    $sscl = ($sub_value * $sscl_rate) / 100;
    $vat = (($sub_value + $sscl) * $vat_rate) / 100;
    $total_row_value = $sub_value + $sscl + $vat;

    $total_qty += $qty;
    $total_sub += $sub_value;
    $total_sscl += $sscl;
    $total_vat += $vat;
    $total_value += $total_row_value;

    echo '<tr class="' . ($row['type'] === 'OUT' ? 'table-danger' : 'table-success') . '">';
    echo '<td>' . htmlspecialchars($row['item_code']) . '</td>';
    echo '<td>' . htmlspecialchars($row['description']) . '</td>';
    echo '<td>' . $row['type'] . '</td>';
    echo '<td>' . number_format($qty) . '</td>';
    echo '<td>' . number_format($unit_price, 2) . '</td>';
    echo '<td>' . number_format($sub_value, 2) . '</td>';
    echo '<td>' . number_format($sscl, 2) . '</td>';
    echo '<td>' . number_format($vat, 2) . '</td>';
    echo '<td>' . number_format($total_row_value, 2) . '</td>';
    echo '<td>' . htmlspecialchars($row['date']) . '</td>';
    echo '<td>' . htmlspecialchars($row['branch_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['created_by']) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

echo '<div class="d-flex justify-content-end mt-3 mb-3"><nav><ul class="pagination">';

if ($page > 1) {
    echo '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page - 1) . '">Previous</a></li>';
}

$start = max(1, $page - 2);
$end = min($total_pages, $page + 2);

for ($i = $start; $i <= $end; $i++) {
    $active = $i == $page ? 'active' : '';
    echo '<li class="page-item ' . $active . '">';
    echo '<a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a>';
    echo '</li>';
}

if ($page < $total_pages) {
    echo '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page + 1) . '">Next</a></li>';
}

echo '</ul></nav></div>';
