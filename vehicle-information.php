<?php
session_start();
require_once 'connections/connection.php';

$hris        = $_SESSION['hris'];
$user_level  = $_SESSION['user_level'];
?>

<style>
.select2-container--default .select2-selection--single {
    height:38px!important;padding:6px 12px;border:1px solid #ced4da;border-radius:.375rem
}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:24px}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:36px;top:1px;right:6px}
.small-alert{display:none;font-size:.85rem;margin-top:4px;padding:4px 8px}
.vehicle-row{cursor:pointer}

#vehicleForm.disabled {
  pointer-events: none;
  opacity: 0.6;
}
</style>

<div class="content font-size" id="contentArea">
<div class="container-fluid my-4">
<div class="card shadow bg-white rounded p-4">
<h4 class="mb-4 text-primary">Vehicle Entry & Management</h4>

<!-- ─── Form ─────────────────────────────────────────────── -->
<form id="vehicleForm">
<div class="row g-3">

  <div class="col-md-6">
    <label>Vehicle Type</label>
    <select name="vehicle_type" class="form-select" required>
      <option value="">-- Select Category --</option>
      <option value="CMM Vehicles">CMM Vehicles</option>
      <option value="Company Bikes">Company Bikes</option>
      <option value="Award Winner">Award Winner</option>
      <option value="Company Vehicles">Company Vehicles</option>
      <option value="Promotion Vehicles">Promotion Vehicles</option>
    </select>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Vehicle Number</label>
    <input type="text" name="vehicle_number" class="form-control" required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Chassis Number</label>
    <input type="text" name="chassis_number" class="form-control" required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Make and Model</label>
    <input type="text" name="make_model" class="form-control" required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <!-- Fuel Type then Engine Capacity -->
  <div class="col-md-6">
    <label>Fuel Type</label>
    <select name="fuel_type" id="fuel_type" class="form-select" required>
      <option value="">-- Select Fuel Type --</option>
      <option value="Hybrid">Hybrid</option>
      <option value="Electric">Electric</option>
      <option value="Fuel">Fuel</option>
    </select>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label id="engineLabel">Engine Capacity (cc)</label>
    <input type="text" name="engine_capacity" id="engine_capacity" class="form-control" required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Year of Manufacture</label>
    <input
      type="text"
      name="year_of_manufacture"
      class="form-control"
      inputmode="numeric"
      maxlength="4"
      pattern="^(19\d{2}|20([0-1]\d|2\d))$"
      title="Enter a valid year between 1900 and 2029"
      required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <!-- Purchase Date (your JS expects this) -->
  <div class="col-md-6">
    <label>Purchase Date</label>
    <input type="text" name="purchase_date" id="purchase_date" class="form-control" autocomplete="off" required>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <!-- ✅ ONLY ONE Purchase Value (with checkbox) -->
  <div class="col-md-6">
    <label>Purchase Value</label>
    <div class="input-group">
      <input type="text" name="purchase_value" id="purchaseValue" class="form-control" placeholder="e.g. 1,250,000" required>
      <span class="input-group-text">
        <input class="form-check-input mt-0" type="checkbox" id="purchaseValueNA">
        <span class="ms-2">Not available</span>
      </span>
    </div>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Original Mileage</label>

    <div class="input-group">
      <input type="text" name="original_mileage" id="originalMileage" class="form-control" placeholder="e.g. 12500" required>
      <span class="input-group-text">
        <input class="form-check-input mt-0" type="checkbox" id="originalMileageNA">
        <span class="ms-2">Not available</span>
      </span>
    </div>

    <div class="alert alert-danger small-alert"></div>
  </div>


  <div class="col-md-6">
    <label>Assigned User</label>
    <select name="assigned_user_hris" id="assigned_user_hris" class="form-select" required></select>
    <div class="alert alert-danger small-alert"></div>
  </div>

  <div class="col-md-6">
    <label>Vehicle Category</label>
    <select name="vehicle_category" class="form-select" required>
      <option value="">-- Select Vehicle Category --</option>
      <option value="SUV">SUV</option>
      <option value="Car">Car</option>
      <option value="Bike">Bike</option>
      <option value="Bus">Bus</option>
      <option value="Van">Van</option>
      <option value="Lorry">Lorry</option>
    </select>
    <div class="alert alert-danger small-alert"></div>
  </div>

</div>

<div class="mt-4 mb-4">
  <button type="submit" class="btn btn-primary">Submit Vehicle</button>
</div>
</form>

<hr class="my-4">

<!-- ─── Table ────────────────────────────────────────────── -->
<h5 class="text-primary mb-3">All Vehicles</h5>
<input type="text" id="searchVehicles" class="form-control mb-3" placeholder="Search vehicles...">
<table class="table table-bordered table-hover" id="vehiclesTable">
  <thead class="table-light">
    <tr>
      <th>SR #</th><th>Vehicle Number</th><th>Type</th><th>Category</th>
      <th>Assigned User</th><th>Year</th><th>Status</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
