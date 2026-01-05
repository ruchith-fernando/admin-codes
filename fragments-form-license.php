<?php
// fragments-form-licencse.php
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
$sql = "SELECT * FROM tbl_admin_vehicle_licensing_insurance WHERE id = ?";
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
    Vehicle License & Insurance - Verify Details for <?= htmlspecialchars($row['vehicle_number']) ?>
</div>

<form id="approveLicenseForm" class="p-3">
    <input type="hidden" name="id" id="entry_id" value="<?= $row['id'] ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Vehicle Number</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['vehicle_number']) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Emission Test Date</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['emission_test_date']) ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Emission Test Amount</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['emission_test_amount']) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Revenue License Date</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['revenue_license_date']) ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Revenue License Amount</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['revenue_license_amount']) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Insurance Amount</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['insurance_amount']) ?>" readonly>
        </div>

        <div class="col-md-12">
            <label class="form-label">Person Handled</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($row['person_handled']) ?>" readonly>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-between">
            <button type="button" class="btn btn-danger" id="rejectLicenseBtn">Reject</button>
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
