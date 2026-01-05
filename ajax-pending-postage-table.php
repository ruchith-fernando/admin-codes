<?php
// ajax-pending-postage-table.php
include 'connections/connection.php';

$entries = $conn->query("SELECT * FROM tbl_admin_actual_postage_stamps WHERE postal_serial_number IS NULL ORDER BY entry_date DESC");
?>

<h5 class="mb-4 text-primary">Pending Postal Serial Entries</h5>
<table class="table table-bordered">
  <thead>
    <tr>
      <th>Serial No</th>
      <th>Date</th>
      <th>Department</th>
      <th>Number of Letters</th>
      <th><strong>Total Spent (Rs.)</strong></th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $entries->fetch_assoc()): ?>
      <?php
        $totalSpent = (float)$row['open_balance'] - (float)$row['end_balance'];
      ?>
      <tr>
        <td><?= htmlspecialchars($row['serial_number']) ?></td>
        <td><?= htmlspecialchars($row['entry_date']) ?></td>
        <td><?= htmlspecialchars($row['department']) ?></td>
        <td><?= (int)$row['total'] ?></td>
        <td><?= number_format($totalSpent, 2) ?></td>
        <td>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#serialModal" data-id="<?= $row['id'] ?>">
            Enter Postal Serial
          </button>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
