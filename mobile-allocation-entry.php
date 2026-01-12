<?php
// mobile-allocation-entry.php (AJAX-in-page, works inside main.php wrapper)
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');


?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-3 text-primary">Mobile Allocation — New / Transfer</h5>

      <div id="maAlert"></div>

      <div class="row g-3">

        <div class="col-md-4">
          <label class="form-label fw-bold">Mobile Number (9 digits)</label>
          <input type="text" id="maMobile" class="form-control" placeholder="e.g. 765455585">
          <div class="form-text">Saved format in DB: 9 digits (no leading 0).</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">HRIS No</label>
          <input type="text" id="maHris" class="form-control" placeholder="e.g. 006428 or Police - XXX">
          <div class="form-text">006428 (6 digits) will lookup employee. Text will skip lookup.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Effective From</label>
          <input type="date" id="maEff" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Owner Name</label>
          <input type="text" id="maOwner" class="form-control" placeholder="Auto-filled if HRIS found">
        </div>

        <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
          <button class="btn btn-outline-secondary" id="btnCheckMobile" type="button">Check Mobile</button>
          <button class="btn btn-outline-secondary" id="btnCheckHris" type="button">Check HRIS</button>
          <button class="btn btn-success" id="btnSaveAlloc" type="button">Save</button>
        </div>

      </div>

      <div class="mt-3" id="maMobileBox"></div>
      <div class="mt-2" id="maHrisBox"></div>
      <div class="mt-3" id="maResult"></div>

    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  let hrisLocked = false; // locked if HRIS is not Active (numeric 6-digit rule)

  function digitsOnly(v){ return (v||'').toString().replace(/\D+/g,''); }

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }

  function getPayload(){
    return {
      mobile: digitsOnly($('#maMobile').val()),
      hris: ($('#maHris').val()||'').trim(),
      owner: ($('#maOwner').val()||'').trim(),
      effective_from: ($('#maEff').val()||'').trim()
    };
  }

  function setSaveEnabled(on){
    $('#btnSaveAlloc').prop('disabled', !on);
  }

  // ---------- Check Mobile ----------
  function doCheckMobile(){
    const p = getPayload();
    $('#maMobile').val(p.mobile);

    $('#maMobileBox').html('<div class="text-muted">Checking mobile...</div>');

    $.post('mobile-allocation-check-mobile.php', { mobile: p.mobile }, function(html){
      $('#maMobileBox').html(html);
    }).fail(function(xhr){
      $('#maMobileBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  // ---------- Check HRIS ----------
  function doCheckHris(){
    const p = getPayload();
    $('#maHrisBox').html('<div class="text-muted">Checking HRIS...</div>');

    $.ajax({
      url: 'mobile-allocation-check-hris.php',
      method: 'POST',
      data: { hris: p.hris },
      dataType: 'json'
    })
    .done(function(res){
      if (!res || !res.ok) {
        hrisLocked = true;
        setSaveEnabled(false);
        $('#maHrisBox').html(bsAlert('danger', (res && res.error) ? res.error : 'HRIS check failed.'));
        return;
      }

      hrisLocked = !!res.locked;
      $('#maHrisBox').html(res.html || '');

      // auto fill owner if found and owner input is empty
      if (res.owner_name && !($('#maOwner').val()||'').trim()) {
        $('#maOwner').val(res.owner_name);
      }

      // lock/unlock save
      setSaveEnabled(!hrisLocked);
    })
    .fail(function(xhr){
      hrisLocked = true;
      setSaveEnabled(false);
      $('#maHrisBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  // ---------- Save ----------
  function doSave(){
    if (hrisLocked) {
      $('#maResult').html(bsAlert('danger', 'HRIS is not Active. Please change HRIS.'));
      return;
    }

    const p = getPayload();
    $('#maMobile').val(p.mobile);

    $('#maResult').html('<div class="text-muted">Saving...</div>');

    $.post('mobile-allocation-save.php', p, function(html){
      $('#maResult').html(html);
    }).fail(function(xhr){
      $('#maResult').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  // ---------- Button clicks ----------
  $('#btnCheckMobile').on('click', doCheckMobile);
  $('#btnCheckHris').on('click', doCheckHris);
  $('#btnSaveAlloc').on('click', doSave);

  // ---------- Blur triggers (no clicking needed) ----------
  let tMob = null, tHris = null;

  $('#maMobile').on('blur', function(){
    clearTimeout(tMob);
    tMob = setTimeout(doCheckMobile, 150);
  });

  $('#maHris').on('blur', function(){
    clearTimeout(tHris);
    tHris = setTimeout(doCheckHris, 150);
  });

})(jQuery);
</script>
