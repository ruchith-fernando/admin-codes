<?php
// ajax-load-pending-orders.php
include 'connections/connection.php';

// Fetch only unique order numbers by grouping
$sql = "SELECT order_number, branch_code, branch_name, requested_date, request_type
        FROM tbl_admin_stationary_orders
        WHERE status = 'draft'
        GROUP BY order_number
        ORDER BY requested_date DESC";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo '<div class="text-muted">No pending orders found.</div>';
    exit;
}
?>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Order Number</th>
        <th>Branch Code</th>
        <th>Branch Name</th>
        <th>Request Type</th>
        <th>Requested Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['order_number']) ?></td>
        <td><?= htmlspecialchars($row['branch_code']) ?></td>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= ucfirst(str_replace('_', ' ', $row['request_type'])) ?></td>
        <td><?= htmlspecialchars($row['requested_date']) ?></td>
        <td>
          <button class="btn btn-sm btn-primary open-approval-modal" 
                  data-order="<?= htmlspecialchars($row['order_number']) ?>">
            View & Approve
          </button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
