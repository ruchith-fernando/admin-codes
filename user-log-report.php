<?php
// user-log-report.php
session_start();
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL);
require_once 'connections/connection.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/css/bootstrap-datepicker.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"></script>

<style>
  .font-size { font-size: 0.95rem; }
  .wrap-anywhere { white-space: normal !important; word-break: break-word; overflow-wrap: anywhere; }
  pre.minibox { max-height: 200px; overflow:auto; background:#f8f9fa; border:1px solid #e9ecef; padding:.5rem; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">User Log Report</h5>

      <!-- Filters -->
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label">From</label>
          <input type="text" id="from_date" class="form-control datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-3">
          <label class="form-label">To</label>
          <input type="text" id="to_date" class="form-control datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-3">
          <label class="form-label">Page (contains)</label>
          <input type="text" id="page_like" class="form-control" placeholder="e.g., login.php">
        </div>
        <div class="col-md-3">
          <label class="form-label">Search (user/hris/ip/action)</label>
          <input type="text" id="q" class="form-control" placeholder="Type to search...">
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button id="btnLoad" type="button" class="btn btn-primary">Load Report</button>
        <button id="btnReset" type="button" class="btn btn-outline-secondary">Reset</button>
        <?php if (isset($_SESSION['hris']) && $_SESSION['hris'] === '01006428'): ?>
            <button id="btnArchive" type="button" class="btn btn-danger">Archive Logs</button>
        <?php endif; ?>

        <button id="btnExport" type="button" class="btn btn-success ms-auto">Download CSV</button>
      </div>

      <div id="reportBody">
        <div class="text-center py-4 text-muted">
          Use the filters above and click <b>Load Report</b>.
        </div>
      </div>
    </div>
  </div>
  <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="archiveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Archive</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This will move all logs older than 6 months into the archive table.</p>
                <p class="text-danger mb-0"><strong>Are you sure you want to continue?</strong></p>
                <div id="archiveProgress" class="mt-3" style="display:none;">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <p class="mt-2 text-muted small">Archiving in progress...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmArchive" type="button" class="btn btn-danger">Confirm Archive</button>
            </div>
            </div>
        </div>
    </div>

</div>
<script>
(function ($) {
  'use strict';

  // === Initialize Datepickers ===
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    todayHighlight: true,
    autoclose: true
  });

  // === Helper: Collect filter values ===
  function getFilters() {
    return {
      from_date: $('#from_date').val(),
      to_date: $('#to_date').val(),
      page_like: $('#page_like').val(),
      q: $('#q').val(),
      page: 1,
      per_page: 25
    };
  }

  // === Fetch & Render Logs Table ===
  function fetchLogs(page = 1) {
    const filters = getFilters();
    filters.page = page;
    filters.per_page = $('#per_page').val() || 25;

    $('#reportBody').html('<div class="text-muted text-center py-4">Loading...</div>');

    $.ajax({
      url: 'user-log-fetch.php',
      method: 'POST',
      data: filters,
      dataType: 'html'
    })
    .done(html => {
      $('#reportBody').html(html);
    })
    .fail(xhr => {
      console.error('LOG FETCH ERROR:', xhr.status, xhr.statusText, xhr.responseText);
      showAlert('❌ Failed to load logs.', 'danger');
      $('#reportBody').html('<div class="alert alert-danger">❌ Failed to load logs.</div>');
    });
  }

  // === Helper: Show Bootstrap-style alert ===
  function showAlert(message, type = 'success') {
    const alertBox = $(`
      <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow" 
           style="z-index:9999; min-width: 300px;">
        <div>${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `);
    $('body').append(alertBox);
    setTimeout(() => alertBox.alert('close'), 4000);
  }

  // === MAIN EVENT HANDLERS ===
  $(document).ready(function () {

    // Load logs button
    $('#btnLoad').on('click', function () {
      fetchLogs(1);
    });

    // Reset filters
    $('#btnReset').on('click', function () {
      $('#from_date,#to_date,#page_like,#q').val('');
      $('#reportBody').html('<div class="text-center py-4 text-muted">Use the filters above and click <b>Load Report</b>.</div>');
    });

    // ✅ Pagination (same behavior as employee directory)
    $(document).off('click.page').on('click.page', '.page-btn', function (e) {
      e.stopPropagation();
      e.preventDefault();
      const pg = $(this).data('pg');
      if (pg) fetchLogs(pg);
    });

    // Change rows per page
    $(document).on('change', '#per_page', function () {
      fetchLogs(1);
    });

    // Export CSV
    $('#btnExport').on('click', function () {
      const qs = $.param(getFilters());
      window.location.href = 'user-log-export.php?' + qs;
    });

    // =============================
    // ARCHIVE FEATURE (admin only)
    // =============================
    $(document).on('click', '#btnArchive', function(){
      $('#archiveModal').modal('show');
    });

    $(document).on('click', '#confirmArchive', function(){
      const progressBar = $('#archiveProgress .progress-bar');
      $('#archiveProgress').show();
      $(this).prop('disabled', true);
      progressBar.css('width', '0%');

      let progress = 0;
      const simulate = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 100) progress = 100;
        progressBar.css('width', progress + '%');
      }, 250);

      $.ajax({
        url: 'archive-user-logs-ajax.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
          clearInterval(simulate);
          progressBar.css('width', '100%');
          $('#archiveProgress p').text('Archive completed!');

          // ✅ Force-close modal immediately after completion
          setTimeout(() => {
            $('#archiveModal').modal('hide'); // Close modal
            $('body').removeClass('modal-open'); // Force cleanup
            $('.modal-backdrop').remove(); // Remove dark overlay manually

            // Reset UI state for next time
            $('#archiveProgress').hide();
            $('#confirmArchive').prop('disabled', false);
            progressBar.css('width', '0%');
            $('#archiveProgress p').text('Archiving in progress...');

            // ✅ Refresh the logs table
            fetchLogs(1);

            // ✅ Show top-right success alert
            const type = response.status === 'success' ? 'success' : 'danger';
            showAlert(response.message || '✅ Archive completed successfully.', type);
          }, 700);
        },
        error: function(xhr) {
          clearInterval(simulate);
          $('#archiveProgress').hide();
          $('#confirmArchive').prop('disabled', false);

          // ✅ Force close modal even on failure
          $('#archiveModal').modal('hide');
          $('body').removeClass('modal-open');
          $('.modal-backdrop').remove();

          showAlert('❌ Archive failed: ' + (xhr.responseText || 'Server error'), 'danger');
        }
      });
    });



  });

})(jQuery);
</script>

