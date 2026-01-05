<?php include 'connections/connection.php'; 
// hr-report-dialog.php
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">HR Mobile Allocation & Dialog Invoice Report</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <input type="text" id="searchInput" class="form-control"
               placeholder="Search Mobile Number, Name, HRIS, Billing Month"
               style="max-width: 600px;">
        <button onclick="exportData('excel')" class="btn btn-primary btn-sm">Download Excel</button>
      </div>

      <input type="hidden" id="searchHidden" value="">
      <div id="tableContainer" class="mt-3">Loading...</div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Invoice & Contribution Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBodyContent">Loading details...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function (w, $) {
  'use strict';

  let typingTimer = null;
  let inited = false;
  let slowLoaderTimer = null;

  function startSlowLoaderDelay() {
    clearTimeout(slowLoaderTimer);
    slowLoaderTimer = setTimeout(function() {
      if (typeof w.showLoader === 'function') w.showLoader();
    }, 600);
  }

  function stopSlowLoader() {
    clearTimeout(slowLoaderTimer);
    slowLoaderTimer = null;
    if (typeof w.hideLoader === 'function') w.hideLoader();
  }

  function loadTable(page = 1) {
    const search = $('#searchInput').val();
    $('#searchHidden').val(search);
    $('#tableContainer').html('<div class="text-muted">Loading...</div>');

    startSlowLoaderDelay();

    $.ajax({
      url: 'table-hr-report-dialog.php',
      data: { page, search },
      method: 'GET'
    })
    .done(function (html) {
      $('#tableContainer').html(html);
    })
    .fail(function (xhr) {
      const body = xhr.responseText && xhr.responseText.trim() ? xhr.responseText : 'Failed to load data.';
      $('#tableContainer').html('<div class="alert alert-danger" style="white-space:pre-wrap;">' + body + '</div>');
    })
    .always(function () {
      stopSlowLoader();
    });
  }

  function exportData(type) {
    const search = $('#searchInput').val();
    const url = 'export-hr-report-dialog-excel.php';
    w.location.href = url + '?search=' + encodeURIComponent(search);
  }
  w.exportData = exportData;

  w.initPage = function initHRReport() {
    if (inited) return;
    inited = true;

    $(document).off('.hrReport');
    $('#detailModal').off('.hrReport');

    loadTable(1);

    $(document).on('keyup.hrReport', '#searchInput', function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => loadTable(1), 300);
    });

    $(document).on('click.hrReport', '.page-link', function (e) {
      e.preventDefault();
      const page = $(this).data('pg');
      if (page) loadTable(page);
    });

    // 🔹 Row click handler (updated)
    $(document).on('click.hrReport', '.table-row', function () {
      const row = $(this).data();
      const html = `
        <strong>Mobile Number:</strong> ${row.mobile}<br>
        <strong>Employee:</strong> ${row.employee}<br>
        <strong>HRIS:</strong> ${row.hris}<br>
        <strong>Designation:</strong> ${row.designation}<br>
        <strong>Bill Period:</strong> ${row.period}<br><br>
        <strong>Total Payable:</strong> Rs. ${row.total}<br>
        <strong>Contribution:</strong> Rs. ${row.contribution}<br>
        <strong>Salary Deduction:</strong> Rs. ${row.deduction}<br>

        <div id="invoiceDetails">Loading detailed bill...</div>
      `;
      $('#modalBodyContent').html(html);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();

      // Load detailed bill via AJAX
      $.ajax({
        url: 'table-hr-report-dialog-detail.php',
        method: 'GET',
        data: { mobile: row.mobile, billing_month: row.period }
      })
      .done(function (html) {
        $('#invoiceDetails').html(html);
      })
      .fail(function () {
        $('#invoiceDetails').html('<div class="text-danger">Failed to load detailed bill.</div>');
      });
    });

    $('#detailModal').on('hidden.bs.modal.hrReport', function () {
      $('#modalBodyContent').html('Loading details...');
    });

    setTimeout(() => $('#searchInput').trigger('focus'), 0);
  };

  w.destroyPage = function destroyHRReport() {
    $(document).off('.hrReport');
    $('#detailModal').off('.hrReport');
    clearTimeout(typingTimer);
    clearTimeout(slowLoaderTimer);
    inited = false;
  };

  $(function () {
    if ($('#tableContainer').length && typeof w.initPage === 'function') {
      try { w.initPage(); } catch (e) { console.error('initPage error:', e); }
    }
  });

})(window, jQuery);
</script>
