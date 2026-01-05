<?php
session_start();
require_once 'connections/connection.php';

if (!isset($_SESSION['hris'])) {
    echo "<div class='alert alert-danger'>Access denied.</div>";
    exit;
}

$hris = $_SESSION['hris'];
$id = intval($_POST['id'] ?? 0);

// Fetch the rejected maintenance record
$sql = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = $id AND entered_by = '$hris' AND status = 'Rejected'";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "<div class='alert alert-danger'>Record not found or access denied.</div>";
    exit;
}

$row = $result->fetch_assoc();
?>

<form id="editRejectedForm">
  <input type="hidden" name="id" value="<?= $row['id'] ?>">

  <div class="mb-3">
    <label class="form-label">Vehicle Number</label>
    <input type="text" class="form-control" name="vehicle_number" value="<?= htmlspecialchars($row['vehicle_number']) ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Maintenance Type</label>
    <input type="text" class="form-control" name="maintenance_type" value="<?= htmlspecialchars($row['maintenance_type']) ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Purchase Date</label>
    <input type="date" class="form-control" name="purchase_date" value="<?= $row['purchase_date'] ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Supplier</label>
    <input type="text" class="form-control" name="supplier" value="<?= htmlspecialchars($row['supplier']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Invoice No</label>
    <input type="text" class="form-control" name="invoice_no" value="<?= htmlspecialchars($row['invoice_no']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Price</label>
    <input type="number" step="0.01" class="form-control" name="price" value="<?= $row['price'] ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Warranty Period</label>
    <input type="text" class="form-control" name="warranty_period" value="<?= htmlspecialchars($row['warranty_period']) ?>">
  </div>

  <!-- Excluded fields: Warranty Mileage, Repair Date, Problem Description -->

  <div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-success">Resubmit</button>
  </div>
</form>

<script>
$('#editRejectedForm').on('submit', function (e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $.post('ajax-resubmit-rejected-maintenance.php', formData, function (res) {
        if (res.status === 'success') {
            $('#approvalModal').modal('hide');
            alert('Resubmitted successfully.');
            $('#maintenancePending').load('ajax-pending-maintenance.php');
        } else {
            alert(res.message || 'Failed to resubmit.');
        }
    }, 'json');
});
</script>
