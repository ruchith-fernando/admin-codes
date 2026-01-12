<?php
// gl-master-entry.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">GL Master — New / Edit</h5>
      <div id="glAlert"></div>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">GL Code</label>
          <input type="text" id="glCode" class="form-control" placeholder="e.g. 1110">
          <div class="form-text">Unique code. Keep consistent with your chart of accounts.</div>
        </div>

        <div class="col-md-5">
          <label class="form-label fw-bold">GL Name</label>
          <input type="text" id="glName" class="form-control" placeholder="e.g. Stationery & Gifts">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Parent GL (optional)</label>
          <select id="glParent" class="form-select">
            <option value="">-- None / Root --</option>
          </select>
          <div class="form-text">Load from approved GL list.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="glMakerNote" class="form-control" rows="2" placeholder="Reason / details for checker"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Record Status</label>
          <input type="text" id="glStatus" class="form-control" value="DRAFT" readonly>
          <div class="form-text">Use Save Draft or Submit for Approval.</div>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnGlLoadParents" type="button">Load Parents</button>
          <button class="btn btn-outline-secondary" id="btnGlCheckCode" type="button">Check GL Code</button>
          <button class="btn btn-outline-primary" id="btnGlSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnGlSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="glCodeBox"></div>
      <div class="mt-3" id="glResult"></div>
    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }

  function normalizeCode(v){
    return (v||'').toString().trim().toUpperCase();
  }

  function payload(action){
    const gl_code = normalizeCode($('#glCode').val());
    $('#glCode').val(gl_code);
    return {
      action: action || 'DRAFT',
      gl_code: gl_code,
      gl_name: ($('#glName').val()||'').trim(),
      parent_gl_id: ($('#glParent').val()||'').trim(),
      maker_note: ($('#glMakerNote').val()||'').trim()
    };
  }

  function loadParents(){
    $('#glAlert').html('');
    $('#glParent').html('<option value="">Loading...</option>');
    $.get('gl-master-load-parents.php', function(html){
      $('#glParent').html('<option value="">-- None / Root --</option>' + html);
    }).fail(function(xhr){
      $('#glParent').html('<option value="">-- None / Root --</option>');
      $('#glAlert').html(bsAlert('danger', 'Server error loading parents: ' + xhr.status));
    });
  }

  function checkCode(){
    const p = payload();
    if (!p.gl_code) { $('#glCodeBox').html(''); return; }
    $('#glCodeBox').html('<div class="text-muted">Checking GL code...</div>');
    $.post('gl-master-check-code.php', { gl_code: p.gl_code }, function(html){
      $('#glCodeBox').html(html);
    }).fail(function(xhr){
      $('#glCodeBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.gl_code) { $('#glResult').html(bsAlert('danger','GL Code is required.')); return; }
    if (!p.gl_name) { $('#glResult').html(bsAlert('danger','GL Name is required.')); return; }

    $('#glResult').html('<div class="text-muted">Saving...</div>');
    $.post('gl-master-save.php', p, function(html){
      $('#glResult').html(html);
      $('#glStatus').val(action === 'SUBMIT' ? 'PENDING' : 'DRAFT');
      checkCode();
    }).fail(function(xhr){
      $('#glResult').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  $('#btnGlLoadParents').on('click', loadParents);
  $('#btnGlCheckCode').on('click', checkCode);
  $('#btnGlSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnGlSubmit').on('click', function(){ save('SUBMIT'); });

  let t=null;
  $('#glCode').on('blur', function(){ clearTimeout(t); t=setTimeout(checkCode, 150); });

})(jQuery);
</script>
