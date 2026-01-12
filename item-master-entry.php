<?php
// item-master-entry.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Item Master — New / Edit</h5>
      <div id="itAlert"></div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">GL</label>
          <select id="itGl" class="form-select">
            <option value="">-- Select GL --</option>
          </select>
          <div class="form-text">Only approved GLs should appear here.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Item Code</label>
          <input type="text" id="itCode" class="form-control" placeholder="e.g. STY-0001">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">UOM</label>
          <input type="text" id="itUom" class="form-control" placeholder="PCS / REAM / ROLL" value="PCS">
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Item Name (must be unique)</label>
          <input type="text" id="itName" class="form-control" placeholder="e.g. Ball Pen - Blue">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Item Type (for variants)</label>
          <select id="itType" class="form-select">
            <option value="">-- None (simple item) --</option>
          </select>
          <div class="form-text">Uniform / Cup / Pen etc. Load from approved list.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="itMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Active</label>
          <select id="itActive" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Record Status</label>
          <input type="text" id="itStatus" class="form-control" value="DRAFT" readonly>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnItLoadLists" type="button">Load GLs & Types</button>
          <button class="btn btn-outline-secondary" id="btnItCheckCode" type="button">Check Code</button>
          <button class="btn btn-outline-secondary" id="btnItCheckName" type="button">Check Name</button>
          <button class="btn btn-outline-primary" id="btnItSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnItSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="itCodeBox"></div>
      <div class="mt-2" id="itNameBox"></div>
      <div class="mt-3" id="itResult"></div>
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

  function normalizeCode(v){ return (v||'').toString().trim().toUpperCase(); }

  function payload(action){
    const item_code = normalizeCode($('#itCode').val());
    $('#itCode').val(item_code);
    return {
      action: action || 'DRAFT',
      gl_id: ($('#itGl').val()||'').trim(),
      item_code: item_code,
      item_name: ($('#itName').val()||'').trim(),
      uom: ($('#itUom').val()||'').trim(),
      item_type_id: ($('#itType').val()||'').trim(),
      is_active: ($('#itActive').val()||'1').trim(),
      maker_note: ($('#itMakerNote').val()||'').trim()
    };
  }

  function loadLists(){
    $('#itAlert').html('');
    // GL list
    $('#itGl').html('<option value="">Loading...</option>');
    $.get('item-master-load-gls.php', function(html){
      $('#itGl').html('<option value="">-- Select GL --</option>' + html);
    }).fail(function(xhr){
      $('#itGl').html('<option value="">-- Select GL --</option>');
      $('#itAlert').html(bsAlert('danger','Server error loading GLs: ' + xhr.status));
    });

    // Type list
    $('#itType').html('<option value="">Loading...</option>');
    $.get('item-master-load-types.php', function(html){
      $('#itType').html('<option value="">-- None (simple item) --</option>' + html);
    }).fail(function(xhr){
      $('#itType').html('<option value="">-- None (simple item) --</option>');
      $('#itAlert').html(bsAlert('danger','Server error loading types: ' + xhr.status));
    });
  }

  function checkCode(){
    const p = payload();
    if (!p.item_code) { $('#itCodeBox').html(''); return; }
    $('#itCodeBox').html('<div class="text-muted">Checking item code...</div>');
    $.post('item-master-check-code.php', { item_code: p.item_code }, function(html){
      $('#itCodeBox').html(html);
    }).fail(function(xhr){
      $('#itCodeBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  function checkName(){
    const p = payload();
    if (!p.item_name) { $('#itNameBox').html(''); return; }
    $('#itNameBox').html('<div class="text-muted">Checking item name...</div>');
    $.post('item-master-check-name.php', { item_name: p.item_name }, function(html){
      $('#itNameBox').html(html);
    }).fail(function(xhr){
      $('#itNameBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.gl_id) { $('#itResult').html(bsAlert('danger','GL is required.')); return; }
    if (!p.item_code) { $('#itResult').html(bsAlert('danger','Item Code is required.')); return; }
    if (!p.item_name) { $('#itResult').html(bsAlert('danger','Item Name is required.')); return; }
    if (!p.uom) { $('#itResult').html(bsAlert('danger','UOM is required.')); return; }

    $('#itResult').html('<div class="text-muted">Saving...</div>');
    $.post('item-master-save.php', p, function(html){
      $('#itResult').html(html);
      $('#itStatus').val(action === 'SUBMIT' ? 'PENDING' : 'DRAFT');
      checkCode(); checkName();
    }).fail(function(xhr){
      $('#itResult').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  $('#btnItLoadLists').on('click', loadLists);
  $('#btnItCheckCode').on('click', checkCode);
  $('#btnItCheckName').on('click', checkName);
  $('#btnItSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnItSubmit').on('click', function(){ save('SUBMIT'); });

  let t1=null, t2=null;
  $('#itCode').on('blur', function(){ clearTimeout(t1); t1=setTimeout(checkCode, 150); });
  $('#itName').on('blur', function(){ clearTimeout(t2); t2=setTimeout(checkName, 150); });

})(jQuery);
</script>
