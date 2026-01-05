<?php
// ajax-get-service-by-id.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'connections/connection.php';

$id = $_POST['id'] ?? 0;
if (!$id) {
  echo '<div class="alert alert-danger">Invalid ID.</div>';
  exit;
}

$query = "SELECT * FROM tbl_admin_vehicle_service WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
  echo '<div class="alert alert-warning">Record not found.</div>';
  exit;
}

$data = $result->fetch_assoc();
$vehicleNumber = htmlspecialchars($data['vehicle_number']);
?>

<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">Verify Service for Vehicle <?php echo $vehicleNumber; ?></h5>
</div>

<div class="modal-body">
  <form id="approveServiceForm">
    <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
    <input type="hidden" name="type" value="SERVICE">

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
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['amount']); ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Driver Name</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($data['driver_name']); ?>" readonly>
      </div>

      <?php if (!empty($data['bill_upload'])): ?>
        <div class="col-md-6">
          <label class="form-label">Bill</label>
          <a href="../uploads/vehicle/<?php echo $data['bill_upload']; ?>" target="_blank" class="btn btn-outline-primary w-100">View Bill</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <button type="button" class="btn btn-danger" onclick="rejectServiceEntry(<?php echo $data['id']; ?>)">Reject</button>
      <div>
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success">Approve</button>
      </div>
    </div>
  </form>
</div>

<script>
  $('#approveServiceForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    $.post('ajax-approve-service-entry.php', formData, function(response) {
      $('#simpleApprovalModal').modal('hide');
      $('body').append(response);
      loadPendingService(); // reload table
    });
  });
</script>
