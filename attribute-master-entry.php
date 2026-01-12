<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Attributes — New / Edit</h5>
      <div id="attrAlert"></div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Attribute Code</label>
          <input type="text" id="attrCode" class="form-control" placeholder="e.g. COLOR / GENDER / COLLAR">
        </div>

        <div class="col-md-5">
          <label class="form-label fw-bold">Attribute Name</label>
          <input type="text" id="attrName" class="form-control" placeholder="e.g. Color">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Data Type</label>
          <select id="attrType" class="form-select">
            <option value="OPTION">OPTION (dropdown)</option>
            <option value="TEXT">TEXT</option>
            <option value="NUMBER">NUMBER</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Active</label>
          <select id="attrActive" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-9">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="attrMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnAttrCheckCode" type="button">Check Code</button>
          <button class="btn btn-outline-secondary" id="btnAttrCheckName" type="button">Check Name</button>
          <button class="btn btn-outline-primary" id="btnAttrSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnAttrSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="attrCodeBox"></div>
      <div class="mt-2" id="attrNameBox"></div>
      <div class="mt-3" id="attrResult"></div>
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
    const attr_code = norm($('#attrCode').val());
    $('#attrCode').val(attr_code);
    return {
      action: action||'DRAFT',
      attr_code,
      attr_name: ($('#attrName').val()||'').trim(),
      data_type: ($('#attrType').val()||'OPTION').trim(),
      is_active: ($('#attrActive').val()||'1').trim(),
      maker_note: ($('#attrMakerNote').val()||'').trim()
    };
  }

  function checkCode(){
    const p = payload();
    if (!p.attr_code){ $('#attrCodeBox').html(''); return; }
    $('#attrCodeBox').html('<div class="text-muted">Checking...</div>');
    $.post('attribute-check-code.php',{ attr_code: p.attr_code }, function(html){
      $('#attrCodeBox').html(html);
    }).fail(function(xhr){
      $('#attrCodeBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function checkName(){
    const p = payload();
    if (!p.attr_name){ $('#attrNameBox').html(''); return; }
    $('#attrNameBox').html('<div class="text-muted">Checking...</div>');
    $.post('attribute-check-name.php',{ attr_name: p.attr_name }, function(html){
      $('#attrNameBox').html(html);
    }).fail(function(xhr){
      $('#attrNameBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.attr_code) { $('#attrResult').html(bsAlert('danger','Attribute Code is required.')); return; }
    if (!p.attr_name) { $('#attrResult').html(bsAlert('danger','Attribute Name is required.')); return; }
    $('#attrResult').html('<div class="text-muted">Saving...</div>');
    $.post('attribute-save.php', p, function(html){
      $('#attrResult').html(html);
      checkCode(); checkName();
    }).fail(function(xhr){
      $('#attrResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#btnAttrCheckCode').on('click', checkCode);
  $('#btnAttrCheckName').on('click', checkName);
  $('#btnAttrSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnAttrSubmit').on('click', function(){ save('SUBMIT'); });

  let t1=null,t2=null;
  $('#attrCode').on('blur', function(){ clearTimeout(t1); t1=setTimeout(checkCode,150); });
  $('#attrName').on('blur', function(){ clearTimeout(t2); t2=setTimeout(checkName,150); });

})(jQuery);
</script>
