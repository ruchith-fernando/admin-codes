<!-- Success Modal -->
<div class="modal" id="successModal" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false"
     aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="successModalBody">
        <!-- Dynamic content -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal" id="rejectReasonModal" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false"
     aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="rejectReasonForm">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Rejection Reason</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="reject_id" name="reject_id">
          <div class="mb-3">
            <label for="reject_reason">Reason</label>
            <select id="reject_reason" name="reject_reason" class="form-select" required>
              <option value="">Select</option>
              <option value="Incorrect Details">Incorrect Details</option>
              <option value="Not Eligible">Not Eligible</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="mb-3" id="note_section" style="display:none;">
            <label for="reject_note">Additional Notes</label>
            <textarea id="reject_note" name="reject_note" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteConfirmModal" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false"
     aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmLabel">Confirm Deletion</h5>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this rejected maintenance record?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteBtn" type="button" class="btn btn-danger">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- MUST be present in your HTML (preferably near bottom of main.php) -->
<div class="modal" id="deleteServiceConfirmModal" tabindex="-1" aria-labelledby="deleteServiceLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteServiceLabel">Confirm Delete</h5>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this rejected service record?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteServiceBtn">Delete</button>
      </div>
    </div>
  </div>
</div>
<!-- Approval Modal (used for Maintenance, Service, License) -->
<div class="modal" id="approvalModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-3 shadow">
      <div class="modal-header border-0">
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="approvalModalBody">
        <!-- Dynamic fragment loads here -->
      </div>
    </div>
  </div>
</div>

<!-- Shared Success Modal -->
<div class="modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="successMessageText"></p>
        <div id="srNumberContainer" class="mt-3 alert alert-light border d-flex justify-content-between align-items-center d-none">
          <div><strong>Help ID: <span id="srNumberText" class="text-primary"></span></strong></div>
          <button class="btn btn-outline-success btn-sm" onclick="copyHelpId()">Copy</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast for clipboard copy -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="copyToast" class="toast fade align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        Help ID copied to clipboard.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- shared/modal-employee-view.php -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="employeeModalLabel">Employee Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="employeeModalBody">
        <!-- Employee details go here -->
      </div>
    </div>
  </div>
</div>
