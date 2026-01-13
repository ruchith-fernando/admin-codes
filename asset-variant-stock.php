<?php
// asset-variant-stock.php  (VARIANTS ONLY)
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
  $cookie = session_get_cookie_params();
  session_set_cookie_params([
    'lifetime' => $cookie['lifetime'],
    'path'     => '/',
    'domain'   => $cookie['domain'],
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { die('Session expired. Please login again.'); }
?>

<style>
.select2-container .select2-selection--single{
  height: calc(2.375rem + 2px) !important;
  border: 1px solid #ced4da !important;
  border-radius: .375rem !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered{
  line-height: calc(2.375rem + 2px) !important;
  padding-left: .75rem !important;
  padding-right: 2rem !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow{
  height: calc(2.375rem + 2px) !important;
}

/* attribute rows */
.attr-row .form-control{ height: calc(2.375rem + 2px); }

/* barcode */
.barcode-box{ width:100%; overflow:hidden; }
#vBarcode{ width:100%; height:56px; }
</style>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Variants (SKU) — New / Submit</h5>
      <div id="vAlert"></div>

      <input type="hidden" id="vResId" value="">
      <input type="hidden" id="vVariantCode" value="">

      <div class="row g-3">

        <!-- MASTER ITEM (AJAX SELECT2) -->
        <div class="col-md-12">
          <label class="form-label fw-bold">Master Item (Approved)</label>
          <select id="vAssetId" class="form-select" style="width:100%">
            <option value="">-- Select Item --</option>
          </select>
          <div class="form-text">Type to search (fast dropdown).</div>
        </div>

        <!-- ATTRIBUTES -->
        <div class="col-md-12">
          <label class="form-label fw-bold">Variant Attributes (optional)</label>

          <div class="border rounded p-3 bg-light">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
              <div class="text-muted small me-2">
                Pick attribute key, then value from dropdown. Example: COLOR=Black, SIZE=XL.
              </div>
              <button class="btn btn-sm btn-outline-primary" type="button" id="btnAddAttr">+ Add Attribute</button>
            </div>

            <div id="attrBox"></div>

            <div class="form-text mt-2">
              Mugs can have zero attributes. Apparel can have many.
            </div>
          </div>
        </div>

        <!-- Variant name / code -->
        <div class="col-md-6">
          <label class="form-label fw-bold">Variant Name (Auto)</label>
          <input type="text" id="vName" class="form-control" readonly>
          <div class="form-text">Built from master item name + attributes.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Variant Code</label>
          <input type="text" id="vCodeShow" class="form-control" readonly>
          <div class="form-text">Generated before submit.</div>
        </div>

        <!-- EXPIRY / SERIAL / WARRANTY -->
        <div class="col-md-4">
          <label class="form-label fw-bold">Expiry Applicable?</label>
          <select id="vHasExpiry" class="form-select">
            <option value="0" selected>No</option>
            <option value="1">Yes</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Serial Number Applicable?</label>
          <select id="vHasSerial" class="form-select">
            <option value="0" selected>No</option>
            <option value="1">Yes</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Warranty Applicable?</label>
          <select id="vHasWarranty" class="form-select">
            <option value="0" selected>No</option>
            <option value="1">Yes</option>
          </select>
        </div>


        <!-- BARCODE -->
        <div class="col-md-12">
          <label class="form-label fw-bold">Barcode</label>
          <div class="border rounded p-3 bg-light barcode-box">
            <svg id="vBarcode"></svg>
            <div class="fw-bold mt-2" id="vBarcodeText"></div>
            <div class="small text-muted" id="vBarcodeHint"></div>
          </div>
        </div>

        <div class="col-md-12 d-flex justify-content-end gap-2">
          <button type="button" id="btnVariantSubmit" class="btn btn-success">Submit Variant for Approval</button>
        </div>
      </div>

      <div class="mt-3" id="vResult"></div>
    </div>

    <!-- APPROVALS -->
    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Variant Approvals (Dual Control)</h5>
      <div id="vApAlert"></div>
      <div id="variantPendingBox"></div>
    </div>

  </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>

<script>
(function($){
  'use strict';

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  }

  function initTooltips(){
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
      const inst = bootstrap.Tooltip.getInstance(el);
      if (inst) inst.dispose();
      new bootstrap.Tooltip(el, { html:true });
    });
  }

  function renderBarcode(code){
    $('#vBarcodeText').text(code||'');
    $('#vBarcode').empty();
    $('#vBarcodeHint').text('');
    if (!code) return;

    if (typeof JsBarcode !== 'function') {
      $('#vBarcodeHint').text('JsBarcode not loaded.');
      return;
    }
    try {
      JsBarcode("#vBarcode", code, { format:"CODE128", displayValue:false, margin:0 });
      const svg = document.getElementById('vBarcode');
      if (svg){
        svg.setAttribute('width', '100%');
        svg.style.width = '100%';
      }
    } catch(e) {
      $('#vBarcodeHint').text('Barcode error: '+e.message);
    }
  }

  function normKey(k){
    return (k||'').toString().trim().toUpperCase()
      .replace(/\s+/g,'_')
      .replace(/[^A-Z0-9_]/g,'');
  }

  // ---------- Extras (Expiry/Serial/Warranty) ----------
  function toggleExtras(){
    const hasExpiry = ($('#vHasExpiry').val() === '1');
    $('#boxExpiryDate').toggle(hasExpiry);
    if (!hasExpiry) $('#vExpiryDate').val('');

    const hasSerial = ($('#vHasSerial').val() === '1');
    $('#boxSerialNo').toggle(hasSerial);
    if (!hasSerial) $('#vSerialNo').val('');

    const hasWarranty = ($('#vHasWarranty').val() === '1');
    $('#boxWarrantyMode').toggle(hasWarranty);

    if (!hasWarranty){
      $('#boxWarrantyDate').hide();
      $('#boxWarrantyText').hide();
      $('#vWarrantyDate').val('');
      $('#vWarrantyText').val('');
      return;
    }

    const mode = ($('#vWarrantyMode').val() || 'DATE');
    if (mode === 'DATE'){
      $('#boxWarrantyDate').show();
      $('#boxWarrantyText').hide();
      $('#vWarrantyText').val('');
    } else {
      $('#boxWarrantyText').show();
      $('#boxWarrantyDate').hide();
      $('#vWarrantyDate').val('');
    }
  }

  function resetExtras(){
    $('#vHasExpiry').val('0');
    $('#vExpiryDate').val('');
    $('#vHasSerial').val('0');
    $('#vSerialNo').val('');
    $('#vHasWarranty').val('0');
    $('#vWarrantyMode').val('DATE');
    $('#vWarrantyDate').val('');
    $('#vWarrantyText').val('');
    toggleExtras();
  }

  // ---------- Attribute Rows (KEY dropdown + VALUE dropdown) ----------
  function addAttrRow(prefKey){
    const id = 'ar'+Math.random().toString(16).slice(2);
    const row = `
      <div class="row g-2 align-items-center attr-row mb-2" data-row="${id}">
        <div class="col-md-4">
          <select class="form-select attr-key" data-row="${id}" style="width:100%"></select>
        </div>
        <div class="col-md-7">
          <select class="form-select attr-val" data-row="${id}" style="width:100%"></select>
        </div>
        <div class="col-md-1 text-end">
          <button type="button" class="btn btn-outline-danger btn-sm btn-del-attr" data-row="${id}">×</button>
        </div>
      </div>`;
    $('#attrBox').append(row);

    initAttrKeySelect(id, prefKey || '');
    initAttrValSelect(id, prefKey || '', 'Select value');
  }

  function initAttrKeySelect(rowId, presetKey){
    const $key = $(`.attr-key[data-row="${rowId}"]`);
    if ($key.data('select2')) $key.select2('destroy');

    $key.select2({
      width:'100%',
      placeholder:'Select KEY (e.g. COLOR)',
      allowClear:true,
      minimumInputLength: 0,
      ajax:{
        url:'asset-variant-api.php',
        type:'POST',
        dataType:'json',
        delay:200,
        cache:true,
        data:function(params){
          return { action:'ATTR_KEY_SEARCH', q: params.term || '', page: params.page || 1 };
        },
        processResults:function(data, params){
          params.page = params.page || 1;
          return { results: data.results || [], pagination:{ more: !!data.more } };
        }
      }
    });

    if (presetKey){
      // set preset key immediately
      const k = normKey(presetKey);
      const opt = new Option(k, k, true, true);
      $key.append(opt).trigger('change');
    }
  }

  function initAttrValSelect(rowId, key, placeholderText){
    const $val = $(`.attr-val[data-row="${rowId}"]`);
    if ($val.data('select2')) $val.select2('destroy');

    $val.select2({
      width:'100%',
      placeholder: placeholderText || 'Select value',
      allowClear:true,
      tags:true, // allow typing new values if needed
      minimumInputLength: 0,
      ajax:{
        url:'asset-variant-api.php',
        type:'POST',
        dataType:'json',
        delay:200,
        cache:true,
        data:function(params){
          return {
            action:'ATTR_VALUE_SEARCH',
            attr_key: normKey(key || ''),
            q: params.term || '',
            page: params.page || 1
          };
        },
        processResults:function(data, params){
          params.page = params.page || 1;
          return { results: data.results || [], pagination:{ more: !!data.more } };
        }
      }
    });
  }

  function updateValPlaceholder(rowId, key){
    key = normKey(key || '');
    if (!key){
      initAttrValSelect(rowId, '', 'Select value');
      return;
    }

    $.post('asset-variant-api.php', { action:'ATTR_VALUE_HINT', attr_key: key }, function(resp){
      let r; try{ r = JSON.parse(resp); }catch(e){ r = null; }
      const hint = (r && r.hint) ? r.hint : '';
      const ph = hint ? ('e.g. ' + hint) : 'Select value';
      initAttrValSelect(rowId, key, ph);
      buildVariantName();
    });
  }

  function getAttrs(){
    const map = {};
    const ordered = [];

    $('#attrBox .attr-row').each(function(){
      const rowId = $(this).data('row');
      const kRaw = $(`.attr-key[data-row="${rowId}"]`).val() || '';
      const vRaw = $(`.attr-val[data-row="${rowId}"]`).val() || '';

      const k = normKey(kRaw);
      const v = (vRaw||'').toString().trim();
      if (!k || !v) return;
      if (map[k]) return;

      map[k] = v;
      ordered.push({key:k, value:v});
    });

    return ordered;
  }

  function buildVariantName(){
    const assetText = $('#vAssetId option:selected').text() || '';
    if (!assetText) { $('#vName').val(''); return; }

    const base = assetText.split('[')[0].trim();
    const attrs = getAttrs();

    if (!attrs.length){
      $('#vName').val(base);
      return;
    }

    const parts = attrs.map(a => `${a.key} ${a.value}`);
    $('#vName').val(base + ', ' + parts.join(', '));
  }

  // ---------- Reserve / Submit ----------
  function reserveVariantCode(){
    const asset_id = ($('#vAssetId').val()||'').trim();
    if (!asset_id) return;

    $('#vAlert').html('<div class="text-muted">Generating variant code...</div>');
    $.post('asset-variant-api.php', { action:'RESERVE', asset_id: asset_id }, function(resp){
      let r; try{ r=JSON.parse(resp);}catch(e){
        $('#vAlert').html(bsAlert('danger','Invalid reserve response.'));
        return;
      }
      if (!r.ok){
        $('#vAlert').html(bsAlert('danger', r.msg||'Reserve failed.'));
        return;
      }
      $('#vResId').val(r.reservation_id);
      $('#vVariantCode').val(r.variant_code);
      $('#vCodeShow').val(r.variant_code);
      renderBarcode(r.variant_code);
      $('#vAlert').html(bsAlert('success','Variant code generated: <b>'+r.variant_code+'</b>'));
    }).fail(function(xhr){
      $('#vAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function submitVariant(){
    buildVariantName();

    const asset_id = ($('#vAssetId').val()||'').trim();
    const res_id = ($('#vResId').val()||'').trim();
    const vcode = ($('#vVariantCode').val()||'').trim();
    const vname = ($('#vName').val()||'').trim();
    const attrs = getAttrs();

    // extras
    const has_expiry = ($('#vHasExpiry').val()==='1') ? 1 : 0;
    const expiry_date = ($('#vExpiryDate').val() || '').trim();

    const has_serial = ($('#vHasSerial').val()==='1') ? 1 : 0;
    const serial_no = ($('#vSerialNo').val() || '').trim();

    const has_warranty = ($('#vHasWarranty').val()==='1') ? 1 : 0;
    const warranty_mode = ($('#vWarrantyMode').val() || 'DATE').trim();
    const warranty_date = ($('#vWarrantyDate').val() || '').trim();
    const warranty_text = ($('#vWarrantyText').val() || '').trim();

    if (!asset_id) { $('#vResult').html(bsAlert('danger','Select Master Item.')); return; }
    if (!res_id || !vcode) { $('#vResult').html(bsAlert('danger','Generate Variant Code first.')); return; }

    $('#vResult').html('<div class="text-muted">Submitting variant...</div>');
    $.post('asset-variant-api.php', {
      action:'SUBMIT',
      asset_id: asset_id,
      reservation_id: res_id,
      variant_name: vname,
      attrs_json: JSON.stringify(attrs),

      has_expiry: has_expiry,
      expiry_date: expiry_date,

      has_serial: has_serial,
      serial_no: serial_no,

      has_warranty: has_warranty,
      warranty_mode: warranty_mode,
      warranty_date: warranty_date,
      warranty_text: warranty_text
    }, function(resp){
      let r; try{ r=JSON.parse(resp);}catch(e){
        $('#vResult').html(bsAlert('danger','Invalid submit response.'));
        return;
      }
      if (!r.ok){
        $('#vResult').html(bsAlert('danger', r.msg||'Submit failed.'));
        return;
      }

      $('#vResult').html(bsAlert('success', r.msg));

      // reset form (keep master selection)
      $('#vResId').val('');
      $('#vVariantCode').val('');
      $('#vCodeShow').val('');
      $('#vBarcode').empty(); $('#vBarcodeText').text('');
      $('#vAlert').html('');
      $('#attrBox').empty();
      $('#vName').val('');

      resetExtras();

      // start again with common rows
      addAttrRow('COLOR');
      addAttrRow('SIZE');

      // next code for same master
      reserveVariantCode();
      loadVariantPending();
    }).fail(function(xhr){
      $('#vResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function loadVariantPending(){
    $('#variantPendingBox').html('<div class="text-muted">Loading variants...</div>');
    $.post('asset-variant-api.php', { action:'LIST' }, function(html){
      $('#variantPendingBox').html(html);
      initTooltips();
    });
  }

  // ---------- Events ----------
  $('#btnAddAttr').on('click', function(){ addAttrRow(''); });

  $(document).on('click', '.btn-del-attr', function(){
    const row = $(this).data('row');
    $(`.attr-row[data-row="${row}"]`).remove();
    buildVariantName();
  });

  // when KEY changes -> update value dropdown + placeholder
  $(document).on('change', '.attr-key', function(){
    const rowId = $(this).data('row');
    const key = $(this).val() || '';
    // clear current value selection
    const $val = $(`.attr-val[data-row="${rowId}"]`);
    if ($val.length){
      $val.val(null).trigger('change');
    }
    updateValPlaceholder(rowId, key);
  });

  // when VALUE changes -> update name
  $(document).on('change', '.attr-val', function(){
    buildVariantName();
  });

  $('#btnVariantSubmit').on('click', submitVariant);

  // Extras toggles
  $('#vHasExpiry,#vHasSerial,#vHasWarranty,#vWarrantyMode').on('change', toggleExtras);

  // approvals
  $(document).on('click', '.btn-v-approve', function(){
    const id = $(this).data('id');
    $('#vApAlert').html('<div class="text-muted">Approving...</div>');
    $.post('asset-variant-api.php', { action:'APPROVE', id:id }, function(html){
      $('#vApAlert').html(html);
      loadVariantPending();
    });
  });

  $(document).on('click', '.btn-v-reject', function(){
    const id = $(this).data('id');
    const reason = prompt('Reject reason?');
    if (!reason) return;
    $('#vApAlert').html('<div class="text-muted">Rejecting...</div>');
    $.post('asset-variant-api.php', { action:'REJECT', id:id, reject_reason: reason }, function(html){
      $('#vApAlert').html(html);
      loadVariantPending();
    });
  });

  // MASTER SELECT2 (AJAX)
  if ($.fn.select2){
    $('#vAssetId').select2({
      width: '100%',
      placeholder: 'Select Master Item',
      allowClear: true,
      minimumInputLength: 1,
      ajax: {
        url: 'asset-variant-api.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        cache: true,
        data: function (params) {
          return {
            action: 'ASSET_SEARCH',
            q: params.term || '',
            page: params.page || 1
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;
          return {
            results: data.results || [],
            pagination: { more: !!data.more }
          };
        }
      }
    });

    $('#vAssetId').on('select2:select', function(){
      $('#vResId').val('');
      $('#vVariantCode').val('');
      $('#vCodeShow').val('');
      $('#vBarcode').empty(); $('#vBarcodeText').text('');
      $('#vAlert').html('');
      $('#attrBox').empty();
      $('#vName').val('');

      resetExtras();

      // common attrs
      addAttrRow('COLOR');
      addAttrRow('SIZE');

      buildVariantName();
      reserveVariantCode();
    });

    $('#vAssetId').on('select2:clear', function(){
      $('#vResId').val('');
      $('#vVariantCode').val('');
      $('#vCodeShow').val('');
      $('#vBarcode').empty(); $('#vBarcodeText').text('');
      $('#vAlert').html('');
      $('#attrBox').empty();
      $('#vName').val('');
      resetExtras();
    });
  }

  // initial
  resetExtras();
  addAttrRow('COLOR');
  addAttrRow('SIZE');
  loadVariantPending();

})(jQuery);
</script>
