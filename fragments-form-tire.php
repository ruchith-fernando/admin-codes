<?php
// fragments-form-tire.php
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
$sql = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = ? AND maintenance_type = 'Tire'";
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
    <h5 class="modal-title">Maintenance Entry - Verify Tire Details for Vehicle <?php echo $vehicleNumber; ?></h5>
    <!-- Removed close (X) button to prevent outside dismissal -->
</div>

<div class="modal-body">
    <form id="approveTireForm">
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

            <div class="col-md-6">
                <label class="form-label">Tire Size</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['tire_size']); ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Purchase Date</label>
                <input type="text" class="form-control" value="<?php echo $data['purchase_date']; ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Quantity</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['tire_quantity']); ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Driver Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['driver_name']); ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Make</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['make']); ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Warranty (Km)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['warranty_mileage']); ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Price</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['price']); ?>" readonly>
            </div>

            <?php if (!empty($data['bill_upload'])): ?>
            <div class="col-md-6">
                <label class="form-label">Bill</label>
                <a href="<?php echo $data['bill_upload']; ?>" target="_blank" class="btn btn-outline-primary w-100">View Bill</a>
            </div>
            <?php endif; ?>

            <?php if (!empty($data['warranty_card_upload'])): ?>
            <div class="col-md-6">
                <label class="form-label">Warranty Card</label>
                <a href="<?php echo $data['warranty_card_upload']; ?>" target="_blank" class="btn btn-outline-primary w-100">View Warranty</a>
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