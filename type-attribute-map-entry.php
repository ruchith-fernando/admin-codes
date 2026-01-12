<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Type → Attribute Mapping</h5>
      <div id="mapAlert"></div>

      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-bold">Item Type</label>
          <select id="mapType" class="form-select">
            <option value="">-- Select Type --</option>
          </select>
        </div>

        <div class="col-md-7">
          <label class="form-label fw-bold">Attribute</label>
          <select id="mapAttr" class="form-select">
            <option value="">-- Select Attribute --</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Required</label>
          <select id="mapReq" class="form-select">
            <option value="0" selected>No</option>
            <option value="1">Yes</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Sort</label>
          <input type="number" id="mapSort" class="form-control" value="0">
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="mapNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnMapLoad" type="button">Load Types & Attributes</button>
          <button class="btn btn-outline-primary" id="btnMapSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnMapSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <hr class="my-3">

      <h6 class="text-secondary mb-2">Existing Mappings (Selected Type)</h6>
      <div id="mapListBox" class="mt-2"></div>
      <div id="mapResult" class="mt-3"></div>
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

  function loadLists(){
    $('#mapType').html('<option value="">Loading...</option>');
    $.get('type-attribute-map-load-types.php', function(html){
      $('#mapType').html('<option value="">-- Select Type --</option>' + html);
    });

    $('#mapAttr').html('<option value="">Loading...</option>');
    $.get('type-attribute-map-load-attributes.php', function(html){
      $('#mapAttr').html('<option value="">-- Select Attribute --</option>' + html);
    });
  }

  function loadTypeList(){
    const type_id = ($('#mapType').val()||'').trim();
    if (!type_id){ $('#mapListBox').html('<div class="text-muted">Select a type to view mappings.</div>'); return; }
    $('#mapListBox').html('<div class="text-muted">Loading mappings...</div>');
    $.post('type-attribute-map-list.php', { item_type_id: type_id }, function(html){
      $('#mapListBox').html(html);
    }).fail(function(xhr){
      $('#mapListBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function payload(action){
    return {
      action: action||'DRAFT',
      item_type_id: ($('#mapType').val()||'').trim(),
      attribute_id: ($('#mapAttr').val()||'').trim(),
      is_required: ($('#mapReq').val()||'0').trim(),
      sort_order: ($('#mapSort').val()||'0').trim(),
      maker_note: ($('#mapNote').val()||'').trim()
    };
  }

  function save(action){
    const p = payload(action);
    if (!p.item_type_id) { $('#mapResult').html(bsAlert('danger','Item Type is required.')); return; }
    if (!p.attribute_id) { $('#mapResult').html(bsAlert('danger','Attribute is required.')); return; }

    $('#mapResult').html('<div class="text-muted">Saving...</div>');
    $.post('type-attribute-map-save.php', p, function(html){
      $('#mapResult').html(html);
      loadTypeList();
    }).fail(function(xhr){
      $('#mapResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#btnMapLoad').on('click', loadLists);
  $('#mapType').on('change', loadTypeList);
  $('#btnMapSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnMapSubmit').on('click', function(){ save('SUBMIT'); });

  // initial
  $('#mapListBox').html('<div class="text-muted">Click "Load Types & Attributes" to start.</div>');

})(jQuery);
</script>
