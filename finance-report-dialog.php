<?php include 'connections/connection.php'; 
// finance-report-dialog.php
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Finance Dialog Invoice Report</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <input type="text" id="searchInput" class="form-control"
               placeholder="Search Mobile Number, Name, HRIS, Billing Month"
               style="max-width: 600px;">
        <button onclick="exportFinanceData('excel')" class="btn btn-primary btn-sm">Download Excel</button>
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
        <h5 class="modal-title" id="detailModalLabel">Finance Invoice Details</h5>
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

  function loadTable(page = 1) {
    const search = $('#searchInput').val();
    $('#searchHidden').val(search);
    $('#tableContainer').html('<div class="text-muted">Loading...</div>');

    $.ajax({
      url: 'table-finance-report-dialog.php',
      data: { page, search },
      method: 'GET'
    })
    .done(function (html) {
      $('#tableContainer').html(html);
    })
    .fail(function (xhr) {
      const body = xhr.responseText && xhr.responseText.trim() ? xhr.responseText : 'Failed to load data.';
      $('#tableContainer').html('<div class="alert alert-danger" style="white-space:pre-wrap;">' + body + '</div>');
    });
  }

  function exportFinanceData(type) {
    const search = $('#searchInput').val();
    const url = 'table-finance-report-dialog-detail.php';
    w.location.href = url + '?search=' + encodeURIComponent(search);
  }
  w.exportFinanceData = exportFinanceData;

  w.initFinancePage = function initFinanceReport() {
    if (inited) return;
    inited = true;

    $(document).off('.financeReport');
    $('#detailModal').off('.financeReport');

    loadTable(1);

    $(document).on('keyup.financeReport', '#searchInput', function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => loadTable(1), 300);
    });

    $(document).on('click.financeReport', '.page-link', function (e) {
      e.preventDefault();
      const page = $(this).data('pg');
      if (page) loadTable(page);
    });

    // Row click handler
    $(document).on('click.financeReport', '.table-row', function () {
      const row = $(this).data();
      const html = `
        <strong>Billing Month:</strong> ${row.period}<br>
        <strong>HRIS:</strong> ${row.hris}<br>
        <strong>Employee:</strong> ${row.employee}<br>
        <strong>Mobile Number:</strong> ${row.mobile}<br><br>
        <strong>Total Payable:</strong> Rs. ${row.total}<br>
      `;
      $('#modalBodyContent').html(html);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();
    });

    $('#detailModal').on('hidden.bs.modal.financeReport', function () {
      $('#modalBodyContent').html('Loading details...');
    });

    setTimeout(() => $('#searchInput').trigger('focus'), 0);
  };

  w.destroyFinancePage = function destroyFinanceReport() {
    $(document).off('.financeReport');
    $('#detailModal').off('.financeReport');
    clearTimeout(typingTimer);
    inited = false;
  };

  $(function () {
    if ($('#tableContainer').length && typeof w.initFinancePage === 'function') {
      try { w.initFinancePage(); } catch (e) { console.error('initFinancePage error:', e); }
    }
  });

})(window, jQuery);
</script>
