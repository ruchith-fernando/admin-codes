<?php
// ajax-view-employee.php
include 'connections/connection.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  echo "<div class='modal-header'><h5 class='modal-title'>Invalid Request</h5></div>
        <div class='modal-body'>No valid ID received.</div>";
  exit;
}

$stmt = $conn->prepare("SELECT * FROM tbl_admin_employee_details WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
  echo "<div class='modal-header'><h5 class='modal-title'>Record Not Found</h5></div>
        <div class='modal-body'>No matching employee found for ID: $id</div>";
  exit;
}
?>

<div class="modal-header">
  <h5 class="modal-title">Employee Details: <?= htmlspecialchars($row['name_of_employee']) ?></h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
  <div class="table-responsive">
    <table class="table table-sm table-bordered">
      <?php foreach ($row as $field => $value): ?>
        <tr>
          <th><?= ucwords(str_replace("_", " ", $field)) ?></th>
          <td><?= htmlspecialchars($value) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
