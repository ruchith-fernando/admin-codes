<?php
// item-variant-entry.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Item Variant (SKU) — New / Edit</h5>
      <div id="vrAlert"></div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Base Item</label>
          <select id="vrItem" class="form-select">
            <option value="">-- Select Item --</option>
          </select>
          <div class="form-text">Choose an approved Item. If it has a Type, attributes will load.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Item Type</label>
          <input type="text" id="vrTypeName" class="form-control" placeholder="Auto" readonly>
          <input type="hidden" id="vrTypeId" value="">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Variant Code (SKU)</label>
          <input type="text" id="vrCode" class="form-control" placeholder="e.g. UNI-TS-GENTS-BLK-LONG-SLIM-15.5">
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Variant Attributes</label>
          <div class="border rounded p-3 bg-light" id="vrAttrBox">
            <div class="text-muted">Select Base Item to load attributes...</div>
          </div>
          <div class="form-text">Attributes are configurable (color/gender/sleeve/fit/collar size etc).</div>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Variant Name (auto)</label>
          <input type="text" id="vrName" class="form-control" placeholder="Auto-generated from attributes">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Active</label>
          <select id="vrActive" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="vrMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Record Status</label>
          <input type="text" id="vrStatus" class="form-control" value="DRAFT" readonly>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnVrLoadItems" type="button">Load Items</button>
          <button class="btn btn-outline-secondary" id="btnVrCheckSku" type="button">Check SKU</button>
          <button class="btn btn-outline-primary" id="btnVrSaveDraft" type="button">Save Draft</button>
          <button class="btn btn-success" id="btnVrSubmit" type="button">Submit for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="vrCheckBox"></div>
      <div class="mt-3" id="vrResult"></div>
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

  // Build signature like ATTR=VALUE|ATTR2=VALUE2 (sorted by attr_code)
  function buildSignature(values){
    const keys = Object.keys(values||{}).sort();
    return keys.map(k => `${k}=${values[k]}`).join('|');
  }

  function readAttrValues(){
    const values = {};
    $('#vrAttrBox').find('[data-attr-code]').each(function(){
      const code = $(this).data('attr-code');
      const v = ($(this).val()||'').toString().trim();
      if (code) values[code] = v;
    });
    return values;
  }

  function autoNameFromAttrs(itemText, values){
    const parts = [];
    Object.keys(values).sort().forEach(k=>{
      if (values[k]) parts.push(values[k]);
    });
    const base = (itemText||'').replace(/\s+/g,' ').trim();
    return base ? (base + ' — ' + parts.join(' / ')) : parts.join(' / ');
  }

  function payload(action){
    const sku = normalizeCode($('#vrCode').val());
    $('#vrCode').val(sku);

    const item_id = ($('#vrItem').val()||'').trim();
    const type_id = ($('#vrTypeId').val()||'').trim();
    const is_active = ($('#vrActive').val()||'1').trim();
    const maker_note = ($('#vrMakerNote').val()||'').trim();

    const values = readAttrValues();
    const signature = buildSignature(values);

    // auto-fill name if empty
    const itemText = $('#vrItem option:selected').text();
    if (!($('#vrName').val()||'').trim()) $('#vrName').val(autoNameFromAttrs(itemText, values));

    return {
      action: action || 'DRAFT',
      item_id: item_id,
      item_type_id: type_id,
      variant_code: sku,
      variant_name: ($('#vrName').val()||'').trim(),
      variant_signature: signature,
      is_active: is_active,
      maker_note: maker_note,
      attr_values: JSON.stringify(values) // backend will parse and insert into tbl_admin_item_variant_value
    };
  }

  function loadItems(){
    $('#vrAlert').html('');
    $('#vrItem').html('<option value="">Loading...</option>');
    $.get('item-variant-load-items.php', function(html){
      $('#vrItem').html('<option value="">-- Select Item --</option>' + html);
      $('#vrTypeName').val(''); $('#vrTypeId').val('');
      $('#vrAttrBox').html('<div class="text-muted">Select Base Item to load attributes...</div>');
    }).fail(function(xhr){
      $('#vrItem').html('<option value="">-- Select Item --</option>');
      $('#vrAlert').html(bsAlert('danger','Server error loading items: ' + xhr.status));
    });
  }

  function loadAttributesForItem(){
    const item_id = ($('#vrItem').val()||'').trim();
    if (!item_id){
      $('#vrTypeName').val(''); $('#vrTypeId').val('');
      $('#vrAttrBox').html('<div class="text-muted">Select Base Item to load attributes...</div>');
      return;
    }

    $('#vrAttrBox').html('<div class="text-muted">Loading attributes...</div>');
    $.ajax({
      url: 'item-variant-load-attributes.php',
      method: 'POST',
      data: { item_id: item_id },
      dataType: 'json'
    })
    .done(function(res){
      if (!res || !res.ok){
        $('#vrAttrBox').html(bsAlert('danger', (res && res.error) ? res.error : 'Failed to load attributes.'));
        return;
      }

      $('#vrTypeName').val(res.type_name || '');
      $('#vrTypeId').val(res.item_type_id || '');

      // res.attrs expected format:
      // [{attr_code, attr_name, data_type, required, options:[{value,label}]}]
      const attrs = res.attrs || [];
      if (!attrs.length){
        $('#vrAttrBox').html('<div class="text-muted">No attributes for this item type (simple SKU).</div>');
        return;
      }

      let html = '<div class="row g-3">';
      attrs.forEach(function(a){
        const req = a.required ? ' <span class="text-danger">*</span>' : '';
        html += `<div class="col-md-4">
          <label class="form-label fw-bold">${a.attr_name}${req}</label>`;

        if (a.data_type === 'OPTION'){
          html += `<select class="form-select" data-attr-code="${a.attr_code}">
            <option value="">-- Select --</option>`;
          (a.options||[]).forEach(function(o){
            html += `<option value="${String(o.value).replace(/"/g,'&quot;')}">${o.label}</option>`;
          });
          html += `</select>`;
        } else if (a.data_type === 'NUMBER'){
          html += `<input type="number" step="0.01" class="form-control" data-attr-code="${a.attr_code}" placeholder="Enter ${a.attr_name}">`;
        } else {
          html += `<input type="text" class="form-control" data-attr-code="${a.attr_code}" placeholder="Enter ${a.attr_name}">`;
        }

        html += `</div>`;
      });
      html += '</div>';

      $('#vrAttrBox').html(html);

      // whenever attributes change, auto-update name + signature preview
      $('#vrAttrBox').find('select,input').on('change blur', function(){
        const p = payload(); // will auto-fill name if empty
        // optional: show signature somewhere if needed
      });
    })
    .fail(function(xhr){
      $('#vrAttrBox').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function checkSku(){
    const p = payload();
    if (!p.variant_code) { $('#vrCheckBox').html(''); return; }
    $('#vrCheckBox').html('<div class="text-muted">Checking SKU...</div>');
    $.post('item-variant-check-sku.php', { variant_code: p.variant_code, item_id: p.item_id }, function(html){
      $('#vrCheckBox').html(html);
    }).fail(function(xhr){
      $('#vrCheckBox').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function save(action){
    const p = payload(action);
    if (!p.item_id) { $('#vrResult').html(bsAlert('danger','Base Item is required.')); return; }
    if (!p.variant_code) { $('#vrResult').html(bsAlert('danger','Variant Code (SKU) is required.')); return; }

    // If type has attributes, require signature (and you can validate required in backend too)
    if (p.item_type_id && !p.variant_signature){
      $('#vrResult').html(bsAlert('danger','Please fill variant attributes to generate a signature.'));
      return;
    }

    $('#vrResult').html('<div class="text-muted">Saving...</div>');
    $.post('item-variant-save.php', p, function(html){
      $('#vrResult').html(html);
      $('#vrStatus').val(action === 'SUBMIT' ? 'PENDING' : 'DRAFT');
      checkSku();
    }).fail(function(xhr){
      $('#vrResult').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  // events
  $('#btnVrLoadItems').on('click', loadItems);
  $('#vrItem').on('change', loadAttributesForItem);
  $('#btnVrCheckSku').on('click', checkSku);
  $('#btnVrSaveDraft').on('click', function(){ save('DRAFT'); });
  $('#btnVrSubmit').on('click', function(){ save('SUBMIT'); });

  let t=null;
  $('#vrCode').on('blur', function(){ clearTimeout(t); t=setTimeout(checkSku, 150); });

})(jQuery);
</script>
