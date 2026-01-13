<?php
// asset-card.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

// Shared-host safe session
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

// Dropdown data
$types = $cats = $buds = [];

if ($stmt = $conn->prepare("SELECT id, type_name FROM tbl_admin_asset_types WHERE is_active=1 ORDER BY type_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $types[] = $row;
  $stmt->close();
}
if ($stmt = $conn->prepare("SELECT id, category_name, category_code FROM tbl_admin_categories WHERE is_active=1 ORDER BY category_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $cats[] = $row;
  $stmt->close();
}
if ($stmt = $conn->prepare("SELECT id, budget_name, budget_code FROM tbl_admin_budgets WHERE is_active=1 ORDER BY budget_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $buds[] = $row;
  $stmt->close();
}
?>

<style>
/* Make Select2 height match Bootstrap .form-select height */
.select2-container .select2-selection--single{
  height: calc(2.375rem + 2px) !important; /* Bootstrap form control height */
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
</style>

<div class="content font-size">
  <div class="container-fluid">

    <!-- MAKER -->
    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Asset Card — New</h5>
      <div id="acAlert"></div>

      <input type="hidden" id="acReservationId" value="">

      <div class="row g-3">

        <div class="col-md-12">
          <label class="form-label fw-bold">Item Name (Unique)</label>
          <input type="text" id="acItemName" class="form-control"
                 placeholder="e.g. 30th Anniversary Mugs / Blue Pens">
          <div class="form-text">Must be unique (system-wide).</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Asset Type</label>
          <select id="acAssetType" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($types as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Category</label>
          <select id="acCategory" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= htmlspecialchars($c['category_name']) ?> (<?= htmlspecialchars($c['category_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Budget</label>
          <select id="acBudget" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($buds as $b): ?>
              <option value="<?= (int)$b['id'] ?>">
                <?= htmlspecialchars($b['budget_name']) ?> (<?= htmlspecialchars($b['budget_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Maker Note</label>
          <textarea id="acMakerNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Item Code (Auto)</label>
          <input type="text" id="acItemCode" class="form-control" value="" readonly>
          <div class="form-text">Generated when Category + Budget are selected.</div>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Barcode</label>
          <div class="border rounded p-3 bg-light">
            <svg id="acBarcode"></svg>
            <div class="mt-2 fw-bold" id="acBarcodeText"></div>
            <div class="small text-muted" id="acBarcodeHint"></div>
          </div>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-success" id="btnAcSubmit" type="button">Submit for Approval</button>
        </div>

      </div>

      <div class="mt-3" id="acResult"></div>
    </div>

    <!-- APPROVALS -->
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Asset Card — Approvals (Dual Control)</h5>
      <div id="apAlert"></div>
      <div id="pendingBox" class="mt-2"></div>
    </div>

  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Asset Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rejAssetId" value="">
        <label class="form-label fw-bold">Reject Reason</label>
        <textarea id="rejReason" class="form-control" rows="3" placeholder="Type reason..."></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" type="button" id="btnDoReject">Reject</button>
      </div>
    </div>
  </div>
</div>

<!-- Your local JsBarcode -->
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
    $('#acBarcodeText').text(code||'');
    $('#acBarcode').empty();
    $('#acBarcodeHint').text('');
    if (!code) return;

    if (typeof JsBarcode !== 'function') {
      $('#acBarcodeHint').text('JsBarcode not loaded. Check: assets/js/JsBarcode.all.min.js');
      return;
    }

    try {
      JsBarcode("#acBarcode", code, { format:"CODE128", displayValue:false });
    } catch(e) {
      $('#acBarcodeHint').text('Barcode error: '+e.message);
    }
  }

  function reserveCode(){
    const category_id = ($('#acCategory').val()||'').trim();
    const budget_id   = ($('#acBudget').val()||'').trim();
    const oldResId    = ($('#acReservationId').val()||'').trim();

    if (!category_id || !budget_id) return;

    $('#acAlert').html('<div class="text-muted">Generating item code...</div>');

    $.post('asset-card-save.php', {
      action: 'RESERVE',
      category_id: category_id,
      budget_id: budget_id,
      cancel_reservation_id: oldResId
    }, function(resp){
      let r;
      try { r = JSON.parse(resp); } catch(e){
        $('#acAlert').html(bsAlert('danger','Invalid reserve response.'));
        return;
      }
      if (!r.ok) {
        $('#acAlert').html(bsAlert('danger', r.msg || 'Reserve failed.'));
        return;
      }

      $('#acReservationId').val(r.reservation_id);
      $('#acItemCode').val(r.item_code);
      renderBarcode(r.item_code);

      $('#acAlert').html(bsAlert('success','Item code generated: <b>'+r.item_code+'</b>'));
    }).fail(function(xhr){
      $('#acAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function submit(){
    const reservation_id = ($('#acReservationId').val()||'').trim();
    const item_code = ($('#acItemCode').val()||'').trim();

    const item_name     = ($('#acItemName').val()||'').trim();
    const asset_type_id = ($('#acAssetType').val()||'').trim();

    if (!reservation_id || !item_code) {
      $('#acResult').html(bsAlert('danger','Select Category + Budget to generate Item Code.'));
      return;
    }
    if (!item_name) {
      $('#acResult').html(bsAlert('danger','Item Name is required.'));
      return;
    }
    if (!asset_type_id) {
      $('#acResult').html(bsAlert('danger','Asset Type is required.'));
      return;
    }

    $('#acResult').html('<div class="text-muted">Submitting for approval...</div>');

    $.post('asset-card-save.php', {
      action: 'SUBMIT',
      reservation_id: reservation_id,
      item_name: item_name,
      asset_type_id: asset_type_id
    }, function(resp){
      let r;
      try { r = JSON.parse(resp); } catch(e){
        $('#acResult').html(bsAlert('danger','Invalid submit response.'));
        return;
      }
      if (!r.ok) {
        $('#acResult').html(bsAlert('danger', r.msg || 'Submit failed.'));
        return;
      }

      $('#acResult').html(bsAlert('success', r.msg));

      // reset
      $('#acReservationId').val('');
      $('#acItemCode').val('');
      $('#acItemName').val('');
      $('#acAssetType').val('');
      $('#acCategory').val('').trigger('change');
      $('#acBudget').val('').trigger('change');
      $('#acBarcode').empty();
      $('#acBarcodeText').text('');
      $('#acAlert').html('');

      loadList();
    }).fail(function(xhr){
      $('#acResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function loadList(){
    $('#pendingBox').html('<div class="text-muted">Loading approvals...</div>');
    $.post('asset-card-approve.php', { action:'LIST' }, function(html){
      $('#pendingBox').html(html);
      initTooltips();
    }).fail(function(xhr){
      $('#pendingBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  // Approve
  $(document).on('click', '.btn-approve', function(){
    const id = $(this).data('id');
    $('#apAlert').html('<div class="text-muted">Approving...</div>');
    $.post('asset-card-approve.php', { action:'APPROVE', id:id }, function(html){
      $('#apAlert').html(html);
      loadList();
    }).fail(function(xhr){
      $('#apAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // Reject open modal
  $(document).on('click', '.btn-reject', function(){
    const id = $(this).data('id');
    $('#rejAssetId').val(id);
    $('#rejReason').val('');
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
  });

  // Reject confirm
  $('#btnDoReject').on('click', function(){
    const id = ($('#rejAssetId').val()||'').trim();
    const reason = ($('#rejReason').val()||'').trim();
    if (!reason) { alert('Reject reason is required.'); return; }

    $('#apAlert').html('<div class="text-muted">Rejecting...</div>');
    $.post('asset-card-approve.php', { action:'REJECT', id:id, reject_reason: reason }, function(html){
      $('#apAlert').html(html);
      loadList();
      bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
    }).fail(function(xhr){
      $('#apAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // Reserve code on Category/Budget change
  $('#acCategory, #acBudget').on('change', reserveCode);

  // Submit
  $('#btnAcSubmit').on('click', submit);

  // Select2
  if ($.fn.select2) {
    $('#acCategory').select2({ width:'100%', placeholder:'Select Category' });
    $('#acBudget').select2({ width:'100%', placeholder:'Select Budget' });
  } else {
    $('#acAlert').html(bsAlert('warning','Select2 not loaded. Please include Select2 JS/CSS.'));
  }

  loadList();

})(jQuery);
</script>
