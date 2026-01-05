<?php
include 'connections/connection.php';

$order_number = $_GET['order_number'] ?? '';

if ($order_number === '') {
    echo '<tr><td colspan="7" class="text-danger">Invalid order number.</td></tr>';
    exit;
}

$sql = "SELECT s.id, s.item_code, s.quantity, s.branch_stock, s.issued_date, 
               m.item_description, o.request_type
        FROM tbl_admin_stationary_stock_out s
        JOIN tbl_admin_stationary_orders o ON s.order_number = o.order_number
        LEFT JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
        WHERE s.order_number = ? AND s.status != 'deleted'
        ORDER BY s.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $order_number);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<tr><td colspan="7" class="text-muted">No items found.</td></tr>';
    exit;
}

while ($row = $res->fetch_assoc()) {
    echo '<tr>
            <td>' . ucfirst(str_replace('_', ' ', $row['request_type'])) . '</td>
            <td>' . htmlspecialchars($row['item_code']) . '</td>
            <td>' . htmlspecialchars($row['item_description'] ?? '-') . '</td>
            <td>' . htmlspecialchars($row['branch_stock'] ?? '-') . '</td>
            <td>
              <input type="number" class="form-control form-control-sm update-qty-input" 
                     data-id="' . $row['id'] . '" value="' . (int)$row['quantity'] . '" min="1">
            </td>
            <td>' . htmlspecialchars($row['issued_date']) . '</td>
            <td>
              <button class="btn btn-sm btn-success update-qty-btn" data-id="' . $row['id'] . '">Update</button>
              <button class="btn btn-sm btn-danger delete-item-btn" data-id="' . $row['id'] . '">Delete</button>
            </td>
          </tr>';
}
