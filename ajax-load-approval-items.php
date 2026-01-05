<?php
// ajax-load-approval-items.php
include 'connections/connection.php';

$order_number = $_GET['order_number'] ?? '';
if ($order_number === '') {
    echo '<div class="text-danger">Invalid order number.</div>';
    exit;
}

// Merge same item_code rows by summing quantity
$sql = "SELECT s.item_code, 
               SUM(s.quantity) AS total_quantity, 
               MAX(s.branch_stock) AS branch_stock, 
               m.item_description, 
               o.request_type
        FROM tbl_admin_stationary_stock_out s
        JOIN tbl_admin_stationary_orders o ON s.order_number = o.order_number
        LEFT JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
        WHERE s.order_number = ? AND s.status != 'deleted'
        GROUP BY s.item_code, m.item_description, o.request_type
        ORDER BY MIN(s.created_at) ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $order_number);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<div class="text-muted">No items found for this order.</div>';
    exit;
}
?>

<!-- Modal alert -->
<div id="approvalModalAlert" class="alert d-none mx-auto mt-3 text-center px-4 py-2" style="max-width: 500px;"></div>

<form id="approvalForm">
  <input type="hidden" name="order_number" value="<?= htmlspecialchars($order_number) ?>">
  <table class="table table-bordered table-sm align-middle mt-3">
    <thead class="table-light">
      <tr>
        <th>Item Code</th>
        <th>Description</th>
        <th>Requested Qty</th>
        <th>Branch Stock</th>
        <th>Total Available Stock</th>
        <th>Approve Qty</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $res->fetch_assoc()): 
        $itemCode = htmlspecialchars($row['item_code']);
        $itemName = htmlspecialchars($row['item_description'] ?? '-');
        $requestedQty = (int)$row['total_quantity'];
        $branchStock = (int)$row['branch_stock'];

        // Get total available stock from stock-in table
        $stockStmt = $conn->prepare("SELECT SUM(remaining_quantity) AS total 
                                     FROM tbl_admin_stationary_stock_in 
                                     WHERE item_code = ? AND remaining_quantity > 0 AND status = 'approved'");
        $stockStmt->bind_param("s", $row['item_code']);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $stockRow = $stockResult->fetch_assoc();
        $available = (int)($stockRow['total'] ?? 0);
      ?>
      <tr>
        <td><?= $itemCode ?></td>
        <td><?= $itemName ?></td>
        <td><?= $requestedQty ?></td>
        <td><?= $branchStock ?></td>
        <td><?= $available ?></td>
        <td>
          <input type="number" class="form-control form-control-sm" 
                 name="approved_qty[<?= $itemCode ?>]"
                 value="<?= $requestedQty ?>" 
                 min="1" max="<?= $available ?>">
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <div class="mb-3 mt-3">
    <label>Remarks (for rejection)</label>
    <textarea class="form-control" name="remarks" rows="2"></textarea>
  </div>

  <div class="text-end">
    <button type="button" id="approveBtn" class="btn btn-success">Approve</button>
    <button type="button" id="rejectBtn" class="btn btn-danger ms-2">Reject</button>
  </div>
</form>
