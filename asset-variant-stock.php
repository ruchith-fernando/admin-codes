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
/* select2 height match bootstrap */
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

/* attribute inputs */
.attr-row .form-control{ height: calc(2.375rem + 2px); }

/* barcode responsive */
.barcode-box{
  width: 100%;
  overflow: hidden;
}
#vBarcode{
  width: 100%;
  height: 56px;
}
</style>

<div class="content font-size">
  <div class="container-fluid">

    <!-- VARIANT MAKER -->
    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Variants (SKU) — New / Submit</h5>
      <div id="vAlert"></div>

      <input type="hidden" id="vResId" value="">
      <input type="hidden" id="vVariantCode" value="">

      <div class="row g-3">

        <div class="col-md-12">
          <label class="form-label fw-bold">Master Item (Approved)</label>

          <!-- FULL DROPDOWN (AJAX SELECT2) -->
          <select id="vAssetId" class="form-select" style="width:100%">
            <option value="">-- Select Item --</option>
          </select>

          <div class="form-text">Type to search (Select2 AJAX).</div>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Variant Attributes (optional)</label>

          <div class="border rounded p-3 bg-light">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
              <div class="text-muted small me-2">
                Example: COLOR=BLUE, SIZE=XL, GENDER=MALE, SLEEVE=SHORT, COLLAR_SIZE=15
              </div>
              <button class="btn btn-sm btn-outline-primary" type="button" id="btnAddAttr">+ Add Attribute</button>
            </div>

            <div id="attrBox"></div>

            <div class="form-text mt-2">
              Mugs can have zero attributes (just submit). Shirts/t-shirts can have many.
            </div>
          </div>
        </div>

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

    <!-- VARIANT APPROVALS -->
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

  function addAttrRow(k,v){
    const id = 'ar'+Math.random().toString(16).slice(2);
    const row = `
      <div class="row g-2 align-items-center attr-row mb-2" data-row="${id}">
        <div class="col-md-4">
          <input class="form-control attr-key" placeholder="KEY (e.g. SIZE)" value="${k?String(k):''}">
        </div>
        <div class="col-md-7">
          <input class="form-control attr-val" placeholder="VALUE (e.g. XL)" value="${v?String(v):''}">
        </div>
        <div class="col-md-1 text-end">
          <button type="button" class="btn btn-outline-danger btn-sm btn-del-attr" data-row="${id}">×</button>
        </div>
      </div>`;
    $('#attrBox').append(row);
  }

  function getAttrs(){
    const map = {};
    const ordered = [];
    $('#attrBox .attr-row').each(function(){
      const kRaw = $(this).find('.attr-key').val();
      const vRaw = $(this).find('.attr-val').val();
      const k = normKey(kRaw);
      const v = (vRaw||'').toString().trim();
      if (!k || !v) return;
      if (map[k]) return;
      map[k]=v;
      ordered.push({key:k, value:v});
      $(this).find('.attr-key').val(k);
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

    if (!asset_id) { $('#vResult').html(bsAlert('danger','Select Master Item.')); return; }
    if (!res_id || !vcode) { $('#vResult').html(bsAlert('danger','Generate Variant Code first.')); return; }

    $('#vResult').html('<div class="text-muted">Submitting variant...</div>');
    $.post('asset-variant-api.php', {
      action:'SUBMIT',
      asset_id: asset_id,
      reservation_id: res_id,
      variant_name: vname,
      attrs_json: JSON.stringify(attrs)
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

      // convenience rows again
      addAttrRow('COLOR','');
      addAttrRow('SIZE','');
      buildVariantName();

      // generate next code for next variant (same master)
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

  // attribute UI
  $('#btnAddAttr').on('click', function(){ addAttrRow('',''); });

  $(document).on('click', '.btn-del-attr', function(){
    const row = $(this).data('row');
    $(`.attr-row[data-row="${row}"]`).remove();
    buildVariantName();
  });

  $(document).on('blur change', '.attr-key,.attr-val', function(){
    buildVariantName();
  });

  $('#btnVariantSubmit').on('click', submitVariant);

  // ✅ FULL DROPDOWN (AJAX SELECT2)
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

    // on select -> reset + build name + reserve code
    $('#vAssetId').on('select2:select', function(){
      $('#vResId').val('');
      $('#vVariantCode').val('');
      $('#vCodeShow').val('');
      $('#vBarcode').empty(); $('#vBarcodeText').text('');
      $('#vAlert').html('');
      $('#attrBox').empty();
      $('#vName').val('');

      addAttrRow('COLOR','');
      addAttrRow('SIZE','');

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
    });
  }

  // start with 2 rows as convenience (before selecting master too)
  addAttrRow('COLOR','');
  addAttrRow('SIZE','');

  loadVariantPending();

})(jQuery);
</script>
