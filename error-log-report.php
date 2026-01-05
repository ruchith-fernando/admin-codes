<?php
session_start();
// error-log-report.php
?>
<style>
  .font-size{font-size:.95rem}.table thead th{white-space:nowrap}
  .small-muted{font-size:.85rem;color:#6c757d}.filter-label{font-weight:500}
  .wrap-anywhere{white-space:normal!important;word-break:break-word;overflow-wrap:anywhere}
  pre.minibox{max-height:200px;overflow:auto;background:#f8f9fa;border:1px solid #e9ecef;padding:.5rem}
  #alertContainer{position:relative;z-index:1000;margin-bottom:1rem;}
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Error Log Report</h5>

      <!-- Filters -->
      <div class="row g-3 mb-3">
        <div class="col-md-2">
          <label class="filter-label">From</label>
          <input type="text" id="from_date" class="form-control datepicker" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-2">
          <label class="filter-label">To</label>
          <input type="text" id="to_date" class="form-control datepicker" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-2">
          <label class="filter-label">Error Type</label>
          <select id="error_type" class="form-select">
            <option value="">All</option><option>Notice</option><option>Warning</option>
            <option>Error</option><option>Fatal</option><option>Exception</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="filter-label">Status</label>
          <select id="resolved_status" class="form-select">
            <option value="">All</option><option value="0">Pending</option><option value="1">Resolved</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="filter-label">File (contains)</label>
          <input type="text" id="file_like" class="form-control" placeholder="e.g., user-log-fetch.php">
        </div>
        <div class="col-md-2">
          <label class="filter-label">IP / User / Message</label>
          <input type="text" id="q" class="form-control" placeholder="Search text">
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button id="btnLoad" class="btn btn-primary">Load Report</button>
        <button id="btnReset" class="btn btn-outline-secondary">Reset</button>
        <button id="btnExport" class="btn btn-success ms-auto">Download CSV</button>
      </div>

      <!-- Bootstrap alert placeholder -->
      <div id="alertContainer"></div>

      <div id="reportBody">
        <div class="text-center py-4 text-muted">Use the filters above and click <b>Load Report</b>.</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Details -->
<div class="modal fade" id="errorDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title text-primary">Error Details</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12"><div class="small text-muted">Message</div><div id="mdl_message" class="border rounded p-2 bg-light"></div></div>
          <div class="col-md-6"><div class="small text-muted">File</div><div id="mdl_file" class="border rounded p-2"></div></div>
          <div class="col-md-2"><div class="small text-muted">Line</div><div id="mdl_line" class="border rounded p-2"></div></div>
          <div class="col-md-4"><div class="small text-muted">Type</div><div id="mdl_type" class="border rounded p-2"></div></div>
          <div class="col-md-6"><div class="small text-muted">User Info</div><div id="mdl_user" class="border rounded p-2"></div></div>
          <div class="col-md-3"><div class="small text-muted">IP</div><div id="mdl_ip" class="border rounded p-2"></div></div>
          <div class="col-md-3"><div class="small text-muted">IP Source</div><div id="mdl_ip_source" class="border rounded p-2"></div></div>
          <div class="col-md-12"><div class="small text-muted">IP Chain</div><div id="mdl_ip_chain" class="border rounded p-2 small" style="white-space:pre-wrap"></div></div>
          <div class="col-md-4"><div class="small text-muted">Created At</div><div id="mdl_created" class="border rounded p-2"></div></div>
          <div class="col-md-8"><div class="small text-muted">Record ID</div><div id="mdl_id" class="border rounded p-2"></div></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script>
(function(){
  $('.datepicker').datepicker({format:'yyyy-mm-dd',todayHighlight:true,autoclose:true});
  function getFilters(){
    return {
      from_date:$('#from_date').val(),to_date:$('#to_date').val(),
      error_type:$('#error_type').val(),resolved_status:$('#resolved_status').val(),
      file_like:$('#file_like').val(),q:$('#q').val(),page:1,per_page:25
    };
  }

  function loadReport(p){
    $('#reportBody').html('<div class="text-center py-4 text-muted">Loading...</div>');
    $.post('error-log-fetch.php',p)
      .done(r=>$('#reportBody').html(r))
      .fail(x=>$('#reportBody').html('<div class="alert alert-danger">Server '+x.status+'</div>'));
  }

  $('#btnLoad').click(()=>loadReport(getFilters()));
  $('#btnReset').click(()=>{$('input,select').val('');$('#reportBody').html('<div class="text-center py-4 text-muted">Use filters and click <b>Load Report</b>.</div>');});
  $('#btnExport').click(()=>{window.location='error-log-export.php?'+$.param(getFilters());});

  // === View Details ===
  $('#reportBody').on('click','.btn-view',function(){
    const b=$(this);
    $('#mdl_message').text(b.data('message'));$('#mdl_file').text(b.data('file'));
    $('#mdl_line').text(b.data('line'));$('#mdl_type').text(b.data('type'));
    $('#mdl_user').text(b.data('user'));$('#mdl_ip').text(b.data('ip'));
    $('#mdl_ip_source').text(b.data('ipsource'));$('#mdl_ip_chain').text(b.data('ipchain'));
    $('#mdl_created').text(b.data('created'));$('#mdl_id').text(b.data('id'));
    new bootstrap.Modal('#errorDetailsModal').show();
  });

  // === Mark as Done (NO confirmation, auto Bootstrap alert) ===
  $('#reportBody').on('click','.btn-mark-done',function(e){
    e.preventDefault();
    const b=$(this);
    $.post('error-log-mark-done.php',{
      id:b.data('id'),
      file:b.data('file'),
      line:b.data('line'),
      message:b.data('message')
    })
    .done(res=>{
      $('#alertContainer').html(res);
      setTimeout(()=>$('.alert').alert('close'),5000);
      $('#btnLoad').click(); // reload
    })
    .fail(()=>{
      $('#alertContainer').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Request failed.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      setTimeout(()=>$('.alert').alert('close'),5000);
    });
  });

  // === Pagination (Pending) ===
  $('#reportBody').on('click','.page-btn',function(){
    const pg=$(this).data('pg');
    const p=getFilters();p.page=pg;
    loadReport(p);
  });

  // === Pagination (Resolved) ===
  $('#reportBody').on('click','.page-btn-resolved',function(){
    const pg=$(this).data('pg');
    $('#resolvedContent').html('Loading...');
    $.post('error-log-fetch-resolved.php',{page:pg},function(html){
      $('#resolvedContent').html(html);
    });
  });

  // === Load Resolved Tab once clicked ===
  $('#reportBody').on('click','#tab-resolved',function(){
    $('#resolvedContent').html('Loading...');
    $.post('error-log-fetch-resolved.php',{},function(html){
      $('#resolvedContent').html(html);
    });
  });
})();
</script>
