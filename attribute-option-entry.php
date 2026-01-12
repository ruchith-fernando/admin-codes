<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Attribute Options — New / Edit</h5>
      <div id="optAlert"></div>

      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-bold">Attribute</label>
          <select id="optAttr" class="form-select">
            <option value="">-- Select Attribute --</option>
          </select>
          <div class="form-text">Loads approved OPTION-type attributes.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Option Code</label>
          <input type="text" id="optCode" class="form-control" placeholder="e.g. BLACK / GENTS / LONG">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Option Name</label>
          <input type="text" id="optName" class="form-control" placeholder="e.g. Black">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Sort</label>
          <input type="number" id="optSort" class="form-control" value="0">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Active</label>
          <select id="optActive" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="optMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnOptLoadAttrs" type="button">Load Attributes</button>
          <button class="btn btn-outline-secondary" id="btnOptCheck" type="button">Check Option</button>
          <button class="btn btn-outline-primary" id="btnOptSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnOptSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="optCheckBox"></div>
      <div class="mt-3" id="optResult"></div>
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

  function loadAttrs(){
    $('#optAttr').html('<option value="">Loading...</option>');
    $.get('attribute-option-load-attributes.php', function(html){
      $('#optAttr').html('<option value="">-- Select Attribute --</option>' + html);
    }).fail(function(xhr){
      $('#optAttr').html('<option value="">-- Select Attribute --</option>');
      $('#optAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function payload(action){
    const option_code = norm($('#optCode').val());
    $('#optCode').val(option_code);
    return {
      action: action||'DRAFT',
      attribute_id: ($('#optAttr').val()||'').trim(),
      option_code,
      option_name: ($('#optName').val()||'').trim(),
      sort_order: ($('#optSort').val()||'0').trim(),
      is_active: ($('#optActive').val()||'1').trim(),
      maker_note: ($('#optMakerNote').val()||'').trim()
    };
  }

  function check(){
    const p = payload();
    if (!p.attribute_id || !p.option_code){ $('#optCheckBox').html(''); return; }
    $('#optCheckBox').html('<div class="text-muted">Checking...</div>');
    $.post('attribute-option-check.php', { attribute_id:p.attribute_id, option_code:p.option_code }, function(html){
      $('#optCheckBox').html(html);
    }).fail(function(xhr){
      $('#optCheckBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.attribute_id) { $('#optResult').html(bsAlert('danger','Attribute is required.')); return; }
    if (!p.option_code) { $('#optResult').html(bsAlert('danger','Option Code is required.')); return; }
    if (!p.option_name) { $('#optResult').html(bsAlert('danger','Option Name is required.')); return; }
    $('#optResult').html('<div class="text-muted">Saving...</div>');
    $.post('attribute-option-save.php', p, function(html){
      $('#optResult').html(html);
      check();
    }).fail(function(xhr){
      $('#optResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#btnOptLoadAttrs').on('click', loadAttrs);
  $('#btnOptCheck').on('click', check);
  $('#btnOptSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnOptSubmit').on('click', function(){ save('SUBMIT'); });

  let t=null;
  $('#optCode').on('blur', function(){ clearTimeout(t); t=setTimeout(check,150); });

})(jQuery);
</script>
