<?php
// vehicle-maintenance.php
require_once 'connections/connection.php';

$vehicleOptions = $conn->query("SELECT id, vehicle_number, assigned_user, make_model FROM tbl_admin_vehicle WHERE status = 'Approved' ORDER BY vehicle_number");
$driverOptions  = $conn->query("SELECT id, driver_name FROM tbl_admin_driver ORDER BY driver_name");
$driverData     = $driverOptions->fetch_all(MYSQLI_ASSOC);
?>

<style>
.form-section { display: none; }
.select2-container .select2-selection--single { height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Vehicle Maintenance / Service / License Entry</h5>

      <div id="vm-alerts"></div>

      <div class="mb-3">
        <label class="form-label">Select Entry Type</label>
        <select id="entryType" class="form-select">
          <option value="">-- Select --</option>
          <option value="maintenance">Maintenance</option>
          <option value="service">Service</option>
          <option value="license">Licensing and Insurance</option>
        </select>
      </div>

      <div class="mb-3 form-section" id="vehicleSelector">
        <label class="form-label">Select Vehicle</label>
        <select id="vehicle_id" class="form-select select2">
          <option value="">-- Select Vehicle --</option>
          <?php while ($v = $vehicleOptions->fetch_assoc()): ?>
            <option value="<?= $v['id'] ?>">
              <?= $v['vehicle_number'] ?> - <?= $v['make_model'] ?><?= $v['assigned_user'] ? ' - ' . htmlspecialchars($v['assigned_user']) : '' ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div id="noEmissionAlert" class="alert alert-danger mt-3" style="display: none;">
        No Emission Test Required for Hybrid or Electric vehicles.
      </div>

      <!-- Maintenance Section -->
      <div class="form-section" id="maintenanceSection">
        <form id="maintenanceForm" method="POST" action="submit-maintenance.php" enctype="multipart/form-data">
          <input type="hidden" name="vehicle_id" id="maintenanceVehicleId">

          <div class="mb-3">
            <label class="form-label">Maintenance Type</label>
            <select id="maintenanceType" name="maintenance_type" class="form-select" required>
              <option value="">-- Select Type --</option>
              <option value="battery">Battery</option>
              <option value="tire">Tire</option>
              <option value="ac">AC</option>
              <option value="running_repairs">Running Repairs</option>
            </select>
          </div>

          <div id="maintenanceDetails" class="mt-3"></div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Submit Maintenance Entry</button>
          </div>
        </form>
      </div>

      <!-- Service Section -->
      <div class="form-section" id="serviceSection">
        <form id="serviceForm" method="POST" action="submit-service.php" enctype="multipart/form-data">
          <input type="hidden" name="vehicle_id" id="serviceVehicleId">

          <div class="row g-3">
            <div class="col-md-4">
              <label>Date</label>
              <input type="text" name="service_date" class="form-control datepicker past-date" required>
            </div>

            <div class="col-md-4">
              <label>Meter Reading</label>
              <input type="text" name="meter_reading" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label>Next Service Meter</label>
              <input type="text" name="next_service_meter" class="form-control" required>
            </div>

            <!-- ✅ NEW: Shop/Garage -->
            <div class="col-md-4">
              <label>Shop / Garage</label>
              <select name="shop_name" class="form-select shop-select" required style="width:100%;">
                <option value="">-- Select or Add Shop --</option>
              </select>
            </div>

            <div class="col-md-4">
              <label>Amount</label>
              <input type="text" name="amount" class="form-control price-field" required>
            </div>

            <div class="col-md-4">
              <label>Driver</label>
              <select name="driver_id" class="form-select" required>
                <option value="">-- Select Driver --</option>
                <?php foreach ($driverData as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['driver_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label>Upload Bill <small class="text-danger">(Optional)</small></label>
              <input type="file" name="bill_file" class="form-control">
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Submit Service Entry</button>
          </div>
        </form>
      </div>

      <!-- License Section -->
      <div class="form-section" id="licenseSection">
        <form id="licenseForm" method="POST" action="submit-license.php" enctype="multipart/form-data">
          <input type="hidden" name="vehicle_id" id="licenseVehicleId">

          <div class="row g-3">
            <div class="col-md-4 emission-fields">
              <label>Emission Test Date</label>
              <input type="text" name="emission_date" class="form-control datepicker past-date">
            </div>

            <div class="col-md-4 emission-fields">
              <label>Emission Test Charges</label>
              <input type="text" name="emission_amount" class="form-control price-field">
            </div>

            <div class="col-md-4">
              <label>Revenue License Date</label>
              <input type="text" name="revenue_date" class="form-control datepicker past-date" required>
            </div>

            <div class="col-md-4">
              <label>Renewal of Revenue License Amount</label>
              <input type="text" name="revenue_amount" class="form-control price-field" required>
            </div>

            <div class="col-md-4">
              <label>Renewal of Insurance Amount</label>
              <input type="text" name="insurance_amount" class="form-control price-field" required>
            </div>

            <div class="col-md-4">
              <label>Person Involved</label>
              <select name="driver_id" class="form-select" required>
                <option value="">-- Select Driver --</option>
                <?php foreach ($driverData as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['driver_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Submit License Entry</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
// Kill any rogue alert popups
window.alert = function() {};

// ---- Utility: Bootstrap alert renderer ----
function renderAlert(payload, container = '#vm-alerts') {
  const { status='error', title='Message', message='', details=[], sr_number=null } = payload || {};
  const type = status === 'success' ? 'success' : (status === 'duplicate' ? 'warning' : 'danger');
  const lines = (details || []).map(d => `<li>${d}</li>`).join('');
  const sr = sr_number ? `<div><strong>SR No:</strong> ${sr_number}</div>` : '';
  const html = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      <div class="fw-bold">${title}</div>
      <div>${message || ''}</div>
      ${sr}
      ${lines ? `<ul class="mt-2 mb-0 small">${lines}</ul>` : ''}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
  const $c = $(container);
  $c.html(html);
  $c[0]?.scrollIntoView({behavior:'smooth', block:'start'});
}

// ---- Utility: datepicker & price field ----
function initDatePickers() {
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  });
  // "past-date" and old "future-date-maintenance" both mean "no future dates allowed"
  $('.past-date, .future-date-maintenance').datepicker('setEndDate', new Date());
}

function initPriceFormatting() {
  // prevent duplicate delegated handlers
  $(document).off('input.pricefmt', '.price-field');
  $(document).on('input.pricefmt', '.price-field', function () {
    let val = this.value.replace(/,/g, '').replace(/[^\d.]/g, '');
    if (!val) { this.value = ''; return; }
    const parts = val.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    this.value = parts.join('.');
  });
}

function initShopSelect() {
  $('.shop-select').select2({
    placeholder: '-- Select or Add Shop --',
    tags: true,
    ajax: {
      url: 'ajax-get-shops.php',
      dataType: 'json',
      delay: 250,
      data: function (params) { return { term: params.term }; },
      processResults: function (data) { return { results: data }; },
      cache: true
    },
    width: '100%'
  });
}

// ---- Tire dynamic builder (Maintenance -> Tire) ----
function rebuildTireRows() {
  const n = parseInt($('#tire_quantity').val() || '0', 10);
  let html = '';
  for (let i = 1; i <= n; i++) {
    html += `
      <div class="col-md-6">
        <label>Tire ${i} Brand</label>
        <input type="text" name="tire_items[${i}][brand]" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label>Tire ${i} Price</label>
        <input type="text" name="tire_items[${i}][price]" class="form-control price-field" inputmode="decimal" required>
      </div>`;
  }
  $('#tireItemsWrap').html(html);
}

// ---- Utility: universal AJAX form submit ----
function postFormJSON(formEl) {
  const fd = new FormData(formEl);
  return $.ajax({
    url: formEl.action,
    method: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    cache: false
  }).then(res => {
    if (typeof res === 'string') {
      try { res = JSON.parse(res); }
      catch(e) { res = {status:'error', title:'Invalid Response', message:'Server sent non-JSON.'}; }
    }
    res.title = res.title || (res.status === 'success' ? 'Success' :
                              res.status === 'duplicate' ? 'Duplicate' : 'Error');
    res.message = res.message || '';
    return res;
  }, xhr => {
    let msg = 'Network/Server error.';
    if (xhr.responseText) { try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e){} }
    return {status:'error', title:'Request Failed', message: msg};
  });
}

// ---- Utility: reset UI after successful submit ----
function resetAfterSuccess(form) {
  form.reset?.();
  $(form).find('.select2').val(null).trigger('change');
  $(form).find('.shop-select').val(null).trigger('change');

  $('#maintenanceDetails').empty();
  $('#noEmissionAlert').hide();

  $('#vehicle_id').val('').trigger('change');
  $('#entryType').val('').trigger('change');
}

// ---- CORE FIX: safe single-binding for AJAX submit ----
function bindAjaxForm(formSel) {
  $(document).off('submit', formSel);
  $(document).on('submit', formSel, function(e){
    e.preventDefault();

    const form = this;
    const $btn = $(form).find('[type=submit]');
    if ($btn.prop('disabled')) return;

    $btn.prop('disabled', true);
    postFormJSON(form).then(res => {
      renderAlert(res);
      if (res.status === 'success') resetAfterSuccess(form);
    }).always(() => {
      setTimeout(() => $btn.prop('disabled', false), 500);
    });
  });
}

$(function () {
  $('.select2').select2({ width: '100%' });
  initDatePickers();
  initPriceFormatting();
  initShopSelect();

  // section toggling
  $('.form-section').hide();
  $('#entryType').on('change', function(){
    $('.form-section').hide();
    const type = $(this).val();
    if (type==='maintenance') $('#vehicleSelector, #maintenanceSection').show();
    if (type==='service')     $('#vehicleSelector, #serviceSection').show();
    if (type==='license')     $('#vehicleSelector, #licenseSection').show();
  });

  // vehicle selector logic
  $('#vehicle_id').on('change', function(){
    const vid = $(this).val();
    $('#maintenanceVehicleId, #serviceVehicleId, #licenseVehicleId').val(vid);
    $('#noEmissionAlert').hide();
    if (vid) {
      $.get('get-vehicle-fuel-type.php', { vehicle_id: vid }, function(ft){
        const fuel = (ft || '').trim().toLowerCase();
        if (fuel === 'hybrid' || fuel === 'electric') {
          $('.emission-fields').hide().find('input').val('');
          $('#noEmissionAlert').show();
        } else {
          $('.emission-fields').show();
        }
      });
    }
  });

  // load maintenance subform
  $('#maintenanceType').on('change', function(){
    const type = $(this).val();
    if (type) {
      $.get('maintenance-form-fragment.php', { type }, function(res){
        $('#maintenanceDetails').html(res);

        // init widgets on newly injected DOM
        initDatePickers();
        initPriceFormatting();
        initShopSelect();

        // if tire fragment loaded and quantity already selected, build rows
        rebuildTireRows();
      });
    } else {
      $('#maintenanceDetails').empty();
    }
  });

  // delegated handler for dynamic tire quantity select
  $(document).off('change.tireqty', '#tire_quantity');
  $(document).on('change.tireqty', '#tire_quantity', rebuildTireRows);

  bindAjaxForm('#maintenanceForm');
  bindAjaxForm('#serviceForm');
  bindAjaxForm('#licenseForm');
});
</script>
