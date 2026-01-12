<?php
// mobile-allocation-new.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
require_once 'includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Mobile Allocation — Initiate New Connection</h5>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Mobile Number</label>
          <input type="text" id="maMobile" class="form-control" placeholder="e.g. 0765455585 or 765455585">
          <div class="form-text">System converts 076xxxxxxx → 76xxxxxxx (9 digits).</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">HRIS No</label>
          <input type="text" id="maHris" class="form-control" placeholder="e.g. 6428 / 006428 / Police-01">
          <div class="form-text">Numeric HRIS becomes 6 digits. Only <b>Active</b> employees allowed.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Effective From</label>
          <input type="date" id="maEff" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Owner Name (auto)</label>
          <input type="text" id="maOwner" class="form-control" readonly>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Voice/Data</label>
          <select id="maVoiceData" class="form-select">
            <option value="">-- Select --</option>
            <option value="Voice">Voice</option>
            <option value="Data">Data</option>
            <option value="Voice+Data">Voice+Data</option>
          </select>
          <div class="form-text">Can be changed by approver.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Connection Status</label>
          <input type="text" class="form-control" value="Connected" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Company Hierarchy (auto)</label>
          <input type="text" id="maHierarchy" class="form-control" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">NIC No (auto)</label>
          <input type="text" id="maNic" class="form-control" readonly>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-outline-secondary" id="btnCheckMobile" type="button">Check Mobile</button>
          <button class="btn btn-outline-secondary" id="btnCheckHris" type="button">Check HRIS</button>
          <button class="btn btn-success" id="btnInitiate" type="button">Initiate (Pending)</button>
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
  let hrisLocked = false;

  function normalizeMobile(v){
    let d = (v||'').toString().replace(/\D+/g,'');
    if (d.length === 10 && d.startsWith('0')) d = d.substring(1);
    return d;
  }
  function normalizeHris(v){
    let t = (v||'').toString().trim();
    if (/^\d+$/.test(t)) t = t.padStart(6,'0');
    return t;
  }
  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
  }
  function payload(){
    const mobile = normalizeMobile($('#maMobile').val());
    const hris = normalizeHris($('#maHris').val());
    $('#maMobile').val(mobile);
    $('#maHris').val(hris);
    return {
      mobile,
      hris,
      effective_from: ($('#maEff').val()||'').trim(),
      voice_data: ($('#maVoiceData').val()||'').trim()
    };
  }

  function doCheckMobile(){
    const p = payload();
    if (!p.mobile) { $('#maMobileBox').html(''); return; }
    $('#maMobileBox').html('<div class="text-muted">Checking mobile...</div>');
    $.post('mobile-allocation-check-mobile.php', { mobile: p.mobile }, function(html){
      $('#maMobileBox').html(html);
    }).fail(function(xhr){
      $('#maMobileBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  function doCheckHris(){
    const p = payload();
    if (!p.hris) { $('#maHrisBox').html(''); return; }
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
        $('#maHrisBox').html(bsAlert('danger', (res && res.error) ? res.error : 'HRIS check failed.'));
        return;
      }
      hrisLocked = !!res.locked;
      $('#maHrisBox').html(res.html || '');
      if (res.emp) {
        $('#maOwner').val(res.emp.name || '');
        $('#maNic').val(res.emp.nic || '');
        $('#maHierarchy').val(res.emp.hierarchy || '');
      }
    })
    .fail(function(xhr){
      hrisLocked = true;
      $('#maHrisBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
    });
  }

  function doInitiate(){
    const p = payload();
    if (hrisLocked) { $('#maResult').html(bsAlert('danger','HRIS is not Active.')); return; }
    if (!/^\d{9}$/.test(p.mobile)) { $('#maResult').html(bsAlert('danger','Mobile must be 9 digits.')); return; }
    if (!p.hris) { $('#maResult').html(bsAlert('danger','HRIS is required.')); return; }
    if (!p.effective_from) { $('#maResult').html(bsAlert('danger','Effective From is required.')); return; }
    if (!p.voice_data) { $('#maResult').html(bsAlert('danger','Voice/Data is required.')); return; }

    $('#maResult').html('<div class="text-muted">Initiating...</div>');
    $.post('mobile-allocation-initiate-save.php', p, function(html){
      $('#maResult').html(html);
      doCheckMobile(); doCheckHris();
    }).fail(function(xhr){
      $('#maResult').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  $('#btnCheckMobile').on('click', doCheckMobile);
  $('#btnCheckHris').on('click', doCheckHris);
  $('#btnInitiate').on('click', doInitiate);

  let tMob=null, tHris=null;
  $('#maMobile').on('blur', function(){ clearTimeout(tMob); tMob=setTimeout(doCheckMobile, 150); });
  $('#maHris').on('blur', function(){ clearTimeout(tHris); tHris=setTimeout(doCheckHris, 150); });

})(jQuery);
</script>
