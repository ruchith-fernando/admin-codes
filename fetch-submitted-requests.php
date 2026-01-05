<?php
include 'connections/connection.php';
session_start();

// Debug: show session data
echo "<!-- DEBUG: Session branch_code = {$_SESSION['branch_code']} -->\n";

$branch_code = $_SESSION['branch_code'] ?? '';

if (empty($branch_code)) {
    echo '<div class="alert alert-danger">Session expired or branch_code missing.</div>';
    exit;
}

// SQL query with JOIN to orders and item master
$sql = "SELECT s.id, s.item_code, m.item_description, s.quantity, o.order_number, o.request_type, o.requested_date
        FROM tbl_admin_stationary_stock_out s
        LEFT JOIN tbl_admin_stationary_orders o ON s.order_number = o.order_number
        LEFT JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
        WHERE s.branch_code = '$branch_code'
        ORDER BY s.id DESC
        LIMIT 10";

// Debug: show query
echo "<!-- DEBUG SQL: $sql -->\n";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo '<div class="alert alert-danger">SQL Error: ' . mysqli_error($conn) . '</div>';
    exit;
}

if (mysqli_num_rows($result) > 0) {
    echo '<table class="table table-bordered table-sm">';
    echo '<thead><tr class="table-light">
            <th>#</th>
            <th>Order No.</th>
            <th>Request Type</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>Quantity</th>
            <th>Request Date</th>
          </tr></thead><tbody>';
    $i = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $item_code = htmlspecialchars($row['item_code']);
        $item_name = htmlspecialchars($row['item_description'] ?? 'N/A');
        $quantity = (int)$row['quantity'];
        $order_number = htmlspecialchars($row['order_number']);
        $request_type = $row['request_type'] === 'stationery_pack' ? 'Stationary Pack' : 'Daily Courier';
        $requested_date = htmlspecialchars($row['requested_date']);

        echo "<tr>
                <td>{$i}</td>
                <td>{$order_number}</td>
                <td>{$request_type}</td>
                <td>{$item_code}</td>
                <td>{$item_name}</td>
                <td>{$quantity}</td>
                <td>{$requested_date}</td>
              </tr>";
        $i++;
    }
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-warning">No requests submitted for your branch.</div>';
}
