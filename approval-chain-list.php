<?php
// approval-chain-list.php
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

// departments dropdown
$depts = [];
if ($stmt = $conn->prepare("SELECT department_id, department_name FROM tbl_admin_departments WHERE is_active=1 ORDER BY department_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $depts[] = $row;
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

    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Admin — View Approval Chains</h5>
      <div id="aclAlert"></div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">Department</label>
          <select id="aclDept" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($depts as $d): ?>
              <option value="<?= (int)$d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Pick a department to view its approval chains.</div>
        </div>

        <div class="col-md-6 d-flex align-items-end justify-content-end">
          <button type="button" class="btn btn-outline-primary" id="btnAclLoad">Load Chains</button>
        </div>
      </div>

      <hr>

      <h6 class="text-primary mb-2">Chains</h6>
      <div id="chainsBox" class="mt-2"></div>

      <hr>

      <h6 class="text-primary mb-2">Steps (Selected Chain)</h6>
      <input type="hidden" id="aclChainId" value="">
      <div id="stepsBox" class="mt-2"></div>

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
    const dept = ($('#aclDept').val()||'').trim();
    $('#aclChainId').val('');
    $('#stepsBox').html('');

    if(!dept){
      $('#chainsBox').html(bsAlert('warning','Select a department.'));
      return;
    }

    $('#chainsBox').html('<div class="text-muted">Loading chains...</div>');

    $.post('approval-chain-list-data.php', { action:'LIST_CHAINS', department_id: dept }, function(html){
      $('#chainsBox').html(html);
    }).fail(function(xhr){
      $('#chainsBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function loadSteps(chainId){
    if(!chainId){
      $('#stepsBox').html(bsAlert('warning','Select a chain to view steps.'));
      return;
    }
    $('#stepsBox').html('<div class="text-muted">Loading steps...</div>');

    $.post('approval-chain-list-data.php', { action:'LIST_STEPS', chain_id: chainId }, function(html){
      $('#stepsBox').html(html);
    }).fail(function(xhr){
      $('#stepsBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  $('#btnAclLoad').on('click', loadChains);
  $('#aclDept').on('change', loadChains);

  // click chain row button
  $(document).on('click', '.btn-view-steps', function(){
    const chainId = $(this).data('id');
    $('#aclChainId').val(chainId);
    loadSteps(chainId);
  });

  // Select2
  if ($.fn.select2) {
    $('#aclDept').select2({ width:'100%', placeholder:'Select Department' });
  }

})(jQuery);
</script>
