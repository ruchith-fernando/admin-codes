<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Approvals (Maker/Checker)</h5>
      <div id="apAlert"></div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Filter</label>
          <select id="apFilter" class="form-select">
            <option value="">All Pending</option>
            <option value="GL">GL</option>
            <option value="ITEM">Item</option>
            <option value="TYPE">Item Type</option>
            <option value="ATTR">Attribute</option>
            <option value="OPT">Attribute Option</option>
            <option value="MAP">Type→Attribute Mapping</option>
            <option value="SKU">Variant SKU</option>
          </select>
        </div>

        <div class="col-md-8 d-flex align-items-end justify-content-end gap-2">
          <button class="btn btn-outline-secondary" id="btnApRefresh" type="button">Refresh</button>
        </div>
      </div>

      <div class="mt-3" id="apListBox"></div>
      <div class="mt-3" id="apResult"></div>
    </div>
  </div>
</div>

<script>
(function($){
  'use strict';
  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  }

  function load(){
    $('#apListBox').html('<div class="text-muted">Loading pending approvals...</div>');
    $.post('gifts-approvals-load.php', { filter: ($('#apFilter').val()||'').trim() }, function(html){
      $('#apListBox').html(html);
    }).fail(function(xhr){
      $('#apListBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function act(entity, id, decision){
    const note = prompt('Checker note (optional):','') || '';
    $('#apResult').html('<div class="text-muted">Processing...</div>');
    $.post('gifts-approvals-action.php', { entity, id, decision, note }, function(html){
      $('#apResult').html(html);
      load();
    }).fail(function(xhr){
      $('#apResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $(document).on('click', '.btn-approve', function(){
    act($(this).data('entity'), $(this).data('id'), 'APPROVE');
  });
  $(document).on('click', '.btn-reject', function(){
    act($(this).data('entity'), $(this).data('id'), 'REJECT');
  });

  $('#btnApRefresh').on('click', load);
  $('#apFilter').on('change', load);

  load();
})(jQuery);
</script>
