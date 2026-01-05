<?php
// cdma-report-group-by-suffix.php
ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Build month list (distinct by bill_period_start’s month) from CDMA monthly table
$months = [];
$qm = "SELECT DATE_FORMAT(bill_period_start,'%Y-%m-01') AS month_start
       FROM tbl_admin_cdma_monthly_data
       GROUP BY month_start
       ORDER BY month_start DESC";
$resM = mysqli_query($conn, $qm);
if ($resM) {
  while ($r = mysqli_fetch_assoc($resM)) {
    $months[] = $r['month_start']; // 'YYYY-MM-01'
  }
}
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">CDMA Bill Report</h5>

      <div class="mb-3 d-flex gap-2 flex-wrap">
        <!-- Month dropdown (no report on initial load until user selects a month) -->
        <select id="monthSelect" class="form-control" style="max-width: 300px;">
          <option value="">-- Select Month --</option>
          <?php foreach ($months as $m1): 
            $dt = DateTime::createFromFormat('Y-m-d', $m1);
            $val = $dt ? $dt->format('Y-m') : '';
            $label = $dt ? $dt->format('F Y') : $m1;
          ?>
            <option value="<?= e($val); ?>"><?= e($label); ?></option>
          <?php endforeach; ?>
        </select>

        <button onclick="exportData('excel')" class="btn btn-primary btn-sm">Download Excel</button>
      </div>

      <input type="hidden" id="searchHidden" value="">
      <div id="tableContainer" class="mt-3">
        <div class="text-muted">Please select a month to view the report.</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal kept for consistency; not used by this table -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">CDMA Bill Details</h5>
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

  function loadTableForMonth(monthYMs) {
    if (!monthYMs) {
      $('#tableContainer').html('<div class="text-muted">Please select a month to view the report.</div>');
      return;
    }
    $('#tableContainer').html('<div class="text-muted">Loading...</div>');
    $.ajax({
      url: 'ajax-cdma-report-group-by-contract.php',
      method: 'GET',
      data: { month: monthYMs }
    })
    .done(function (html) {
      $('#tableContainer').html(html);
    })
    .fail(function (xhr) {
      const body = xhr.responseText && xhr.responseText.trim() ? xhr.responseText : 'Failed to load data.';
      $('#tableContainer').html('<div class="alert alert-danger" style="white-space:pre-wrap;">' + body + '</div>');
    });
  }

  // Month change → AJAX load (no page navigation)
  $(document).on('change', '#monthSelect', function(){
    loadTableForMonth(this.value || '');
  });

  // Export button uses current month (no page reload for the table)
  w.exportData = function exportData(type){
    const msel = document.getElementById('monthSelect');
    const m = msel && msel.value ? msel.value : '';
    const url = (type === 'excel') ? 'cdma-report-contract-export-csv.php' : 'cdma-report-contract-export-csv.php';
    const u = new URL(url, window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
    if (m) u.searchParams.set('month', m);
    window.location.href = u.toString();
  };

})(window, jQuery);
</script>
