<?php
// maintenance-form-fragment.php
require_once 'connections/connection.php';

$type = $_GET['type'] ?? '';
$type = trim($type);

$driverOptions = '';
$driverQuery = $conn->query("SELECT driver_name FROM tbl_admin_driver ORDER BY driver_name ASC");
while ($d = $driverQuery->fetch_assoc()) {
  $dn = htmlspecialchars($d['driver_name']);
  $driverOptions .= '<option value="'.$dn.'">'.$dn.'</option>';
}

$shopField = <<<HTML
<div class="col-md-6">
  <label>Shop</label>
  <select name="shop_name" class="form-select shop-select" required style="width:100%;">
    <option value="">-- Select or Add Shop --</option>
  </select>
</div>
HTML;

$html = '';

if ($type === 'battery') {

  $html = <<<HTML
  <div class="row g-3 mt-3">
    {$shopField}
    <div class="col-md-6"><label>Price</label><input type="text" name="battery_price" class="form-control price-field" inputmode="decimal" required></div>
    <div class="col-md-6"><label>Make</label><input type="text" name="battery_make" class="form-control" required></div>
    <div class="col-md-6"><label>Purchase Date</label><input type="text" name="battery_purchase_date" class="form-control datepicker past-date" required></div>
    <div class="col-md-6"><label>Warranty Period (months)</label><input type="text" name="battery_warranty" class="form-control" required></div>

    <div class="col-md-6">
      <label>Driver</label>
      <select name="battery_driver" class="form-select" required>
        <option value="">-- Select Driver --</option>
        {$driverOptions}
      </select>
    </div>

    <div class="col-md-6"><label>Mileage</label><input type="text" name="battery_mileage" class="form-control" required></div>
    <div class="col-md-6"><label>Upload Bill <small class="text-danger">(Optional)</small></label><input type="file" name="battery_bill" class="form-control"></div>
    <div class="col-md-6"><label>Warranty Card <small class="text-danger">(Optional)</small></label><input type="file" name="battery_warranty_card" class="form-control"></div>
  </div>
HTML;

} elseif ($type === 'tire') {

  $html = <<<HTML
  <div class="row g-3 mt-3">
    <div class="col-md-6"><label>Purchase Date</label><input type="text" name="tire_purchase_date" class="form-control datepicker past-date" required></div>
    <div class="col-md-6"><label>Tire Size</label><input type="text" name="tire_size" class="form-control" required></div>

    <div class="col-md-6">
      <label>Number of Tires Replaced</label>
      <select name="tire_quantity" id="tire_quantity" class="form-select" required>
        <option value="">-- Select --</option>
        <option value="1">1 Tire</option>
        <option value="2">2 Tires</option>
        <option value="3">3 Tires</option>
        <option value="4">4 Tires</option>
      </select>
    </div>

    <div class="col-md-6">
      <label>Wheel Alignment Amount <small class="text-muted">(if done)</small></label>
      <input type="text" name="wheel_alignment_amount" class="form-control price-field" inputmode="decimal">
    </div>

    {$shopField}

    <div class="col-md-6"><label>Warranty (km)</label><input type="text" name="tire_warranty_km" class="form-control" required></div>

    <div class="col-12">
      <div class="fw-bold mb-2">Tire Details (Brand + Price)</div>
      <div class="row g-3" id="tireItemsWrap"></div>
    </div>

    <div class="col-md-6">
      <label>Driver</label>
      <select name="tire_driver" class="form-select" required>
        <option value="">-- Select Driver --</option>
        {$driverOptions}
      </select>
    </div>

    <div class="col-md-6"><label>Mileage</label><input type="text" name="tire_mileage" class="form-control" required></div>
    <div class="col-md-6"><label>Upload Bill <small class="text-danger">(Optional)</small></label><input type="file" name="tire_bill" class="form-control"></div>
  </div>
HTML;

} elseif ($type === 'ac' || $type === 'running_repairs') {

  $prefixLabel = ($type === 'running_repairs') ? 'Running Repairs' : 'AC';
  $prefixName  = ($type === 'running_repairs') ? 'running_repairs' : 'ac';

  $html = <<<HTML
  <div class="row g-3 mt-3">
    <div class="col-md-6"><label>{$prefixLabel} Date</label><input type="text" name="{$prefixName}_repair_date" class="form-control datepicker past-date" required></div>
    {$shopField}
    <div class="col-md-6"><label>Problem Description</label><textarea name="{$prefixName}_problem" class="form-control" rows="3" required></textarea></div>
    <div class="col-md-6"><label>Amount</label><input type="text" name="{$prefixName}_amount" class="form-control price-field" inputmode="decimal" required></div>

    <div class="col-md-6">
      <label>Driver</label>
      <select name="{$prefixName}_driver" class="form-select" required>
        <option value="">-- Select Driver --</option>
        {$driverOptions}
      </select>
    </div>

    <div class="col-md-6"><label>Mileage</label><input type="text" name="{$prefixName}_mileage" class="form-control" required></div>
    <div class="col-md-6"><label>Upload Bill <small class="text-danger">(Optional)</small></label><input type="file" name="{$prefixName}_bill" class="form-control"></div>
  </div>
HTML;

} else {
  $html = '<div class="alert alert-warning">Invalid maintenance type.</div>';
}

echo $html;
