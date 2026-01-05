<?php
// vehicle-history.php
session_start();
require_once 'connections/connection.php';
$vehicleQuery = "SELECT id, vehicle_number, assigned_user FROM tbl_admin_vehicle ORDER BY vehicle_number";
$vehicleOptions = $conn->query($vehicleQuery);
?>
<style>
.select2-container .select2-selection--single { height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">View Vehicle Records</h5>

      <!-- Vehicle Selector -->
      <div class="mb-3">
        <label>Select Vehicle</label>
        <select id="vehicle_id" class="form-select select2">
          <option value="">-- Select Vehicle --</option>
          <?php while ($v = $vehicleOptions->fetch_assoc()): ?>
            <option value="<?= $v['id'] ?>">
              <?= htmlspecialchars($v['vehicle_number']) ?>
              <?= $v['assigned_user'] ? ' - ' . htmlspecialchars($v['assigned_user']) : '' ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- AJAX-loaded content -->
      <div id="vehicleCards"></div>
    </div>
  </div>
</div>

<!-- UNIVERSAL POPUP MODAL -->
<div class="modal fade" id="imagePdfModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" id="modalContent"></div>
    </div>
  </div>
</div>


<script>
window.initPage = function () {
  console.log("🚗 vehicle-history.js initPage running");

  // Initialize Select2 safely
  if ($.fn.select2) {
    $('#vehicle_id').select2({
      placeholder: "-- Select Vehicle --",
      allowClear: true,
      width: '100%',
      dropdownParent: $('#contentArea')
    });
  }

  // === Vehicle selector ===
  $('#vehicle_id').off('change').on('change', function () {
    const vehicleId = $(this).val();
    if (vehicleId) {
      $('#vehicleCards').html('<div class="text-center p-4">Loading...</div>');
      loadAllVehicleData(vehicleId);
    } else {
      $('#vehicleCards').html('');
    }
  });

  // === Pagination click handler ===
  $(document).off('click.page').on('click.page', '.page-btn', function () {
    const page = $(this).data('pg');
    const type = $(this).data('type');
    const vehicleId = $('#vehicle_id').val();
    if (!vehicleId || !type) return;
    loadVehicleSection(vehicleId, type, page);
  });

  // === Load all vehicle sections ===
  function loadAllVehicleData(vehicleId) {
    $('#vehicleCards').html('<div class="text-center p-4">Loading vehicle details...</div>');
    $.when(
      loadVehicleSection(vehicleId, 'maintenance', 1),
      loadVehicleSection(vehicleId, 'service', 1),
      loadVehicleSection(vehicleId, 'license', 1)
    ).done(function (m, s, l) {
      $('#vehicleCards').html(`
        <div class="mb-4">
          <h6 class="text-primary">Maintenance History</h6>
          <div id="maintenanceSection">${m[0]}</div>
        </div>
        <div class="mb-4">
          <h6 class="text-primary">Service History</h6>
          <div id="serviceSection">${s[0]}</div>
        </div>
        <div>
          <h6 class="text-primary">License & Insurance</h6>
          <div id="licenseSection">${l[0]}</div>
        </div>
      `);
    }).fail(() => $('#vehicleCards').html('<div class="alert alert-danger">Error loading vehicle data.</div>'));
  }

  // === Load a single section ===
  function loadVehicleSection(vehicleId, type, page = 1) {
    let url = '', container = '';
    switch (type) {
      case 'maintenance': url = 'ajax-get-maintenance.php'; container = '#maintenanceSection'; break;
      case 'service':     url = 'ajax-get-service.php'; container = '#serviceSection'; break;
      case 'license':     url = 'ajax-get-license.php'; container = '#licenseSection'; break;
    }
    $(container).html('<div class="text-center p-3">Loading...</div>');
    return $.ajax({
      url, method: 'GET',
      data: { vehicle_id: vehicleId, page },
      success: data => $(container).html(data),
      error: xhr => {
        console.error(xhr.responseText);
        $(container).html('<div class="alert alert-danger">Failed to load data.</div>');
      }
    });
  }
};

// If loaded directly (not via main.php AJAX), still run initPage()
$(document).ready(function(){
  if (typeof window.initPage === 'function') window.initPage();
});

// === UNIVERSAL POPUP HANDLER FOR IMAGES & PDFs ===
$(document).off('click.preview').on('click.preview', '.preview-file', function () {
    const file = $(this).data('file');
    const ext = file.split('.').pop().toLowerCase();

    // Build content based on file type
    let content = "";

    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
        content = `<img src="${file}" class="img-fluid rounded">`;
    }
    else if (ext === 'pdf') {
        content = `<embed src="${file}" type="application/pdf" width="100%" height="600px">`;
    }
    else {
        content = `<p class="text-danger">Preview not supported for this file.</p>`;
    }

    $("#modalContent").html(content);
    $("#imagePdfModal").modal("show");
});

</script>
