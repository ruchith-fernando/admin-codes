<?php
$search = trim($_GET['search'] ?? '');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="text-primary mb-4">Transport Report - Kangaroo</h5>

      <form method="GET" class="row g-3 align-items-end mb-4" id="kangarooFilterForm">
        <div class="col-md-3">
          <label for="from" class="form-label">From Date</label>
          <input type="text" id="from" name="from" class="form-control datepicker" autocomplete="off">
        </div>
        <div class="col-md-3">
          <label for="to" class="form-label">To Date</label>
          <input type="text" id="to" name="to" class="form-control datepicker" autocomplete="off">
        </div>
        <div class="col-md-3">
          <label for="search" class="form-label">Search (Voucher / Vehicle)</label>
          <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Type to search">
        </div>
        <div class="col-md-3 d-grid">
          <label class="form-label invisible">Export</label>
          <a id="exportBtn" class="btn btn-success" target="_blank">
            <i class="bi bi-file-earmark-excel"></i> Download as Excel
          </a>
        </div>
      </form>

      <div id="kangarooLoader" class="text-center my-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

      <div class="table-responsive" id="kangarooReportTable">
        <!-- AJAX content appears here -->
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  });

  function updateExportLink(from, to, search) {
    const query = $.param({ from, to, search });
    $('#exportBtn').attr('href', 'export-kangaroo-report-excel.php?' + query);
  }

  function loadKangarooReport(page = 1) {
    const from = $('#from').val();
    const to = $('#to').val();
    const search = $('#search').val();

    $('#kangarooLoader').show();
    $('#kangarooReportTable').html('');

    $.ajax({
      url: 'ajax-kangaroo-transport-report.php',
      method: 'GET',
      data: { from, to, search, page },
      success: function (data) {
        $('#kangarooReportTable').html(data);
        updateExportLink(from, to, search);
      },
      error: function () {
        $('#kangarooReportTable').html('<div class="alert alert-danger">Failed to load report.</div>');
      },
      complete: function () {
        $('#kangarooLoader').hide();
      }
    });
  }

  // Initial load
  loadKangarooReport();

  // Pagination click
  $(document).on('click', '.pagination-link', function (e) {
    e.preventDefault();
    const page = $(this).data('page');
    loadKangarooReport(page);
  });

  $('#from, #to').on('change', () => loadKangarooReport());
  $('#search').on('keyup', function () {
    clearTimeout($.data(this, 'timer'));
    const wait = setTimeout(loadKangarooReport, 400);
    $(this).data('timer', wait);
  });
});
</script>
