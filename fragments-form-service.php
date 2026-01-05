<?php
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;

$sql = "SELECT * FROM tbl_admin_vehicle_service WHERE id = ?";
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
    <h5 class="modal-title">Service Entry - Verify Details for Vehicle <?php echo $vehicleNumber; ?></h5>
</div>

<div class="modal-body">
    <form id="approveServiceForm">
        <input type="hidden" id="entry_id" value="<?= htmlspecialchars($_POST['id'] ?? '') ?>">


        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Vehicle Number</label>
                <input type="text" class="form-control" value="<?php echo $vehicleNumber; ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Service Date</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['service_date']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Meter Reading</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['meter_reading']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next Service Meter</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['next_service_meter']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Amount</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['amount']); ?>" readonly style="min-width: 200px;">
            </div>
            <div class="col-md-6">
                <label class="form-label">Driver Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['driver_name']); ?>" readonly>
            </div>

            <?php if (!empty($data['bill_file'])): ?>
                <div class="col-md-6">
                    <label class="form-label">Bill</label>
                    <a href="uploads/vehicle/<?php echo htmlspecialchars($data['bill_file']); ?>" target="_blank" class="btn btn-outline-primary w-100">View Bill</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-danger" id="rejectServiceBtn">Reject</button>
            <div>
                <button type="button" class="btn btn-secondary me-2" onclick="closeFormService()">Close</button>
                <button type="submit" class="btn btn-success">Approve & Save</button>
            </div>
        </div>
    </form>
</div>

<script>
window.closeFormService = function () {
    const modalEl = document.querySelector('.modal.show');
    if (modalEl) {
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.hide();
        }
    }
};
</script>
