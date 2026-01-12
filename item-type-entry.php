<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Item Types — New / Edit</h5>
      <div id="itTypeAlert"></div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Type Code</label>
          <input type="text" id="itTypeCode" class="form-control" placeholder="e.g. UNIFORM / CUP / PEN">
          <div class="form-text">Unique. Use uppercase.</div>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Type Name</label>
          <input type="text" id="itTypeName" class="form-control" placeholder="e.g. Office Uniforms">
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="itTypeMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Record Status</label>
          <input type="text" id="itTypeStatus" class="form-control" value="DRAFT" readonly>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnTypeCheck" type="button">Check Type Code</button>
          <button class="btn btn-outline-primary" id="btnTypeSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnTypeSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="itTypeCheckBox"></div>
      <div class="mt-3" id="itTypeResult"></div>
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
  function norm(v){ return (v||'').toString().trim().toUpperCase(); }

  function payload(action){
    const type_code = norm($('#itTypeCode').val());
    $('#itTypeCode').val(type_code);
    return {
      action: action||'DRAFT',
      type_code,
      type_name: ($('#itTypeName').val()||'').trim(),
      maker_note: ($('#itTypeMakerNote').val()||'').trim()
    };
  }

  function check(){
    const p = payload();
    if (!p.type_code) { $('#itTypeCheckBox').html(''); return; }
    $('#itTypeCheckBox').html('<div class="text-muted">Checking...</div>');
    $.post('item-type-check-code.php', { type_code: p.type_code }, function(html){
      $('#itTypeCheckBox').html(html);
    }).fail(function(xhr){
      $('#itTypeCheckBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.type_code) { $('#itTypeResult').html(bsAlert('danger','Type Code is required.')); return; }
    if (!p.type_name) { $('#itTypeResult').html(bsAlert('danger','Type Name is required.')); return; }
    $('#itTypeResult').html('<div class="text-muted">Saving...</div>');
    $.post('item-type-save.php', p, function(html){
      $('#itTypeResult').html(html);
      $('#itTypeStatus').val(action==='SUBMIT' ? 'PENDING' : 'DRAFT');
      check();
    }).fail(function(xhr){
      $('#itTypeResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#btnTypeCheck').on('click', check);
  $('#btnTypeSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnTypeSubmit').on('click', function(){ save('SUBMIT'); });

  let t=null;
  $('#itTypeCode').on('blur', function(){ clearTimeout(t); t=setTimeout(check,150); });

})(jQuery);
</script>
