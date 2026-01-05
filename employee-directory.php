<?php include 'connections/connection.php'; ?>
<!-- employee-directory.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">HR Employee Directory</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
          <input type="text" id="searchBox" class="form-control"
                placeholder="Search HRIS, Name, NIC, Location, Category, Status"
                style="max-width: 600px;">

          <!-- NEW Download CSV Button -->
          <button id="btnExport" class="btn btn-success btn-sm">
              Download CSV
          </button>
      </div>


      <div id="tableWrapper" class="mt-3"></div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="empModal" tabindex="-1" aria-labelledby="empModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="empModalLabel">Employee Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="empModalBody">Loading details...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  'use strict';
  let timer = null;

  // === Load table ===
  function fetchTable(page = 1) {
    const term = $('#searchBox').val();
    $('#tableWrapper').html('<div class="text-muted">Loading...</div>');
    $.ajax({
      url: 'employee-table.php',
      method: 'GET',
      data: { page, search: term },
      dataType: 'html'
    })
    .done(html => $('#tableWrapper').html(html))
    .fail(xhr => {
      console.error('TABLE LOAD ERROR:', xhr.status, xhr.statusText, xhr.responseText);
      $('#tableWrapper').html('<div class="alert alert-danger">❌ Failed to load page.</div>');
    });
  }

  // === Load employee details ===
  function fetchDetails(hris) {
    $('#empModalBody').html('Loading details...');
    $('#empModal').modal('show');
    $.ajax({
      url: 'employee-details.php',
      method: 'GET',
      data: { hris },
      dataType: 'html'
    })
    .done(html => $('#empModalBody').html(html))
    .fail(xhr => {
      console.error('DETAILS ERROR:', xhr.responseText);
      $('#empModalBody').html('<div class="text-danger">Failed to load details.</div>');
    });
  }

  $(document).ready(function () {
    fetchTable();

    // live search
    $('#searchBox').on('keyup', function () {
      clearTimeout(timer);
      timer = setTimeout(() => fetchTable(1), 300);
    });

    // ✅ fixed pagination
    $(document).off('click.page').on('click.page', '.page-btn', function (e) {
      e.stopPropagation();
      e.preventDefault();
      const pg = $(this).data('pg');
      if (pg) fetchTable(pg);
    });

    // row click
    $(document).on('click', '.emp-row', function () {
      const hris = $(this).data('hris');
      fetchDetails(hris);
    });

    // export (optional)
    $('#btnExport').on('click', function () {
      const term = $('#searchBox').val();
      window.location = 'employee-export.php?search=' + encodeURIComponent(term);
    });
  });
})(jQuery);
</script>