<nav><ul class="pagination" id="vehiclePagination"></ul></nav>

</div>
</div>
</div>

<!-- ─── Modal ────────────────────────────────────────────── -->
<div class="modal fade" id="vehicleDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Vehicle Details</h5>
        <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="vehicleDetailsBody"></div>
    </div>
  </div>
</div>

<script>
$(function(){

  // helper: show alert for a field (works even inside input-group)
  function showFieldError($field, msg){
    const $alert = $field.closest('.col-md-6').find('.small-alert').first();
    $alert.text(msg).show();
  }

  // ─── Auto Uppercase for Vehicle & Chassis Numbers ─────────────
  $('input[name="vehicle_number"], input[name="chassis_number"]').on('input', function() {
    this.value = this.value.toUpperCase();
  });

  // ─── Year of Manufacture (digits only + 4 chars) ─────────────
  $('input[name="year_of_manufacture"]').on('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
  });

  // ─── Check for duplicate vehicle number ──────────────────────
  $('input[name="vehicle_number"]').on('blur', function() {
    const vehicleNumber = $(this).val().trim();
    if (vehicleNumber === '') return;

    $.ajax({
      url: 'check-duplicate-vehicle.php',
      type: 'POST',
      data: { vehicle_number: vehicleNumber },
      dataType: 'json',
      success: function(response) {
        $('#vehicleForm .alert-duplicate').remove();

        if (response.exists) {
          const v = response.vehicle;
          const details = `
            <ul class="mb-0 mt-2">
              <li><b>Type:</b> ${v.vehicle_type || '—'}</li>
              <li><b>Category:</b> ${v.vehicle_category || '—'}</li>
              <li><b>Assigned User:</b> ${v.assigned_user || '—'}</li>
              <li><b>Status:</b> ${v.status || '—'}</li>
            </ul>
          `;

          $('#vehicleForm').before(`
            <div class="alert alert-danger alert-duplicate alert-dismissible fade show mt-3" role="alert">
              <strong>Duplicate Found!</strong> Vehicle number <b>${vehicleNumber}</b> already exists in the system.
              ${details}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          `);

          $('#vehicleForm')
            .find(':input')
            .not('[name="vehicle_number"]')
            .each(function () {
              if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).prop('disabled', true).trigger('change.select2');
              } else {
                $(this).prop('disabled', true);
              }
            });

          $('input[name="vehicle_number"]').addClass('is-invalid');
          $('#vehicleForm').addClass('disabled');

        } else {
          $('#vehicleForm').removeClass('disabled');
          $('#vehicleForm').find(':input').prop('disabled', false);
          $('input[name="vehicle_number"]').removeClass('is-invalid');
        }
      },
      error: function() {
        console.error("Error checking duplicate vehicle number.");
      }
    });
  });

  // ─── Datepicker ──────────────────────────────────────────────
  $('#purchase_date')
    .datepicker({
      format:'yyyy-mm-dd',
      endDate:new Date(),
      autoclose:true,
      todayHighlight:true
    })
    .datepicker('setDate', new Date());

  // ─── Fuel Type → Engine Label ────────────────────────────────
  $('#fuel_type').on('change', function(){
    const val = $(this).val();
    if(val === 'Electric'){
      $('#engineLabel').text('Power (kW)');
      $('#engine_capacity').attr('placeholder', 'Enter power in kW');
    } else {
      $('#engineLabel').text('Engine Capacity (cc)');
      $('#engine_capacity').attr('placeholder', 'Enter engine capacity in cc');
    }
  });

  // ─── Purchase Value formatting + Not Available toggle ─────────
  function formatMoneyWithCommas(val){
    let cleaned = val.replace(/,/g, '').replace(/[^\d.]/g, '');
    const parts = cleaned.split('.');
    if (parts.length > 2) cleaned = parts[0] + '.' + parts.slice(1).join('');
    if (cleaned === '') return '';

    const [intPart, decPart] = cleaned.split('.');
    const intFormatted = Number(intPart || 0).toLocaleString('en-US');

    if (decPart !== undefined) return intFormatted + '.' + decPart.slice(0, 2);
    return intFormatted;
  }

  $('#purchaseValue').on('input', function(){
    if ($('#purchaseValueNA').is(':checked')) return;
    this.value = formatMoneyWithCommas(this.value);
  });

  $('#purchaseValueNA').on('change', function(){
    const isNA = $(this).is(':checked');
    const $pv = $('#purchaseValue');
    const $alert = $pv.closest('.col-md-6').find('.small-alert').first();

    if (isNA) {
      $pv.val('NOT_AVAILABLE').prop('readonly', true);
      $alert.hide();
    } else {
      $pv.val('').prop('readonly', false).focus();
    }
  });

  // ─── Original Mileage (digits only, NO thousand separators) ───
  $('#originalMileage').on('input', function(){
    if ($('#originalMileageNA').is(':checked')) return;
    this.value = this.value.replace(/\D/g, ''); // digits only, no commas
  });

  $('#originalMileageNA').on('change', function(){
    const isNA = $(this).is(':checked');
    const $m = $('#originalMileage');
    const $alert = $m.closest('.col-md-6').find('.small-alert').first();

    if (isNA) {
      $m.val('NOT_AVAILABLE').prop('readonly', true);
      $alert.hide();
    } else {
      $m.val('').prop('readonly', false).focus();
    }
  });

  // Auto-dismiss alerts after 5 seconds
  $(document).on('DOMNodeInserted', '.alert-msg', function() {
    setTimeout(() => {
      $(this).alert('close');
    }, 5000);
  });

  // ─── Form Submission ─────────────────────────────────────────
  $('#vehicleForm').on('submit', function(e){
    e.preventDefault();
    $('.small-alert').hide();
    let ok = true;

    // Required checks (works for both normal inputs and input-groups)
    $('#vehicleForm [required]').each(function(){
      const $f = $(this);
      if(!$f.val()){
        ok = false;
        showFieldError($f, 'This field is required');
      }
    });

    // Year range validation 1900–2029
    const y = $('input[name="year_of_manufacture"]').val().trim();
    if (!/^(19\d{2}|20([0-1]\d|2\d))$/.test(y)) {
      ok = false;
      showFieldError($('input[name="year_of_manufacture"]'), 'Year must be between 1900 and 2029');
    }

    // Purchase value validation + normalize (remove commas before sending)
    let pv = $('#purchaseValue').val().trim();
    if ($('#purchaseValueNA').is(':checked')) {
      pv = 'NOT_AVAILABLE';
    } else {
      pv = pv.replace(/,/g,'');
      if (pv === '' || isNaN(pv)) {
        ok = false;
        showFieldError($('#purchaseValue'), 'Enter a valid number or tick "Not available"');
      }
    }

    // Original mileage validation + normalize (digits only, but still clean just in case)
    let om = $('#originalMileage').val().trim();
    if ($('#originalMileageNA').is(':checked')) {
      om = 'NOT_AVAILABLE';
    } else {
      om = om.replace(/,/g,'');
      if (om === '' || isNaN(om)) {
        ok = false;
        showFieldError($('#originalMileage'), 'Enter a valid mileage or tick "Not available"');
      }
    }

    if(!ok) return;

    // Serialize and override purchase_value + original_mileage safely
    let data = $(this).serializeArray();
    data = data.map(x => {
      if (x.name === 'purchase_value') return {name: x.name, value: pv};
      if (x.name === 'original_mileage') return {name: x.name, value: om};
      return x;
    });

    $.ajax({
      url: 'submit-vehicle.php',
      type: 'POST',
      data: $.param(data),
      dataType: 'json',
      success: function(r) {
        $('.alert-msg').remove();

        let alertClass = (r.status === 'success') ? 'alert-success' : 'alert-danger';
        let message = (r.status === 'success')
          ? `<strong>Success!</strong> Vehicle added successfully. SR: ${r.sr_number}`
          : `<strong>Error!</strong> ${r.message}`;

        $('#vehicleForm').before(`
          <div class="alert ${alertClass} alert-msg alert-dismissible fade show mt-3" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `);

        if (r.status === 'success') {
          $('#vehicleForm')[0].reset();

          // reset purchase date to today
          $('#purchase_date').datepicker('setDate', new Date());

          // reset purchase value NA state
          $('#purchaseValueNA').prop('checked', false);
          $('#purchaseValue').prop('readonly', false).val('');

          // reset original mileage NA state
          $('#originalMileageNA').prop('checked', false);
          $('#originalMileage').prop('readonly', false).val('');

          loadVehicles();
          $('html, body').animate({ scrollTop: $('#contentArea').offset().top - 50 }, 400);
        }
      }
    });
  });

  // ─── Load & Populate Table ───────────────────────────────────
  function loadVehicles(){
    $.getJSON('fetch-vehicles.php', function(d){
      populateTable(d);
    });
  }

  function populateTable(data, page=1){
    const per = 8;
    const start = (page - 1) * per;
    const end = start + per;
    const pdata = data.slice(start, end);
    const tb = $('#vehiclesTable tbody');
    tb.empty();

    if(!pdata.length){
      tb.append('<tr><td colspan="7" class="text-center text-muted">No records found</td></tr>');
      return;
    }

    $.each(pdata, function(_, v){
      tb.append(`
        <tr data-id="${v.id}" class="vehicle-row ${v.status === 'Approved' ? 'table-success' : 'table-warning'}">
          <td>${v.sr_number || '—'}</td>
          <td>${v.vehicle_number}</td>
          <td>${v.vehicle_type}</td>
          <td>${v.vehicle_category}</td>
          <td>${v.assigned_user || '—'}</td>
          <td>${v.year_of_manufacture || '—'}</td>
          <td><span class="badge ${v.status === 'Approved' ? 'bg-success' : 'bg-warning text-dark'}">${v.status}</span></td>
        </tr>
      `);
    });

    const pages = Math.ceil(data.length / per);
    const pg = $('#vehiclePagination').empty();
    for(let i = 1; i <= pages; i++){
      pg.append(`<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#">${i}</a></li>`);
    }
    pg.find('a').on('click', function(e){
      e.preventDefault();
      populateTable(data, parseInt($(this).text(), 10));
    });

    $('.vehicle-row').on('click', function(){
      viewVehicleDetails($(this).data('id'));
    });
  }

  // ─── Keystroke Search ────────────────────────────────────────
  $('#searchVehicles').on('keyup', function(){
    const val = $(this).val().toLowerCase();
    $('#vehiclesTable tbody tr').filter(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
    });
  });

  // ─── Modal Details ───────────────────────────────────────────
  function viewVehicleDetails(id){
    $.getJSON('fetch-vehicle-details.php', {id:id}, function(v){
      if(!v) return;

      const unit = v.fuel_type === 'Electric' ? 'kW' : 'cc';
      const eng = v.engine_capacity ? (v.engine_capacity + ' ' + unit) : '—';

      // Purchase value display
      let purchaseValue = '—';
      if (v.purchase_value === 'NOT_AVAILABLE') {
        purchaseValue = 'Not available';
      } else if (v.purchase_value) {
        const numeric = parseFloat(v.purchase_value.toString().replace(/,/g,''));
        if(!isNaN(numeric)){
          purchaseValue = 'LKR ' + numeric.toLocaleString('en-LK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }
      }

      // Original mileage display (NO commas)
      let mil = '—';
      if (v.original_mileage === 'NOT_AVAILABLE') {
        mil = 'Not available';
      } else if (v.original_mileage) {
        const milesNum = parseInt(v.original_mileage.toString().replace(/,/g,''), 10);
        if (!isNaN(milesNum)) mil = milesNum + ' km';
      }

      $('#vehicleDetailsBody').html(`
        <table class="table table-striped">
          <tr><th>SR Number</th><td>${v.sr_number || '—'}</td></tr>
          <tr><th>Vehicle Type</th><td>${v.vehicle_type}</td></tr>
          <tr><th>Vehicle Number</th><td>${v.vehicle_number}</td></tr>
          <tr><th>Chassis Number</th><td>${v.chassis_number || '—'}</td></tr>
          <tr><th>Make & Model</th><td>${v.make_model}</td></tr>
          <tr><th>Engine Capacity</th><td>${eng}</td></tr>
          <tr><th>Fuel Type</th><td>${v.fuel_type || '—'}</td></tr>
          <tr><th>Year of Manufacture</th><td>${v.year_of_manufacture || '—'}</td></tr>
          <tr><th>Purchase Date</th><td>${v.purchase_date}</td></tr>
          <tr><th>Purchase Value</th><td>${purchaseValue}</td></tr>
          <tr><th>Original Mileage</th><td>${mil}</td></tr>
          <tr><th>Assigned User</th><td>${v.assigned_user || '—'} (${v.assigned_user_hris || ''})</td></tr>
          <tr><th>Vehicle Category</th><td>${v.vehicle_category}</td></tr>
          <tr><th>Status</th><td><span class="badge ${v.status === 'Approved' ? 'bg-success' : 'bg-warning text-dark'}">${v.status}</span></td></tr>
          <tr><th>Created By</th><td>${v.created_by || '—'}</td></tr>
          <tr><th>Approved By</th><td>${v.approved_by || '—'}</td></tr>
          <tr><th>Approved At</th><td>${v.approved_at || '—'}</td></tr>
          <tr><th>Created At</th><td>${v.created_at}</td></tr>
        </table>
      `);
      $('#vehicleDetailsModal').modal('show');
    });
  }

  // ─── Assigned User Select2 ────────────────────────────────────
  $('#assigned_user_hris').select2({
    placeholder: "-- Select User --",
    minimumInputLength: 2,
    ajax: {
      url: 'search-employees.php',
      type: 'POST',
      dataType: 'json',
      delay: 250,
      data: params => ({search: params.term}),
      processResults: data => ({
        results: $.map(data, item => ({
          id: item.hris,
          text: item.display_name + ' (' + item.hris + ')'
        }))
      }),
      cache: true
    },
    width: '100%'
  });

  // ─── Initialize Table ─────────────────────────────────────────
  loadVehicles();
});
</script>
