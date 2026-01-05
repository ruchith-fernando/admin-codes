<?php
// get-approval-form.php
require_once 'connections/connection.php';
session_start();

if (!in_array($_SESSION['user_level'], ['super-admin', 'verifier'])) {
    echo "<div class='alert alert-danger m-4'>Access denied.</div>";
    exit;
}

$id = $_GET['id'] ?? '';
$type = $_GET['type'] ?? '';

if (!$id || !$type) {
    echo "<div class='alert alert-danger m-4'>Invalid request parameters.</div>";
    exit;
}

$record = null;
$title = "";

switch ($type) {
    case 'maintenance':
        $stmt = $conn->prepare("SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = ?");
        $title = "Approve Maintenance Entry";
        break;
    case 'service':
        $stmt = $conn->prepare("SELECT * FROM tbl_admin_vehicle_service WHERE id = ?");
        $title = "Approve Service Entry";
        break;
    case 'license':
        $stmt = $conn->prepare("SELECT * FROM tbl_admin_vehicle_licensing_insurance WHERE id = ?");
        $title = "Approve License/Insurance Entry";
        break;
    default:
        echo "<div class='alert alert-danger m-4'>Invalid entry type.</div>";
        exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    echo "<div class='alert alert-warning m-4'>Record not found.</div>";
    exit;
}

function displayImageLinks($json) {
    $html = '';
    $files = json_decode($json, true);
    if ($files && is_array($files)) {
        foreach ($files as $path) {
            $name = basename($path);
            $html .= "<a href='{$path}' target='_blank' class='d-block mb-1'>📎 {$name}</a>";
        }
    }
    return $html ?: '<em>No files attached</em>';
}
?>

<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="approvalModalLabel"><?= $title ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="approveForm">
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= $record['id'] ?>">
          <input type="hidden" name="type" value="<?= $type ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label>Vehicle Number</label>
              <input type="text" class="form-control" name="vehicle_number" value="<?= htmlspecialchars($record['vehicle_number']) ?>" readonly>
            </div>
            <?php if ($type === 'maintenance'): ?>
              <div class="col-md-4">
                <label>Maintenance Type</label>
                <input type="text" class="form-control" value="<?= $record['maintenance_type'] ?>" readonly>
              </div>
              <div class="col-md-4">
                <label>Price</label>
                <input type="text" class="form-control" name="price" value="<?= $record['price'] ?>">
              </div>
              <div class="col-md-12">
                <label>Images / Attachments</label>
                <?= displayImageLinks($record['image_path']) ?>
              </div>
            <?php elseif ($type === 'service'): ?>
              <div class="col-md-4">
                <label>Service Date</label>
                <input type="text" class="form-control" name="service_date" value="<?= $record['service_date'] ?>">
              </div>
              <div class="col-md-4">
                <label>Meter Reading</label>
                <input type="text" class="form-control" name="meter_reading" value="<?= $record['meter_reading'] ?>">
              </div>
              <div class="col-md-4">
                <label>Amount</label>
                <input type="text" class="form-control" name="amount" value="<?= $record['amount'] ?>">
              </div>
              <div class="col-md-12">
                <label>Images / Attachments</label>
                <?= displayImageLinks($record['image_path']) ?>
              </div>
            <?php elseif ($type === 'license'): ?>
              <div class="col-md-4">
                <label>Revenue License Date</label>
                <input type="text" class="form-control" name="revenue_license_date" value="<?= $record['revenue_license_date'] ?>">
              </div>
              <div class="col-md-4">
                <label>Revenue License Amount</label>
                <input type="text" class="form-control" name="revenue_license_amount" value="<?= $record['revenue_license_amount'] ?>">
              </div>
              <div class="col-md-4">
                <label>Insurance Amount</label>
                <input type="text" class="form-control" name="insurance_amount" value="<?= $record['insurance_amount'] ?>">
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Approve & Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('#approveForm').on('submit', function (e) {
    e.preventDefault();
    const formData = $(this).serialize();
    $.post('approve-entry.php', formData, function (response) {
      $('#approvalModal').modal('hide');
      location.reload();
    }).fail(function (xhr) {
      alert('Error approving entry: ' + xhr.responseText);
    });
  });
});
</script>