<?php
// vehicle-approval-panel.php
session_start();
require_once 'connections/connection.php';
$logged_hris = $_SESSION['hris'] ?? '';
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Pending Vehicle Approvals</h5>

      <div class="mb-3">
        <input type="text" id="searchInput" class="form-control" placeholder="Search by vehicle number, make/model, user...">
      </div>

      <!-- Alerts -->
      <div id="alertContainer"></div>

      <div id="approvalTableArea"></div>
    </div>
  </div>
</div>

<!-- View & Approve Modal -->
<div class="modal fade" id="vehicleModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="vehicleApprovalForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Review Vehicle Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="vehicleModalBody"></div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Approve & Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.vehicle-row:hover {
  background-color: #f1f9ff;
  cursor: pointer;
}
.not-allowed {
  background-color: #f8f9fa;
  opacity: 0.6;
  cursor: not-allowed;
}
</style>

<script>
(() => {
  const loggedHris = "<?php echo $logged_hris; ?>";

  function showAlert(type, message) {
    $('#alertContainer').html(`
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `);
    setTimeout(() => { $('.alert').alert('close'); }, 6000);
  }

  function loadPendingVehicles(query = '') {
    $.ajax({
      url: 'ajax-get-pending-vehicles.php',
      method: 'GET',
      data: { search: query },
      success: function (data) {
        $('#approvalTableArea').html(data);
      },
      error: function () {
        $('#approvalTableArea').html('<div class="alert alert-danger">Failed to load vehicle data.</div>');
      }
    });
  }

  function openVehicleModal(id) {
    $.ajax({
      url: 'ajax-get-vehicle-details.php',
      method: 'GET',
      data: { id: id },
      success: function (html) {
        $('#vehicleModalBody').html(html);
        $('#vehicleModal').modal('show');
      },
      error: function () {
        showAlert('danger', 'Failed to load vehicle details.');
      }
    });
  }

  $(document).ready(function () {
    loadPendingVehicles();

    $('#searchInput').on('keyup', function () {
      const query = $(this).val().trim();
      loadPendingVehicles(query);
    });
  });

  // Clickable rows
  $(document).on('click', '.vehicle-row', function () {
    const id = $(this).data('id');
    const createdBy = $(this).data('created');

    if (createdBy && createdBy.toString().trim() === loggedHris.toString().trim()) {
      showAlert('warning', 'You cannot approve a record you created.');
      return;
    }

    openVehicleModal(id);
  });

  // Approve form submission
  $(document).on('submit', '#vehicleApprovalForm', function (e) {
    e.preventDefault();

    $.ajax({
      url: 'ajax-approve-update-vehicle.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          $('#vehicleModal').modal('hide');
          showAlert('success', `<strong>Approved:</strong> SR <b>${res.sr_number}</b> (${res.vehicle_number}) approved successfully.`);
          loadPendingVehicles($('#searchInput').val());
        } else {
          showAlert('danger', `<strong>Error:</strong> ${res.message}`);
        }
      },
      error: function () {
        showAlert('danger', '<strong>Error:</strong> Failed to approve and save.');
      }
    });
  });
})();
</script>
