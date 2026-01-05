<!-- fetch-order-items.php -->
<?php
include 'connections/connection.php';

$order_number = $_GET['order_number'] ?? '';

if ($order_number === '') {
    echo '<div class="text-danger">Invalid order number.</div>';
    exit;
}

// ✅ Fetch request_type separately (only once)
$typeSql = "SELECT request_type FROM tbl_admin_stationary_orders WHERE order_number = ? LIMIT 1";
$typeStmt = $conn->prepare($typeSql);
$typeStmt->bind_param("s", $order_number);
$typeStmt->execute();
$typeResult = $typeStmt->get_result();
$request_type = ($row = $typeResult->fetch_assoc()) ? $row['request_type'] : 'Unknown';

// ✅ Main Query WITHOUT JOIN to orders table (fixes duplication)
$sql = "SELECT s.id, s.item_code, s.quantity, s.branch_stock, s.issued_date, 
               m.item_description
        FROM tbl_admin_stationary_stock_out s
        LEFT JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
        WHERE s.order_number = ? AND s.status != 'deleted'
        ORDER BY s.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $order_number);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<div class="text-muted">No items found for this order.</div>';
    exit;
}
?>

<!-- ✅ Print Styles -->
<style>
  @media print {
    body {
      font-size: 11px !important;
    }

    .no-print, .btn, .update-qty-btn, .delete-item-btn {
      display: none !important;
    }

    .print-plain {
      border: none !important;
      background: transparent !important;
      box-shadow: none !important;
      color: black !important;
      padding-top: 0 !important;
      padding-bottom: 0 !important;
      font-size: inherit !important;
      pointer-events: none !important;
    }

    th, td {
      border: 1px solid #000 !important;
      font-size: 11px !important;
      padding: 4px !important;
    }

    table {
      font-size: 11px !important;
    }

    input {
      font-size: 11px !important;
    }
  }
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Request Type</th>
        <th>Item Code</th>
        <th>Description</th>
        <th>Branch Stock</th>
        <th>Quantity</th>
        <th>Request Date</th>
        <th class="no-print">Actions</th>
      </tr>
    </thead>
    <tbody>

<?php
while ($row = $res->fetch_assoc()) {
    echo '<tr>
            <td>' . ucfirst(str_replace('_', ' ', $request_type)) . '</td>
            <td>' . htmlspecialchars($row['item_code']) . '</td>
            <td>' . htmlspecialchars($row['item_description'] ?? '-') . '</td>
            <td>' . htmlspecialchars($row['branch_stock'] ?? '-') . '</td>
            <td>
              <input type="number" class="form-control form-control-sm update-qty-input print-plain" 
                     data-id="' . $row['id'] . '" value="' . (int)$row['quantity'] . '" min="1">
            </td>
            <td>' . htmlspecialchars($row['issued_date']) . '</td>
            <td class="no-print">
              <button class="btn btn-sm btn-success update-qty-btn" data-id="' . $row['id'] . '">Update</button>
              <button class="btn btn-sm btn-danger delete-item-btn" data-id="' . $row['id'] . '">Delete</button>
            </td>
          </tr>';
}
?>

    </tbody>
  </table>
</div>

<script>
function prepareAndPrint() {
  document.querySelectorAll('input.update-qty-input').forEach(function (input) {
    const value = input.value;
    const span = document.createElement('span');
    span.textContent = value;
    span.style.fontSize = '11px';
    input.parentNode.replaceChild(span, input);
  });

  window.print();
}
</script>
