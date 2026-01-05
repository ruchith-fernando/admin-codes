<?php include 'connections/connection.php'; ?>
<!-- view-vehicle-information.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h5 class="text-primary mb-0">Approved Vehicle Records</h5>
        <button id="btnDownloadExcel" class="btn btn-success btn-sm">
          <i class="bi bi-file-earmark-excel"></i> Download Excel
        </button>
      </div>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <input type="text" id="vehicleSearch" class="form-control"
               placeholder="Search Vehicle No, Make/Model, User, Type, etc."
               style="max-width: 600px;">
      </div>

      <div id="vehicleTableArea" class="mt-3"></div>
    </div>
  </div>
</div>

<script>
(function ($) {
  'use strict';
  let timer = null;

  // === Load table ===
  function loadVehicles(page = 1) {
    const search = $('#vehicleSearch').val();
    $('#vehicleTableArea').html('<div class="text-muted">Loading...</div>');
    $.ajax({
      url: 'vehicle-ajax.php',
      method: 'GET',
      data: { page, search },
      dataType: 'html'
    })
    .done(html => $('#vehicleTableArea').html(html))
    .fail(xhr => {
      console.error('VEHICLE TABLE ERROR:', xhr.status, xhr.statusText);
      $('#vehicleTableArea').html('<div class="alert alert-danger">❌ Failed to load page.</div>');
    });
  }

  $(document).ready(function () {
    // initial load
    loadVehicles();

    // live search
    $('#vehicleSearch').on('keyup', function () {
      clearTimeout(timer);
      timer = setTimeout(() => loadVehicles(1), 300);
    });

    // ✅ pagination (no hrefs)
    $(document).off('click.page').on('click.page', '.page-btn', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const pg = $(this).data('pg');
      if (pg) loadVehicles(pg);
    });

    // ✅ Excel export
    $(document).on('click', '#btnDownloadExcel', function () {
      const search = $('#vehicleSearch').val();
      window.location = 'download-vehicles-csv.php?search=' + encodeURIComponent(search);
    });
  });
})(jQuery);
</script>
