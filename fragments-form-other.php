<?php
// fragments-form-other.php
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
$sql = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = ? AND maintenance_type = 'Other'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo '<div class="alert alert-danger">No record found.</div>';
    exit;
}

$vehicleNumber = htmlspecialchars($data['vehicle_number']);
?>

<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">Verify Other Maintenance for Vehicle <?php echo $vehicleNumber; ?></h5>
</div>

<div class="modal-body">
  <form id="approveOtherForm">
    <input type="hidden" id="entry_id" value="<?= htmlspecialchars($id) ?>">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Vehicle Number</label>
        <input type="text" class="form-control" value="<?php echo $vehicleNumber; ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Shop Name</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['shop_name']); ?>" readonly>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Problem Description</label>
        <textarea class="form-control bg-light" readonly><?php echo htmlspecialchars($data['problem_description']); ?></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">Repair Date</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['repair_date']); ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Price</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['price']); ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Driver Name</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['driver_name']); ?>" readonly>
      </div>

      <?php if (!empty($data['bill_upload'])): ?>
        <div class="col-md-6">
          <label class="form-label">Bill</label>
          <a href="<?php echo htmlspecialchars($data['bill_upload']); ?>" target="_blank" class="btn btn-outline-primary w-100">View Bill</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <button type="button" class="btn btn-danger" id="rejectMaintenanceBtn">Reject</button>
      <div>
        <button type="button" class="btn btn-secondary me-2" onclick="closeAcModal()">Close</button>
        <button type="submit" class="btn btn-success">Approve & Save</button>
      </div>
    </div>
  </form>
</div>
<script>

window.closeAcModal = function () {
  const modalEl = document.querySelector('.modal.show');
  if (modalEl) {
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) {
      modalInstance.hide();
    }
  }
};

</script>