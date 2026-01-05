<?php
// ajax-get-vehicle-details.php
session_start();
require_once 'connections/connection.php';

$loggedHris = $_SESSION['hris'] ?? null;

if (!isset($_GET['id'])) {
  echo "<div class='alert alert-danger'>Invalid request: Missing ID.</div>";
  exit;
}

$id = intval($_GET['id']);
$query = "SELECT * FROM tbl_admin_vehicle WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<div class='alert alert-warning'>Vehicle not found.</div>";
  exit;
}

$v = $result->fetch_assoc();

// Fuel label logic
$engineLabel = ($v['fuel_type'] === 'Electric') ? 'Power (kW)' : 'Engine Capacity (cc)';

// Same-user check
$isSameUser = ($loggedHris && $v['created_by'] === $loggedHris);
?>

<style>
.small-alert {
  display: none;
  font-size: 0.85rem;
  margin-top: 4px;
  padding: 4px 8px;
}
</style>

<?php if ($isSameUser): ?>
<div class="alert alert-danger text-center fw-bold mb-3">
  You cannot approve a record you created.
</div>
<?php endif; ?>

<div class="row g-3">

  <div class="col-md-6">
    <label>Vehicle Type</label>
    <select name="vehicle_type" class="form-select" <?= $isSameUser ? 'disabled' : '' ?> required>
      <option value="">-- Select Category --</option>
      <?php
        $types = ['CMM Vehicles', 'Company Bikes', 'Award Winner', 'Company Vehicles', 'Promotion Vehicles'];
        foreach ($types as $t) {
          $sel = ($t === $v['vehicle_type']) ? 'selected' : '';
          echo "<option value='$t' $sel>$t</option>";
        }
      ?>
    </select>
  </div>

  <div class="col-md-6">
    <label>Vehicle Number</label>
    <input type="text" name="vehicle_number" class="form-control"
           value="<?= htmlspecialchars($v['vehicle_number']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Chassis Number</label>
    <input type="text" name="chassis_number" class="form-control"
           value="<?= htmlspecialchars($v['chassis_number']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Make & Model</label>
    <input type="text" name="make_model" class="form-control"
           value="<?= htmlspecialchars($v['make_model']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Fuel Type</label>
    <select name="fuel_type" id="fuel_type_modal" class="form-select" <?= $isSameUser ? 'disabled' : '' ?> required>
      <option value="">-- Select Fuel Type --</option>
      <?php
        $fuels = ['Hybrid', 'Electric', 'Fuel'];
        foreach ($fuels as $f) {
          $sel = ($f === $v['fuel_type']) ? 'selected' : '';
          echo "<option value='$f' $sel>$f</option>";
        }
      ?>
    </select>
  </div>

  <div class="col-md-6">
    <label id="engineLabel_modal"><?= $engineLabel ?></label>
    <input type="text" name="engine_capacity" id="engine_capacity_modal" class="form-control"
           value="<?= htmlspecialchars($v['engine_capacity']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Year of Manufacture</label>
    <input type="number" name="year_of_manufacture" class="form-control"
           value="<?= htmlspecialchars($v['year_of_manufacture']) ?>" min="1900" max="2099"
           <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Purchase Date</label>
    <input type="text" name="purchase_date" id="purchase_date_modal" class="form-control"
           value="<?= htmlspecialchars($v['purchase_date']) ?>" autocomplete="off"
           <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Purchase Value</label>
    <input type="text" name="purchase_value" id="purchase_value_modal" class="form-control"
           value="<?= htmlspecialchars(number_format((float)str_replace(',', '', $v['purchase_value']), 2)) ?>"
           <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Original Mileage</label>
    <input type="text" name="original_mileage" id="original_mileage_modal" class="form-control"
           value="<?= htmlspecialchars($v['original_mileage']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Assigned User HRIS</label>
    <input type="text" name="assigned_user" class="form-control"
           value="<?= htmlspecialchars($v['assigned_user_hris']) ?>" <?= $isSameUser ? 'readonly' : '' ?> required>
  </div>

  <div class="col-md-6">
    <label>Vehicle Category</label>
    <select name="vehicle_category" class="form-select" <?= $isSameUser ? 'disabled' : '' ?> required>
      <option value="">-- Select Vehicle Category --</option>
      <?php
        $cats = ['SUV', 'Car', 'Bike', 'Bus', 'Van', 'Lorry'];
        foreach ($cats as $c) {
          $sel = ($c === $v['vehicle_category']) ? 'selected' : '';
          echo "<option value='$c' $sel>$c</option>";
        }
      ?>
    </select>
  </div>

  <div class="col-md-6">
    <label>Entered By</label>
    <input type="text" class="form-control"
           value="<?= htmlspecialchars($v['created_by']) ?>" readonly>
  </div>

</div>

<input type="hidden" name="id" value="<?= intval($v['id']) ?>">

<script>
$(function() {
  $('#purchase_date_modal').datepicker({
    format: 'yyyy-mm-dd',
    endDate: new Date(),
    autoclose: true,
    todayHighlight: true
  });

  $('#fuel_type_modal').on('change', function() {
    const val = $(this).val();
    if (val === 'Electric') {
      $('#engineLabel_modal').text('Power (kW)');
      $('#engine_capacity_modal').attr('placeholder', 'Enter power in kW');
    } else {
      $('#engineLabel_modal').text('Engine Capacity (cc)');
      $('#engine_capacity_modal').attr('placeholder', 'Enter engine capacity in cc');
    }
  });

  // currency formatting (no 1000 separator for mileage)
  $('#purchase_value_modal').on('input', function() {
    let v = this.value.replace(/,/g, '');
    if (!isNaN(v) && v !== '') {
      this.value = parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  });

  $('#original_mileage_modal').on('input', function() {
    this.value = this.value.replace(/[^0-9.]/g, '');
  });

  <?php if ($isSameUser): ?>
    // disable Approve button in parent modal
    $('#vehicleApprovalForm button[type="submit"]').prop('disabled', true);
  <?php else: ?>
    $('#vehicleApprovalForm button[type="submit"]').prop('disabled', false);
  <?php endif; ?>
});
</script>
