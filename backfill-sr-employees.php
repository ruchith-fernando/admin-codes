<?php
include 'connections/connection.php';
include 'includes/sr-generator.php';

$result = $conn->query("SELECT id FROM tbl_admin_actual_postage_stamps WHERE sr_number IS NULL ORDER BY id ASC");

$count = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $sr = generate_sr_number($conn, 'tbl_admin_actual_postage_stamps', $id);
    if ($sr) {
        $count++;
    } else {
        $failed++;
    }
}

echo "
<div class='alert alert-success fw-bold'>
✅ Backfill Complete<br>
<ul>
  <li><strong>SRs Assigned:</strong> $count</li>
  <li><strong>Failed Attempts:</strong> $failed</li>
</ul>
</div>";

$conn->close();
?>
