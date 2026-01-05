<?php
// vehicle-approvals-pro.php
?>
<style>
  /* Compact spacing just for this fragment */
  .va-approvals .nav-tabs { margin-bottom: .5rem !important; }
  .va-approvals .nav-pills { margin-bottom: .5rem !important; }
  .va-approvals .tab-content { margin-top: .5rem !important; }
  .va-approvals .tab-content .tab-content { margin-top: .25rem !important; }
  .va-approvals h6 { margin: .25rem 0 !important; }
  #proAlertContainer .alert {
    margin-bottom: 0.5rem;
  }
  .va-approvals table tr.pro-js-view:hover {
    background-color: #f8f9fa;
  }
  .va-approvals table tr.pro-js-view:hover {
    background-color: #f8f9fa;
  }
</style>


<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4 va-approvals">
      <h5 class="mb-4 text-primary">Pending Vehicle Records for Approval</h5>

      <!-- main tabs (added mb-2) -->
      <ul class="nav nav-tabs mb-2">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#proTabMaint">Maintenance</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#proTabServ">Service</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#proTabLic">Licensing &amp; Emission</button>
        </li>
      </ul>

      <!-- reduced mt-3 → mt-2 -->
      <div class="tab-content mt-2 border bg-white p-3">
        <!-- Maintenance -->
        <div class="tab-pane fade show active" id="proTabMaint">
          <!-- inner pills mb-3 → mb-2 -->
          <ul class="nav nav-pills mb-2">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#proMtPending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#proMtRejected">Rejected</button></li>
          </ul>
          <!-- add pt-1 to pull content up -->
          <div class="tab-content pt-1">
            <div class="tab-pane fade show active" id="proMtPending"></div>
            <div class="tab-pane fade" id="proMtRejected"></div>
          </div>
        </div>

        <!-- Service -->
        <div class="tab-pane fade" id="proTabServ">
          <ul class="nav nav-pills mb-2">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#proSvPending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#proSvRejected">Rejected</button></li>
          </ul>
          <div class="tab-content pt-1">
            <div class="tab-pane fade show active" id="proSvPending"></div>
            <div class="tab-pane fade" id="proSvRejected"></div>
          </div>
        </div>

        <!-- Licensing & Emission -->
        <div class="tab-pane fade" id="proTabLic">
          <ul class="nav nav-pills mb-2">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#proLcPending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#proLcRejected">Rejected</button></li>
          </ul>
          <div class="tab-content pt-1">
            <div class="tab-pane fade show active" id="proLcPending"></div>
            <div class="tab-pane fade" id="proLcRejected"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- View & Approve Modal -->
<div class="modal fade" id="proApprModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">View & Approve — SR: <span id="proApprSr"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="proApprBody"></div>
      <div class="modal-footer d-block">
        <div class="d-flex gap-2 justify-content-end" id="proApprActions">
          <button class="btn btn-success" id="proBtnApprove">Approve</button>
          <button class="btn btn-danger" id="proBtnReject">Reject</button>
        </div>
        <div id="proRejectWrap" class="mt-3" style="display:none;">
          <select id="proRejectReason" class="form-select mb-2">
            <option value="" disabled selected>Select Rejection Reason</option>
            <option value="Incorrect Information">Incorrect Information</option>
            <option value="Duplicate Entry">Duplicate Entry</option>
            <option value="Insufficient Evidence/Attachments">Insufficient Evidence/Attachments</option>
            <option value="Not Compliant With Policy">Not Compliant With Policy</option>
            <option value="Other">Other</option>
          </select>
          <textarea id="proRejectOther" class="form-control mb-2" placeholder="Enter other reason" style="display:none;"></textarea>
          <div class="d-grid">
            <button class="btn btn-danger" id="proBtnRejectConfirm">Confirm Reject</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="proAlertContainer" class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1080; width: auto;"></div>

<script>
  window.initPage = function () {
    if (window.ApprovalsPro) {
      ApprovalsPro.initFragment(document); // wire delegated events
      ApprovalsPro.loadAll();              // load tables
    }
  };
</script>
