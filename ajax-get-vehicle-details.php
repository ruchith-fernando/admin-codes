<?php
session_start();
require_once 'connections/connection.php';

$logged = $_SESSION['hris'] ?? '';
$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM tbl_admin_vehicle WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();

if (!$v) {
    echo "<div class='alert alert-danger'>Vehicle not found.</div>";
    exit;
}

$isSame = ($v['created_by'] === $logged);
$engineLabel = ($v['fuel_type'] === 'Electric') ? 'Power (kW)' : 'Engine Capacity (cc)';
?>

<div class="row g-3">

<!-- Vehicle Type -->
<div class="col-md-6">
  <label>Vehicle Type</label>
  <input class="form-control" value="<?= $v['vehicle_type'] ?>" readonly>
</div>

<!-- Vehicle Number -->
<div class="col-md-6">
  <label>Vehicle Number</label>
  <input class="form-control" value="<?= $v['vehicle_number'] ?>" readonly>
</div>

<!-- Chassis -->
<div class="col-md-6">
  <label>Chassis Number</label>
  <input class="form-control" value="<?= $v['chassis_number'] ?>" readonly>
</div>

<!-- Make Model -->
<div class="col-md-6">
  <label>Make & Model</label>
  <input class="form-control" value="<?= $v['make_model'] ?>" readonly>
</div>

<!-- Fuel -->
<div class="col-md-6">
  <label>Fuel Type</label>
  <input class="form-control" value="<?= $v['fuel_type'] ?>" readonly>
</div>

<!-- Engine -->
<div class="col-md-6">
  <label><?= $engineLabel ?></label>
  <input class="form-control" value="<?= $v['engine_capacity'] ?>" readonly>
</div>

<!-- Year -->
<div class="col-md-6">
  <label>Year of Manufacture</label>
  <input class="form-control" value="<?= $v['year_of_manufacture'] ?>" readonly>
</div>

<!-- Purchase Date -->
<div class="col-md-6">
  <label>Purchase Date</label>
  <input class="form-control" value="<?= $v['purchase_date'] ?>" readonly>
</div>

<!-- Purchase Value -->
<div class="col-md-6">
  <label>Purchase Value</label>
  <input class="form-control" value="<?= number_format((float)str_replace(',', '', $v['purchase_value']), 2) ?>" readonly>
</div>

<!-- Mileage -->
<div class="col-md-6">
  <label>Original Mileage</label>
  <input class="form-control" value="<?= $v['original_mileage'] ?> km" readonly>
</div>

<!-- Assigned User -->
<div class="col-md-6">
  <label>Assigned User</label>
  <input class="form-control" value="<?= $v['assigned_user'] ?>" readonly>
</div>

<!-- Assigned User HRIS -->
<div class="col-md-6">
  <label>Assigned User HRIS</label>
  <input class="form-control" value="<?= $v['assigned_user_hris'] ?>" readonly>
</div>

<!-- Category -->
<div class="col-md-6">
  <label>Vehicle Category</label>
  <input class="form-control" value="<?= $v['vehicle_category'] ?>" readonly>
</div>

<!-- Created By -->
<div class="col-md-6">
  <label>Entered By</label>
  <input class="form-control" value="<?= $v['created_by'] ?>" readonly>
</div>

</div>

<hr>

<?php if ($isSame): ?>
<div class="alert alert-danger text-center">
  You cannot approve your own record.
</div>
<script>
  $('#vehicleApprovalForm button[type="submit"]').prop('disabled', true);
</script>
<?php else: ?>
<script>
  $('#vehicleApprovalForm button[type="submit"]').prop('disabled', false);
</script>
<?php endif; ?>

<input type="hidden" name="id" value="<?= $v['id'] ?>">
