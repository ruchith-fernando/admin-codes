<?php
require_once 'connections/connection.php';
$id = $_GET['id'] ?? 0;

$q = mysqli_query($conn, "SELECT * FROM tbl_admin_actual_water WHERE id = '$id'");
$r = mysqli_fetch_assoc($q);

if (!$r) {
    echo '<p class="text-danger text-center">Record not found.</p>';
    exit;
}
?>

<div class="text-start">
  <p><strong>Branch:</strong> <?= htmlspecialchars($r['branch']) ?></p>
  <p><strong>Month:</strong> <?= htmlspecialchars($r['month_applicable']) ?></p>
  <p><strong>Total Amount:</strong> Rs. <?= number_format($r['total_amount'], 2) ?></p>
  <p><strong>Provision:</strong> <?= $r['is_provision'] === 'yes' ? 'Yes' : 'No' ?></p>
  <p><strong>Reason:</strong> <?= htmlspecialchars($r['provision_reason']) ?></p>
  <p><strong>Entered By:</strong> <?= htmlspecialchars($r['entered_name']) ?> (<?= htmlspecialchars($r['entered_hris']) ?>)</p>
  <p><strong>Entered At:</strong> <?= htmlspecialchars($r['entered_at']) ?></p>

  <hr>
  <div class="text-center">
    <a href="water-approve.php?id=<?= $r['id'] ?>" class="btn btn-success me-2">Approve</a>
    <a href="water-reject.php?id=<?= $r['id'] ?>" class="btn btn-danger"
       onclick="return confirm('Reject this record?')">Reject</a>
  </div>
</div>
