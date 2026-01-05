<?php include 'connections/connection.php'; ?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">HR Employee Directory</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <input type="text" id="searchBox" class="form-control"
               placeholder="Search HRIS, Name, NIC, Location, Category, Status"
               style="max-width: 600px;">
        <button id="btnExport" class="btn btn-primary btn-sm">Download Excel</button>
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

  function fetchTable(page = 1) {
    const term = $('#searchBox').val();
    $('#tableWrapper').html('<div class="text-muted">Loading...</div>');
    $.get('employee-table.php', { page, search: term })
      .done(html => $('#tableWrapper').html(html))
      .fail(() => $('#tableWrapper').html('<div class="alert alert-danger">Failed to load data.</div>'));
  }

  function fetchDetails(hris) {
    $('#empModalBody').html('Loading details...');
    $('#empModal').modal('show');
    $.get('employee-details.php', { hris })
      .done(html => $('#empModalBody').html(html))
      .fail(() => $('#empModalBody').html('<div class="text-danger">Failed to load details.</div>'));
  }

  $(document).ready(function () {
    fetchTable();

    // Live search
    $('#searchBox').on('keyup', function () {
      clearTimeout(timer);
      timer = setTimeout(() => fetchTable(1), 300);
    });

    // Pagination
    $(document).on('click', '.page-link', function (e) {
      e.preventDefault();
      const p = $(this).data('page');
      if (p) fetchTable(p);
    });

    // Row click for details
    $(document).on('click', '.emp-row', function () {
      const hris = $(this).data('hris');
      fetchDetails(hris);
    });

    // Export
    $('#btnExport').on('click', function () {
      const term = $('#searchBox').val();
      window.location = 'employee-export.php?search=' + encodeURIComponent(term);
    });
  });
})(jQuery);
</script>
