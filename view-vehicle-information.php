<?php include 'connections/connection.php'; ?>
<!-- view-vehicle-information.php -->

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h5 class="text-primary mb-0">Approved Vehicle Records</h5>
        <button id="btnDownloadExcel" class="btn btn-success btn-sm" type="button">
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

  function loadVehicles(page = 1) {
    const search = $('#vehicleSearch').val().trim();

    $('#vehicleTableArea').html('<div class="text-muted">Loading...</div>');

    $.ajax({
      url: 'vehicle-ajax.php',
      method: 'GET',
      dataType: 'html',
      cache: false,                     // ✅ prevent cached pagination pages
      data: {
        page: page,
        search: search,
        _: Date.now()                   // ✅ strong cache buster
      }
    })
    .done(function (html) {
      $('#vehicleTableArea').html(html);
    })
    .fail(function (xhr) {
      console.error('VEHICLE TABLE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
      $('#vehicleTableArea').html(
        '<div class="alert alert-danger">❌ Failed to load page.</div>'
      );
    });
  }

  $(document).ready(function () {
    // initial load
    loadVehicles(1);

    // live search
    $('#vehicleSearch').on('keyup', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        loadVehicles(1);
      }, 300);
    });

    // ✅ pagination click (delegate INSIDE ajax container)
    $('#vehicleTableArea').on('click', '.page-btn', function (e) {
      e.preventDefault();
      const pg = parseInt($(this).attr('data-pg'), 10);
      if (!Number.isNaN(pg) && pg > 0) loadVehicles(pg);
    });

    // ✅ Excel/CSV export (keeps current search)
    $('#btnDownloadExcel').on('click', function () {
      const search = $('#vehicleSearch').val().trim();
      window.location = 'download-vehicles-csv.php?search=' + encodeURIComponent(search);
    });
  });

})(jQuery);
</script>
