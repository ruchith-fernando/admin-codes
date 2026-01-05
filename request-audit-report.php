<?php
// request-audit-report.php
session_start();

// Silent log for unexpected PHP warnings to avoid 500 during AJAX loads
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL);
?>
<!-- Bootstrap Datepicker (jQuery plugin) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/css/bootstrap-datepicker.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"></script>

<style>
  .font-size { font-size: 0.95rem; }
  .table thead th { white-space: nowrap; }
  .cursor-pointer { cursor: pointer; }
  .small-muted { font-size: 0.85rem; color: #6c757d; }
  .pagination .page-link { cursor: pointer; }
  .wrap-anywhere { white-space: normal !important; word-break: break-word; overflow-wrap: anywhere; }
  .table td { vertical-align: top; }
  pre.minibox { max-height: 200px; overflow:auto; background:#f8f9fa; border:1px solid #e9ecef; padding:.5rem; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Request Audit Report</h5>

      <!-- Filters -->
      <div class="row g-3 mb-3">
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="text" id="from_date" class="form-control datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="text" id="to_date" class="form-control datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-2">
          <label class="form-label">Method</label>
          <select id="method" class="form-select">
            <option value="">All</option>
            <option>GET</option>
            <option>POST</option>
            <option>PUT</option>
            <option>PATCH</option>
            <option>DELETE</option>
            <option>OPTIONS</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Page (contains)</label>
          <input type="text" id="page_like" class="form-control" placeholder="e.g., security-cost-report.php">
        </div>
        <div class="col-md-3">
          <label class="form-label">Search (user/IP/URI/agent/referer)</label>
          <input type="text" id="q" class="form-control" placeholder="Type to search...">
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button id="btnLoad" type="button" class="btn btn-primary">Load Report</button>
        <button id="btnReset" type="button" class="btn btn-outline-secondary">Reset</button>
        <button id="btnExport" type="button" class="btn btn-success ms-auto">Download CSV</button>
      </div>

      <div id="reportBody">
        <div class="text-center py-4 text-muted">Use the filters above and click <b>Load Report</b>.</div>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title text-primary">Audit Entry Details</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="small text-muted">Created At</div>
            <div id="mdl_created" class="border rounded p-2"></div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted">Method</div>
            <div id="mdl_method" class="border rounded p-2"></div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Page</div>
            <div id="mdl_page" class="border rounded p-2"></div>
          </div>

          <div class="col-md-6">
            <div class="small text-muted">Request URI</div>
            <div id="mdl_uri" class="border rounded p-2" style="white-space:pre-wrap"></div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Referer</div>
            <div id="mdl_referer" class="border rounded p-2" style="white-space:pre-wrap"></div>
          </div>

          <div class="col-md-4">
            <div class="small text-muted">Username</div>
            <div id="mdl_username" class="border rounded p-2"></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">HRIS</div>
            <div id="mdl_hris" class="border rounded p-2"></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">Record ID</div>
            <div id="mdl_id" class="border rounded p-2"></div>
          </div>

          <div class="col-md-4">
            <div class="small text-muted">IP Address</div>
            <div id="mdl_ip" class="border rounded p-2"></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">IP Source</div>
            <div id="mdl_ip_source" class="border rounded p-2"></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">X-Forwarded-For Chain</div>
            <div id="mdl_xff" class="border rounded p-2 small" style="white-space:pre-wrap"></div>
          </div>

          <div class="col-12">
            <div class="small text-muted">User Agent</div>
            <div id="mdl_ua" class="border rounded p-2 small" style="white-space:pre-wrap"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button data-bs-dismiss="modal" type="button" class="btn btn-secondary">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // --- Datepickers ---
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    todayHighlight: true,
    autoclose: true,
    orientation: 'bottom'
  });

  function getFilters() {
    return {
      from_date: $('#from_date').val(),
      to_date: $('#to_date').val(),
      method: $('#method').val(),
      page_like: $('#page_like').val(),
      q: $('#q').val(),
      page: 1,
      per_page: 25
    };
  }

  function loadReport(params){
    $('#reportBody').html('<div class="text-center py-4 text-muted">Loading...</div>');
    return $.post('request-audit-fetch.php', params)
      .done(function(html){
        $('#reportBody').html(html);
      })
      .fail(function(xhr){
        const body = (xhr.responseText || '').toString();
        $('#reportBody').html(
          '<div class="alert alert-danger mb-2">Failed to load report (HTTP '+xhr.status+').</div>'+
          '<pre class="minibox small text-danger">'+$('<div>').text(body).html()+'</pre>'
        );
      });
  }

  $('#btnLoad').on('click', function(){ loadReport(getFilters()); });

  $('#btnReset').on('click', function(){
    $('#from_date,#to_date,#page_like,#q').val('');
    $('#method').val('');
    $('#reportBody').html('<div class="text-center py-4 text-muted">Use the filters above and click <b>Load Report</b>.</div>');
  });

  // Pagination (single owner)
  $('#reportBody').on('click', '.pagination a.page-link[data-page]', function(e){
    e.preventDefault();
    e.stopPropagation();
    const page = parseInt($(this).data('page'), 10) || 1;
    const p = getFilters();
    p.page = page;
    p.per_page = $('#per_page').val() || 25;
    loadReport(p);
  });

  // Per-page change
  $('#reportBody').on('change', '#per_page', function(){
    const p = getFilters();
    p.page = 1;
    p.per_page = $(this).val();
    loadReport(p);
  });

  // (Optional) Details modal support if you add an Actions column later
  $(document).on('click','.btn-view',function(e){
    e.preventDefault();
    e.stopPropagation();
    const $b=$(this);
    $('#mdl_id').text($b.data('id')||'');
    $('#mdl_created').text($b.data('created')||'');
    $('#mdl_method').text($b.data('method')||'');
    $('#mdl_page').text($b.data('page')||'');
    $('#mdl_uri').text(($b.data('uri')||'').toString());
    $('#mdl_username').text($b.data('username')||'');
    $('#mdl_hris').text($b.data('hris')||'');
    $('#mdl_ip').text($b.data('ip')||'');
    $('#mdl_ip_source').text($b.data('ipsource')||'');
    $('#mdl_xff').text(($b.data('xff')||'').toString());
    $('#mdl_ua').text(($b.data('ua')||'').toString());
    $('#mdl_referer').text(($b.data('referer')||'').toString());
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(document.getElementById('auditDetailsModal')).show();
    } else if (typeof $('#auditDetailsModal').modal === 'function') {
      $('#auditDetailsModal').modal('show');
    }
  });

  $('#btnExport').on('click', function(){
    const qs = $.param(getFilters());
    window.location.href = 'request-audit-export.php?' + qs;
  });
})();
</script>
