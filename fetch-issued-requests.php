<?php
include 'connections/connection.php';
session_start();

if (!isset($_SESSION['hris']) || !isset($_SESSION['branch_code'])) {
    echo "<div class='alert alert-danger'>Session expired. Please login again.</div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_type'])) {
    $requestType = $_POST['request_type'];
    $branchCode = $_SESSION['branch_code'];

    $sql = "
        SELECT o.order_number, o.requested_date, o.status, o.request_type,
               i.item_code, i.quantity, m.item_description
        FROM tbl_admin_stationary_orders o
        JOIN tbl_admin_stationary_stock_out i ON o.order_number = i.order_number
        LEFT JOIN tbl_admin_print_stationary_master m ON i.item_code = m.item_code
        WHERE o.branch_code = ? 
          AND o.request_type = ?
          AND o.status = 'pending_dispatch'
        ORDER BY o.requested_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $branchCode, $requestType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<div class='text-muted'>No issued requests found.</div>";
        exit;
    }

    echo "<table class='table table-bordered table-sm'>
            <thead class='table-light'>
                <tr>
                    <th>Order #</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Requested Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['order_number']}</td>
                <td>{$row['item_description']} ({$row['item_code']})</td>
                <td>{$row['quantity']}</td>
                <td>{$row['requested_date']}</td>
                <td><span class='badge bg-success'>{$row['status']}</span></td>
              </tr>";
    }

    echo "</tbody></table>";
}
?>
