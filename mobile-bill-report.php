<!-- mobile-bill-report.php -->
<?php include 'connections/connection.php'; ?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Mobile Bill Report - HR</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <input type="text" id="searchInput" class="form-control"
               placeholder="Search Mobile Number, Name, HRIS, NIC and Billing Month" style="max-width: 600px;">
        <button onclick="exportData('excel')" class="btn btn-primary btn-sm">Download Excel</button>
      </div>

      <input type="hidden" id="searchHidden" value="">
      <div id="tableContainer" class="mt-3">Loading...</div>
    </div>
  </div>
</div>

<!-- Modal (only here, not in the AJAX response) -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Mobile Bill Details</h5>
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
  // Delayed loader so it appears only if the request is slow
  let slowLoaderTimer = null;

  function startSlowLoaderDelay() {
    // Show overlay only if still loading after 600ms
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

    // kick off delayed global overlay
    startSlowLoaderDelay();

    $.ajax({
      url: 'mobile-bill-table.php',
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
      // stop/hide the overlay regardless of outcome
      stopSlowLoader();
    });
  }

  function exportData(type) {
    const search = $('#searchInput').val();
    const url = type === 'excel' ? 'export-mobile-bill-excel.php' : 'export-mobile-bill-csv.php';
    w.location.href = url + '?search=' + encodeURIComponent(search);
  }
  w.exportData = exportData; // needed by button

  // called by page-loader.js
  w.initPage = function initMobileBillReport() {
    if (inited) return;
    inited = true;

    // clear any prior handlers
    $(document).off('.mobileBill');
    $('#detailModal').off('.mobileBill');

    // initial load
    loadTable(1);

    // search debounce
    $(document).on('keyup.mobileBill', '#searchInput', function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => loadTable(1), 300);
    });

    // pagination (uses data-pg to avoid clashing with global [data-page] loader)
    $(document).on('click.mobileBill', '.page-link', function (e) {
      e.preventDefault();
      e.stopPropagation();                 // don’t bubble to global loader
      const page = $(this).data('pg');     // <- IMPORTANT
      if (page) loadTable(page);
    });

    // row click → details modal
    $(document).on('click.mobileBill', '.table-row', function () {
      const row = $(this).data();
      const html = `
        <strong>Mobile Number:</strong> ${row.mobile}<br>
        <strong>Employee:</strong> ${row.employee}<br>
        <strong>NIC:</strong> ${row.nic}<br>
        <strong>Designation:</strong> ${row.designation}<br>
        <strong>Hierarchy:</strong> ${row.hierarchy}<br>
        <strong>HRIS:</strong> ${row.hris}<br><br>
        <strong>Date:</strong> ${row.date}<br>
        <strong>Total Payable:</strong> Rs. ${row.total}<br>
        <strong>Total Roaming:</strong> Rs. ${row.roaming}<br>
        <strong>Total Value Added Services:</strong> Rs. ${row.vas}<br>
        <strong>Total Add to Bill:</strong> Rs. ${row.addtobill}<br>
        <strong>Company Contribution:</strong> Rs. ${row.contribution}<br>
        <strong>Salary Deduction:</strong> Rs. ${row.deduction}<br>
      `;
      $('#modalBodyContent').html(html);
      bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();
    });

    $('#detailModal').on('hidden.bs.modal.mobileBill', function () {
      $('#modalBodyContent').html('Loading details...');
    });

    setTimeout(() => $('#searchInput').trigger('focus'), 0);
  };

  // optional: called before page unload by your loader
  w.destroyPage = function destroyMobileBillReport() {
    $(document).off('.mobileBill');
    $('#detailModal').off('.mobileBill');
    clearTimeout(typingTimer);
    clearTimeout(slowLoaderTimer);
    inited = false;
  };

  // If opened directly (without loader), still init.
  $(function () {
    if ($('#tableContainer').length && typeof w.initPage === 'function') {
      try { w.initPage(); } catch (e) { console.error('initPage error:', e); }
    }
  });

})(window, jQuery);
</script>
