<!-- vehicle-approvals.php -->
<?php
  $allowed_roles = ['super-admin', 'admin', 'vehicle_supervisor','issuer'];
  require_once 'includes/check-permission.php';
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Pending Vehicle Records for Approval</h5>

      <!-- Main Tabs -->
      <ul class="nav nav-tabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#maintenance">Maintenance</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#service">Service</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#license">License/Insurance</button></li>
      </ul>

      <div class="tab-content mt-3 border bg-white p-3">

        <!-- Maintenance -->
        <div class="tab-pane fade show active" id="maintenance">
          <ul class="nav nav-pills mb-3">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#maintenancePending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#maintenanceRejected">Rejected</button></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="maintenancePending"></div>
            <div class="tab-pane fade" id="maintenanceRejected"></div>
          </div>
        </div>

        <!-- Service -->
        <div class="tab-pane fade" id="service">
          <ul class="nav nav-pills mb-3">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#servicePending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#serviceRejected">Rejected</button></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="servicePending"></div>
            <div class="tab-pane fade" id="serviceRejected"></div>
          </div>
        </div>

        <!-- License/Insurance -->
        <div class="tab-pane fade" id="license">
          <ul class="nav nav-pills mb-3">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#licensePending">Pending</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#licenseRejected">Rejected</button></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="licensePending"></div>
            <div class="tab-pane fade" id="licenseRejected"></div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approval-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">View & Approve - SR Number: <span id="sr-number"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="approval-details"></div>
      <div class="modal-footer d-block">
        <div class="d-flex gap-2 justify-content-end" id="action-buttons">
          <button type="button" class="btn btn-success" id="approve-btn">Approve</button>
          <button type="button" class="btn btn-danger" id="reject-btn">Reject</button>
        </div>
        <div id="rejection-section" class="w-100 mt-3" style="display:none;">
          <select id="rejection-reason" class="form-select mb-2">
            <option value="" disabled selected>Select Rejection Reason</option>
            <option value="Incorrect Information">Incorrect Information</option>
            <option value="Duplicate Entry">Duplicate Entry</option>
            <option value="Other">Other</option>
          </select>
          <textarea id="other-reason" class="form-control mb-2" style="display:none;" placeholder="Enter other reason"></textarea>
          <div class="d-grid">
            <button class="btn btn-danger" id="confirm-reject-btn">Confirm Reject</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="success-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header">
        <h5 class="modal-title text-success">Success</h5>
      </div>
      <div class="modal-body" id="success-message"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="error-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Error</h5>
      </div>
      <div class="modal-body" id="error-message"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger w-100" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5></div>
      <div class="modal-body">
        <p>Are you sure you want to delete this record? This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="vehicle-approval.js"></script>
