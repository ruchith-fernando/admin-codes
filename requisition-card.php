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

// Budgets list (NOTE: your tbl_admin_budgets has no branch column; showing active budgets.
// If later you add mapping to branch, we can filter by $branch_code here.)
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

        <div class="col-md-4">
          <label class="form-label fw-bold">Priority</label>
          <select id="prPriority" class="form-select">
            <option value="NORMAL">Normal</option>
            <option value="URGENT">Urgent</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Required Date</label>
          <input type="date" id="prRequiredDate" class="form-control" value="">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Attachments (PDF / Images)</label>
          <input type="file" id="prFiles" class="form-control" multiple
                 accept=".pdf,image/*">
          <div class="form-text">You can upload multiple files.</div>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Overall Justification (Optional)</label>
          <textarea id="prOverallJustification" class="form-control" rows="2" placeholder="Reason for requesting..."></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Recommended Vendor (Optional)</label>
            <input type="text" id="prVendorName" class="form-control" placeholder="Vendor name">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Vendor Contact (Optional)</label>
            <input type="text" id="prVendorContact" class="form-control" placeholder="Phone / Email / Person">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Vendor Note (Optional)</label>
            <input type="text" id="prVendorNote" class="form-control" placeholder="e.g. Best price / Fast delivery">
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

  function uomOptions(){
    let html = `<option value="">-- Select --</option>`;
    UOMS.forEach(u => {
      html += `<option value="${String(u.uom).replace(/"/g,'&quot;')}">${String(u.uom_name)} (${String(u.uom)})</option>`;
    });
    return html;
  }

  function budOptions(){
    let html = `<option value="">-- Select --</option>`;
    BUDS.forEach(b => {
      html += `<option value="${b.id}">${String(b.budget_name)} (${String(b.budget_code)})</option>`;
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
    // Select2 inside rows (optional)
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

  function submit(){
    const department_id = ($('#prDepartmentId').val()||'').trim();
    if (!department_id || parseInt(department_id,10) <= 0) {
      $('#prResult').html(bsAlert('danger','Your department is not mapped in tbl_admin_departments. Please add it first.'));
      return;
    }

    const priority = ($('#prPriority').val()||'NORMAL').trim();
    const required_date = ($('#prRequiredDate').val()||'').trim();
    const overall_justification = ($('#prOverallJustification').val()||'').trim();
    const lines = collectLines();

    const vendor_name = ($('#prVendorName').val()||'').trim();
    const vendor_contact = ($('#prVendorContact').val()||'').trim();
    const vendor_note = ($('#prVendorNote').val()||'').trim();



    if (lines.length === 0) {
      $('#prResult').html(bsAlert('danger','Add at least 1 line item (Item Name).'));
      return;
    }

    // Build multipart form-data (for file upload)
    const fd = new FormData();
    fd.append('action', 'SUBMIT');
    fd.append('priority', priority);
    fd.append('required_date', required_date);
    fd.append('overall_justification', overall_justification);
    fd.append('lines_json', JSON.stringify(lines));
    fd.append('recommended_vendor_name', vendor_name);
    fd.append('recommended_vendor_contact', vendor_contact);
    fd.append('recommended_vendor_note', vendor_note);
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
      $('#prPriority').val('NORMAL');
      $('#prRequiredDate').val('');
      $('#prOverallJustification').val('');
      $('#prFiles').val('');
      $('#prLinesTable tbody').empty();
      $('#prVendorName').val('');
        $('#prVendorContact').val('');
        $('#prVendorNote').val('');
      addLine();

      loadList();
    }).fail(function(xhr){
      $('#prResult').html(bsAlert('danger','Server error: '+xhr.status));
    });
  }

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

  // Select2
  if (!$.fn.select2) {
    $('#prAlert').html(bsAlert('warning','Select2 not loaded. Please include Select2 JS/CSS.'));
  }

  // init
  addLine();
  loadList();

  // Submit
  $('#btnPrSubmit').on('click', submit);

})(jQuery);
</script>
