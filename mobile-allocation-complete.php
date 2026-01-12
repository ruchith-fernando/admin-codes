<?php
// mobile-allocation-complete.php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';
date_default_timezone_set('Asia/Colombo');

$issueId = (int)($_GET['issue_id'] ?? 0);

// dropdown values
$list = $conn->query("
  SELECT id, label
  FROM tbl_admin_contributions
  WHERE is_active=1
  ORDER BY sort_order ASC, id ASC
");
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Mobile Allocation — Approve & Complete</h5>

      <input type="hidden" id="maIssueId" value="<?= (int)$issueId ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Issue ID</label>
          <input type="text" id="maIssueIdView" class="form-control" value="<?= (int)$issueId ?>" readonly>
          <div class="form-text">Open from Pending list. (Issue must be Pending)</div>
        </div>

        <div class="col-md-8 d-flex align-items-end justify-content-end gap-2">
          <button class="btn btn-outline-primary" id="btnLoadIssue" type="button">Load Pending Issue</button>
        </div>
      </div>

      <div id="maLoadedInfo" class="mt-3"></div>

      <hr>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Voice/Data</label>
          <select id="maVoiceData2" class="form-select">
            <option value="">-- Select --</option>
            <option value="Voice">Voice</option>
            <option value="Data">Data</option>
            <option value="Voice+Data">Voice+Data</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Connection Status</label>
          <select id="maConnStatus2" class="form-select">
            <option value="Connected" selected>Connected</option>
            <option value="Disconnected">Disconnected</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Company Contribution</label>
          <select id="maContributionId" class="form-select">
            <option value="">-- Select --</option>
            <?php if ($list): while($r=$list->fetch_assoc()): ?>
              <option value="<?= (int)$r['id'] ?>"><?= esc($r['label']) ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>

        <div class="col-md-3 d-flex align-items-end justify-content-end">
          <button class="btn btn-success" id="btnApprove" type="button">Approve</button>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Remarks</label>
          <textarea id="maRemarks2" class="form-control" rows="2"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Remarks on Branch Operational lines</label>
          <textarea id="maBranchRemarks2" class="form-control" rows="2"></textarea>
        </div>
      </div>

      <div id="maCompleteResult" class="mt-3"></div>
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

  function loadIssue(){
    const issueId = ($('#maIssueId').val()||'').trim();
    if (!issueId || !/^\d+$/.test(issueId)) {
      $('#maLoadedInfo').html(bsAlert('danger','Invalid Issue ID.'));
      return;
    }

    $('#maLoadedInfo').html('<div class="text-muted">Loading...</div>');

    $.post('mobile-allocation-load.php', { issue_id: issueId }, function(html){
      $('#maLoadedInfo').html(html);

      // read meta and auto-fill voice/data
      const meta = $('#maPendingMeta');
      if (meta.length) {
        const v = meta.data('voice');
        if (v) $('#maVoiceData2').val(v);
      }
    }).fail(function(xhr){
      $('#maLoadedInfo').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function approve(){
    const payload = {
      issue_id: ($('#maIssueId').val()||'').trim(),
      voice_data: ($('#maVoiceData2').val()||'').trim(),
      connection_status: ($('#maConnStatus2').val()||'').trim(),
      contribution_id: ($('#maContributionId').val()||'').trim(),
      remarks: ($('#maRemarks2').val()||'').trim(),
      branch_operational_remarks: ($('#maBranchRemarks2').val()||'').trim()
    };

    if (!payload.issue_id || !/^\d+$/.test(payload.issue_id)) {
      $('#maCompleteResult').html(bsAlert('danger','Invalid Issue ID.'));
      return;
    }
    if (!payload.voice_data) { $('#maCompleteResult').html(bsAlert('danger','Voice/Data is required.')); return; }
    if (!payload.contribution_id) { $('#maCompleteResult').html(bsAlert('danger','Company Contribution is required.')); return; }

    $('#maCompleteResult').html('<div class="text-muted">Approving...</div>');
    $.post('mobile-allocation-complete-save.php', payload, function(html){
      $('#maCompleteResult').html(html);
      loadIssue(); // refresh
    }).fail(function(xhr){
      $('#maCompleteResult').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  $('#btnLoadIssue').on('click', loadIssue);
  $('#btnApprove').on('click', approve);

  // auto-load if opened from pending list
  if ($('#maIssueId').val()) loadIssue();

})(jQuery);
</script>
