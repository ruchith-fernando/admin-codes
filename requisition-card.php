<?php
// requisition-card.php
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

// Load logged user's branch + department label (we will lock to this)
$user = null;
if ($stmt = $conn->prepare("SELECT id, name, branch_code, branch_name, category, category_auto FROM tbl_admin_users WHERE id=? LIMIT 1")) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
}
if (!$user) { die('User not found.'); }

$branch_code = (string)($user['branch_code'] ?? '');
$branch_name = (string)($user['branch_name'] ?? '');
$dept_label  = trim((string)($user['category'] ?? ''));
if ($dept_label === '') $dept_label = trim((string)($user['category_auto'] ?? ''));
if ($dept_label === '') $dept_label = 'UNKNOWN';

// Resolve department_id from tbl_admin_departments by name (must exist for approvals)
$department_id = 0;
$department_name = $dept_label;

if ($stmt = $conn->prepare("SELECT department_id, department_name FROM tbl_admin_departments WHERE is_active=1 AND department_name=? LIMIT 1")) {
  $stmt->bind_param("s", $dept_label);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $department_id = (int)$row['department_id'];
    $department_name = (string)$row['department_name'];
  }
  $stmt->close();
}

// Dropdown data
$uoms = $buds = [];

// UOM list
if ($stmt = $conn->prepare("SELECT uom, uom_name FROM tbl_admin_uom WHERE is_active=1 ORDER BY uom_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $uoms[] = $row;
  $stmt->close();
}

// Budgets list
if ($stmt = $conn->prepare("SELECT id, budget_name, budget_code FROM tbl_admin_budgets WHERE is_active=1 ORDER BY budget_name")) {
  $stmt->execute(); $res = $stmt->get_result();
  while($row = $res->fetch_assoc()) $buds[] = $row;
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

    <!-- MAKER -->
    <div class="card shadow bg-white rounded p-4 mb-4">
      <h5 class="mb-3 text-primary">Purchase Requisition — New</h5>
      <div id="prAlert"></div>

      <input type="hidden" id="prDepartmentId" value="<?= (int)$department_id ?>">
      <input type="hidden" id="prBranchCode" value="<?= htmlspecialchars($branch_code) ?>">

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label fw-bold">Your Branch</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars(trim($branch_code.' - '.$branch_name)) ?>" readonly>
          <div class="form-text">Requests are restricted to your branch.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Your Department</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($department_name) ?>" readonly>
          <div class="form-text">
            Department is locked to your profile (<?= htmlspecialchars($dept_label) ?>).
            <?php if($department_id<=0): ?>
              <span class="text-danger">Department not found in tbl_admin_departments. Add it to enable approvals.</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Approval Chain</label>
          <select id="prChainId" class="form-select"></select>
          <div class="form-text">Select approval chain/version for this requisition.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Required Date</label>
          <input type="date" id="prRequiredDate" class="form-control" value="">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Attachments (PDF / Images)</label>
          <input type="file" id="prFiles" class="form-control" multiple accept=".pdf,image/*">
          <div class="form-text">You can upload multiple files.</div>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Overall Justification (Optional)</label>
          <textarea id="prOverallJustification" class="form-control" rows="2" placeholder="Reason for requesting..."></textarea>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Approval Steps (Change approver per step only for this requisition)</label>
          <div class="table-responsive">
            <table class="table table-bordered table-sm" id="prStepsTable">
              <thead class="table-light">
                <tr>
                  <th style="width:10%">Step</th>
                  <th style="width:55%">Approver</th>
                  <th style="width:35%">Designation</th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="3" class="text-muted">Select an approval chain to load steps.</td></tr>
              </tbody>
            </table>
          </div>
          <div class="form-text">
            This does <b>NOT</b> change the master chain. It only applies to this requisition.
          </div>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Items</label>

          <div class="table-responsive">
            <table class="table table-bordered table-sm" id="prLinesTable">
              <thead class="table-light">
                <tr>
                  <th style="width:18%">Item Name</th>
                  <th style="width:26%">Specifications</th>
                  <th style="width:8%">Qty</th>
                  <th style="width:14%">UOM</th>
                  <th style="width:18%">Budget</th>
                  <th style="width:14%">Justification</th>
                  <th style="width:2%"></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <button class="btn btn-outline-primary btn-sm" id="btnPrAddLine" type="button">+ Add Line</button>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-success" id="btnPrSubmit" type="button">Submit for Approval</button>
        </div>

      </div>

      <div class="mt-3" id="prResult"></div>
    </div>

    <!-- APPROVALS -->
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Purchase Requisition — Approvals</h5>
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
        <h5 class="modal-title">Reject Requisition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rejReqId" value="">
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

  const UOMS = <?php echo json_encode($uoms, JSON_UNESCAPED_UNICODE); ?>;
  const BUDS = <?php echo json_encode($buds, JSON_UNESCAPED_UNICODE); ?>;

  // Approval chain runtime data
  let APPROVER_USERS = []; // [{id,name,designation,branch_name}]
  let CURRENT_STEPS  = []; // [{step_order, approver_user_id}]

  function escapeHtml(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function uomOptions(){
    let html = `<option value="">-- Select --</option>`;
    UOMS.forEach(u => {
      html += `<option value="${String(u.uom).replace(/"/g,'&quot;')}">${escapeHtml(u.uom_name)} (${escapeHtml(u.uom)})</option>`;
    });
    return html;
  }

  function budOptions(){
    let html = `<option value="">-- Select --</option>`;
    BUDS.forEach(b => {
      html += `<option value="${b.id}">${escapeHtml(b.budget_name)} (${escapeHtml(b.budget_code)})</option>`;
    });
    return html;
  }

  function lineRowHtml(){
    return `
      <tr>
        <td><input type="text" class="form-control form-control-sm pr-item-name" placeholder="e.g. Printer Toner"></td>
        <td><textarea class="form-control form-control-sm pr-spec" rows="2" placeholder="Specs..."></textarea></td>
        <td><input type="number" step="0.001" min="0" class="form-control form-control-sm pr-qty" value="1"></td>
        <td>
          <select class="form-select form-select-sm pr-uom">
            ${uomOptions()}
          </select>
        </td>
        <td>
          <select class="form-select form-select-sm pr-budget-id">
            ${budOptions()}
          </select>
        </td>
        <td><textarea class="form-control form-control-sm pr-just" rows="2" placeholder="Justification..."></textarea></td>
        <td class="text-center">
          <button type="button" class="btn btn-outline-danger btn-sm btn-line-del" title="Remove">
            <i class="bi bi-x-lg"></i>
          </button>
        </td>
      </tr>
    `;
  }

  function addLine(){
    $('#prLinesTable tbody').append(lineRowHtml());
    if ($.fn.select2) {
      $('#prLinesTable tbody tr:last .pr-uom').select2({ width:'100%' });
      $('#prLinesTable tbody tr:last .pr-budget-id').select2({ width:'100%' });
    }
  }

  function collectLines(){
    const lines = [];
    $('#prLinesTable tbody tr').each(function(){
      const item_name = ($(this).find('.pr-item-name').val()||'').trim();
      if (!item_name) return;

      const specifications = ($(this).find('.pr-spec').val()||'').trim();
      const qty = ($(this).find('.pr-qty').val()||'').trim();
      const uom = ($(this).find('.pr-uom').val()||'').trim();
      const budget_id = ($(this).find('.pr-budget-id').val()||'').trim();
      const line_justification = ($(this).find('.pr-just').val()||'').trim();

      lines.push({ item_name, specifications, qty, uom, budget_id, line_justification });
    });
    return lines;
  }

  // ===== Approval chain UI =====
  function userById(id){
    id = parseInt(id||0,10);
    return APPROVER_USERS.find(u => parseInt(u.id,10) === id) || null;
  }

  function userOptions(selectedId){
    let html = `<option value="">-- Select Approver --</option>`;
    APPROVER_USERS.forEach(u => {
      const sel = (String(u.id) === String(selectedId)) ? 'selected' : '';
      const label = `${u.name} — ${(u.designation||'-')} (${u.branch_name||''})`;
      html += `<option value="${u.id}" ${sel}>${escapeHtml(label)}</option>`;
    });
    return html;
  }

  function renderStepsTable(steps){
    const $tb = $('#prStepsTable tbody');
    $tb.empty();

    if (!steps || steps.length === 0) {
      $tb.append(`<tr><td colspan="3" class="text-muted">No steps found.</td></tr>`);
      return;
    }

    steps.forEach(st => {
      const u = userById(st.approver_user_id);
      const desig = u ? (u.designation || '-') : '-';

      $tb.append(`
        <tr data-step="${st.step_order}">
          <td>${st.step_order}</td>
          <td>
            <select class="form-select form-select-sm pr-step-approver">
              ${userOptions(st.approver_user_id)}
            </select>
          </td>
          <td class="pr-step-desig">${escapeHtml(desig)}</td>
        </tr>
      `);
    });

    if ($.fn.select2) {
      $('#prStepsTable .pr-step-approver').select2({ width:'100%' });
    }
  }

  function loadChains(){
    const deptId = ($('#prDepartmentId').val()||'').trim();
    if (!deptId || parseInt(deptId,10) <= 0) {
      $('#prAlert').html(bsAlert('danger','Your department is not mapped in tbl_admin_departments. Cannot load approval chains.'));
      return;
    }

    $('#prChainId').html(`<option value="">Loading...</option>`);

    $.post('requisition-chain.php', { action:'CHAINS', department_id: deptId }, function(resp){
      let r;
      try { r = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e){
        $('#prAlert').html(bsAlert('danger','Invalid response (chains).'));
        return;
      }

      if (!r.ok) {
        $('#prAlert').html(bsAlert('danger', r.msg || 'Cannot load approval chains.'));
        return;
      }

      const $sel = $('#prChainId');
      $sel.empty();

      if (!r.chains || r.chains.length === 0) {
        $sel.append(`<option value="">No active chains</option>`);
        $('#prAlert').html(bsAlert('danger','No active approval chains found for your department.'));
        renderStepsTable([]);
        return;
      }

      $sel.append(`<option value="">-- Select Chain --</option>`);
      r.chains.forEach(c => {
        const txt = `${c.chain_name} (v${c.version_no})`;
        $sel.append(`<option value="${c.chain_id}">${escapeHtml(txt)}</option>`);
      });

      renderStepsTable([]);
    }).fail(function(xhr){
      $('#prAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function loadStepsForChain(chainId){
    if (!chainId) {
      APPROVER_USERS = [];
      CURRENT_STEPS = [];
      renderStepsTable([]);
      return;
    }

    $('#prStepsTable tbody').html(`<tr><td colspan="3" class="text-muted">Loading steps...</td></tr>`);

    $.post('requisition-chain.php', { action:'STEPS', chain_id: chainId }, function(resp){
      let r;
      try { r = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e){
        $('#prAlert').html(bsAlert('danger','Invalid response (steps).'));
        return;
      }

      if (!r.ok) {
        $('#prAlert').html(bsAlert('danger', r.msg || 'Cannot load chain steps.'));
        renderStepsTable([]);
        return;
      }

      APPROVER_USERS = r.users || [];
      CURRENT_STEPS  = r.steps || [];
      renderStepsTable(CURRENT_STEPS);

    }).fail(function(xhr){
      $('#prAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  function collectStepOverrides(){
    const overrides = [];
    $('#prStepsTable tbody tr').each(function(){
      const step_order = parseInt($(this).attr('data-step')||'0', 10);
      const approver_user_id = parseInt($(this).find('.pr-step-approver').val()||'0', 10);
      if (step_order > 0 && approver_user_id > 0) {
        overrides.push({ step_order, approver_user_id });
      }
    });
    return overrides;
  }

  // update designation when user changes approver dropdown
  $(document).on('change', '.pr-step-approver', function(){
    const uid = parseInt($(this).val()||'0',10);
    const u = userById(uid);
    const desig = u ? (u.designation || '-') : '-';
    $(this).closest('tr').find('.pr-step-desig').text(desig);
  });

  // ===== Submit =====
  function submit(){
    const department_id = ($('#prDepartmentId').val()||'').trim();
    if (!department_id || parseInt(department_id,10) <= 0) {
      $('#prResult').html(bsAlert('danger','Your department is not mapped in tbl_admin_departments. Please add it first.'));
      return;
    }

    const chain_id = ($('#prChainId').val()||'').trim();
    if (!chain_id) {
      $('#prResult').html(bsAlert('danger','Please select an Approval Chain.'));
      return;
    }

    const required_date = ($('#prRequiredDate').val()||'').trim();
    const overall_justification = ($('#prOverallJustification').val()||'').trim();
    const lines = collectLines();

    if (lines.length === 0) {
      $('#prResult').html(bsAlert('danger','Add at least 1 line item (Item Name).'));
      return;
    }

    const step_overrides = collectStepOverrides();
    if (!step_overrides || step_overrides.length === 0) {
      $('#prResult').html(bsAlert('danger','Approval steps are not loaded. Please select a chain again.'));
      return;
    }

    const fd = new FormData();
    fd.append('action', 'SUBMIT');
    fd.append('required_date', required_date);
    fd.append('overall_justification', overall_justification);
    fd.append('lines_json', JSON.stringify(lines));

    // chain + per-requisition approver edits
    fd.append('chain_id', chain_id);
    fd.append('steps_override_json', JSON.stringify(step_overrides));

    // attachments
    const files = document.getElementById('prFiles').files;
    if (files && files.length) {
      for (let i=0;i<files.length;i++){
        fd.append('pr_files[]', files[i]);
      }
    }

    $('#prResult').html('<div class="text-muted">Submitting for approval...</div>');

    $.ajax({
      url: 'requisition-save.php',
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false
    }).done(function(resp){
      let r;
      try { r = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e){
        $('#prResult').html(bsAlert('danger','Invalid response.'));
        return;
      }
      if (!r.ok) {
        $('#prResult').html(bsAlert('danger', r.msg || 'Submit failed.'));
        return;
      }

      $('#prResult').html(bsAlert('success', r.msg || 'Submitted.'));

      // reset
      $('#prRequiredDate').val('');
      $('#prOverallJustification').val('');
      $('#prFiles').val('');
      $('#prLinesTable tbody').empty();
      addLine();

      // keep chain selected (better UX) and reload steps to ensure latest users
      loadStepsForChain($('#prChainId').val());

      loadList();
    }).fail(function(xhr){
      $('#prResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  // ===== Approvals list =====
  function loadList(){
    $('#pendingBox').html('<div class="text-muted">Loading approvals...</div>');
    $.post('requisition-approve.php', { action:'LIST' }, function(html){
      $('#pendingBox').html(html);
      initTooltips();
    }).fail(function(xhr){
      $('#pendingBox').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

  // Approve
  $(document).on('click', '.btn-approve', function(){
    const req_id = $(this).data('id');
    $('#apAlert').html('<div class="text-muted">Approving...</div>');
    $.post('requisition-approve.php', { action:'APPROVE', req_id:req_id }, function(html){
      $('#apAlert').html(html);
      loadList();
    }).fail(function(xhr){
      $('#apAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // Reject open modal
  $(document).on('click', '.btn-reject', function(){
    const req_id = $(this).data('id');
    $('#rejReqId').val(req_id);
    $('#rejReason').val('');
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
  });

  // Reject confirm
  $('#btnDoReject').on('click', function(){
    const req_id = ($('#rejReqId').val()||'').trim();
    const reason = ($('#rejReason').val()||'').trim();
    if (!reason) { alert('Reject reason is required.'); return; }

    $('#apAlert').html('<div class="text-muted">Rejecting...</div>');
    $.post('requisition-approve.php', { action:'REJECT', req_id:req_id, reject_reason: reason }, function(html){
      $('#apAlert').html(html);
      loadList();
      bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
    }).fail(function(xhr){
      $('#apAlert').html(bsAlert('danger','Server error: '+xhr.status));
    });
  });

  // Add/Remove line
  $('#btnPrAddLine').on('click', addLine);
  $(document).on('click', '.btn-line-del', function(){
    $(this).closest('tr').remove();
    if ($('#prLinesTable tbody tr').length === 0) addLine();
  });

  // Chain change
  $('#prChainId').on('change', function(){
    loadStepsForChain(($(this).val()||'').trim());
  });

  // Select2 warning (optional)
  if (!$.fn.select2) {
    $('#prAlert').html(bsAlert('warning','Select2 not loaded. Please include Select2 JS/CSS (optional).'));
  }

  // init
  addLine();
  loadList();
  loadChains();

  // Submit
  $('#btnPrSubmit').on('click', submit);

})(jQuery);
</script>
