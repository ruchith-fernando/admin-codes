<?php
// approval-chain-admin.php
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

// Departments
$depts = [];
if ($stmt = $conn->prepare("SELECT department_id, department_name FROM tbl_admin_departments WHERE is_active=1 ORDER BY department_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $depts[] = $row;
  $stmt->close();
}

// Users (approver dropdown) – you can later filter only active approvers etc.
$users = [];
if ($stmt = $conn->prepare("SELECT id, name, designation, branch_name FROM tbl_admin_users ORDER BY name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $users[] = $row;
  $stmt->close();
}
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
.table td, .table th { vertical-align: middle; }
</style>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Admin — Approval Chains</h5>
      <div id="acAdminAlert"></div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">Department</label>
          <select id="deptId" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($depts as $d): ?>
              <option value="<?= (int)$d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Chains are created per department. Users can only choose chains for their department.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Chain Name</label>
          <input type="text" id="chainName" class="form-control" placeholder="e.g. PR Chain - Standard / PR Chain - Urgent">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Version</label>
          <input type="number" id="versionNo" class="form-control" value="1" min="1">
        </div>

        <div class="col-md-2">
          <label class="form-label fw-bold">Active</label>
          <select id="isActive" class="form-select">
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-8 d-flex align-items-end justify-content-end gap-2">
          <button class="btn btn-outline-primary" type="button" id="btnCreateChain">Create Chain</button>
        </div>
      </div>

      <hr>

      <h6 class="text-primary mb-2">Chains for Department</h6>
      <div id="chainsBox" class="mt-2"></div>

      <hr>

      <h6 class="text-primary mb-2">Chain Steps</h6>

      <input type="hidden" id="selectedChainId" value="">

      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label fw-bold">Step Order</label>
          <input type="number" id="stepOrder" class="form-control" value="1" min="1">
        </div>

        <div class="col-md-10">
          <label class="form-label fw-bold">Approver</label>
          <select id="approverUserId" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>">
                <?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['designation'] ?? '-') ?> (<?= htmlspecialchars($u['branch_name'] ?? '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-12 d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" type="button" id="btnLoadSteps">Load Steps</button>
          <button class="btn btn-success" type="button" id="btnAddStep">Add Step</button>
        </div>
      </div>

      <div class="mt-3" id="stepsBox"></div>
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

  function loadChains(){
    const dept = ($('#deptId').val()||'').trim();
    if(!dept){ $('#chainsBox').html('<div class="text-muted">Select a department.</div>'); return; }
    $('#chainsBox').html('<div class="text-muted">Loading chains...</div>');
    $.post('approval-chain-admin-save.php', { action:'LIST_CHAINS', department_id: dept }, function(html){
      $('#chainsBox').html(html);
    }).fail(function(xhr){
      $('#chainsBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function loadSteps(){
    const chainId = ($('#selectedChainId').val()||'').trim();
    if(!chainId){ $('#stepsBox').html(bsAlert('warning','Select a chain first.')); return; }
    $('#stepsBox').html('<div class="text-muted">Loading steps...</div>');
    $.post('approval-chain-admin-save.php', { action:'LIST_STEPS', chain_id: chainId }, function(html){
      $('#stepsBox').html(html);
    }).fail(function(xhr){
      $('#stepsBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#deptId').on('change', function(){
    $('#selectedChainId').val('');
    $('#stepsBox').html('');
    loadChains();
  });

  $('#btnCreateChain').on('click', function(){
    const department_id = ($('#deptId').val()||'').trim();
    const chain_name = ($('#chainName').val()||'').trim();
    const version_no = ($('#versionNo').val()||'1').trim();
    const is_active = ($('#isActive').val()||'0').trim();

    if(!department_id){ $('#acAdminAlert').html(bsAlert('danger','Department is required.')); return; }
    if(!chain_name){ $('#acAdminAlert').html(bsAlert('danger','Chain name is required.')); return; }

    $('#acAdminAlert').html('<div class="text-muted">Creating chain...</div>');

    $.post('approval-chain-admin-save.php', {
      action:'CREATE_CHAIN',
      department_id, chain_name, version_no, is_active
    }, function(resp){
      $('#acAdminAlert').html(resp);
      $('#chainName').val('');
      loadChains();
    }).fail(function(xhr){
      $('#acAdminAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // select chain from list
  $(document).on('click', '.btn-select-chain', function(){
    const chainId = $(this).data('id');
    $('#selectedChainId').val(chainId);
    $('#acAdminAlert').html(bsAlert('info','Selected chain ID: <b>'+chainId+'</b>'));
    loadSteps();
  });

  // toggle active
  $(document).on('click', '.btn-toggle-chain', function(){
    const chainId = $(this).data('id');
    $('#acAdminAlert').html('<div class="text-muted">Updating...</div>');
    $.post('approval-chain-admin-save.php', { action:'TOGGLE_CHAIN', chain_id: chainId }, function(resp){
      $('#acAdminAlert').html(resp);
      loadChains();
    }).fail(function(xhr){
      $('#acAdminAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  $('#btnLoadSteps').on('click', loadSteps);

  $('#btnAddStep').on('click', function(){
    const chain_id = ($('#selectedChainId').val()||'').trim();
    const step_order = ($('#stepOrder').val()||'').trim();
    const approver_user_id = ($('#approverUserId').val()||'').trim();

    if(!chain_id){ $('#stepsBox').html(bsAlert('warning','Select a chain first.')); return; }
    if(!step_order){ $('#stepsBox').html(bsAlert('danger','Step order is required.')); return; }
    if(!approver_user_id){ $('#stepsBox').html(bsAlert('danger','Approver is required.')); return; }

    $('#stepsBox').html('<div class="text-muted">Adding step...</div>');

    $.post('approval-chain-admin-save.php', {
      action:'ADD_STEP', chain_id, step_order, approver_user_id
    }, function(resp){
      $('#stepsBox').prepend(resp);
      loadSteps();
    }).fail(function(xhr){
      $('#stepsBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // delete step
  $(document).on('click', '.btn-del-step', function(){
    const id = $(this).data('id');
    if(!confirm('Delete this step?')) return;
    $.post('approval-chain-admin-save.php', { action:'DELETE_STEP', step_id: id }, function(resp){
      $('#acAdminAlert').html(resp);
      loadSteps();
    });
  });

  // Select2
  if ($.fn.select2) {
    $('#deptId').select2({ width:'100%', placeholder:'Select Department' });
    $('#approverUserId').select2({ width:'100%', placeholder:'Select Approver' });
  }

})(jQuery);
</script>
