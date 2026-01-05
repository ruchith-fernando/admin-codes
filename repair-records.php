<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-primary">Vehicle Repair Records</h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
      </div>

      <div class="row mb-3">
        <div class="col-md-3">
          <label for="start_date" class="form-label">Start Date</label>
          <input type="text" id="start_date" class="form-control datepicker" placeholder="Select start date" autocomplete="off">
        </div>
        <div class="col-md-3">
          <label for="end_date" class="form-label">End Date</label>
          <input type="text" id="end_date" class="form-control datepicker" placeholder="Select end date" autocomplete="off">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary" id="filter-report">Show Records</button>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary" id="download-csv">⬇️ Download CSV</button>
        </div>
      </div>

      <!-- 🔍 Keystroke search -->
      <div class="mb-3 d-flex">
        <input type="text" id="searchBox" class="form-control" placeholder="Search Vehicle, User, Description, etc.">
      </div>

      <div id="report-content">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div>Loading report...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  'use strict';
  let timer = null;

  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  });

  // === Load paginated report ===
  function loadRepairReport(page = 1) {
    const start = $('#start_date').val();
    const end   = $('#end_date').val();
    const search = $('#searchBox').val();

    $('#report-content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading...</div></div>');
    $.ajax({
      url: 'ajax-repair-report.php',
      method: 'GET',
      data: { page, start_date: start, end_date: end, search },
      dataType: 'html'
    })
    .done(html => $('#report-content').html(html))
    .fail(xhr => {
      $('#report-content').html('<div class="alert alert-danger">Error loading report</div>');
      console.error(xhr.responseText);
    });
  }

  $(document).ready(function () {
    loadRepairReport();

    $('#filter-report').on('click', () => loadRepairReport(1));

    $('#searchBox').on('keyup', function () {
      clearTimeout(timer);
      timer = setTimeout(() => loadRepairReport(1), 300);
    });

    $(document).off('click.page').on('click.page', '.page-btn', function (e) {
      e.preventDefault();
      const pg = $(this).data('pg');
      if (pg) loadRepairReport(pg);
    });

    $('#download-csv').on('click', function () {
      const start = $('#start_date').val();
      const end   = $('#end_date').val();
      const search = $('#searchBox').val();
      const params = $.param({ start_date: start, end_date: end, search });
      window.location = 'ajax-repair-export.php?' + params;
    });
  });
})(jQuery);
</script>
