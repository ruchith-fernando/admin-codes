<?php
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

      <div id="alertContainer"></div>
      <div id="approvalTableArea"></div>
    </div>
  </div>
</div>


<!-- MODAL -->
<div class="modal fade" id="vehicleModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="vehicleApprovalForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Review Vehicle Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
  background-color: #eee;
  cursor: not-allowed;
}
</style>

<script>
$(document).ready(function () {

  const loggedHris = "<?= $logged_hris ?>";

  function showAlert(type, message) {
    $('#alertContainer').html(`
      <div class="alert alert-${type} alert-dismissible fade show">
        ${message}
        <button class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `);
    setTimeout(() => $('.alert').alert('close'), 6000);
  }

  function loadPendingVehicles(q = '') {
    $.get('ajax-get-pending-vehicles.php', { search: q }, function(data) {
      $('#approvalTableArea').html(data);
    });
  }

  loadPendingVehicles();

  $('#searchInput').keyup(function() {
    loadPendingVehicles($(this).val().trim());
  });

  $(document).on('click', '.vehicle-row', function () {
    const id = $(this).data('id');
    const creator = $(this).data('created').toString().trim();

    if (creator === loggedHris) {
      showAlert('warning', 'You cannot approve a record you created.');
      return;
    }

    $.get('ajax-get-vehicle-details.php', { id: id }, function(html) {
      $('#vehicleModalBody').html(html);
      $('#vehicleModal').modal('show');
    });
  });

  $(document).on('submit', '#vehicleApprovalForm', function(e) {
    e.preventDefault();

    $.post('ajax-approve-update-vehicle.php', $(this).serialize(), function(res) {

      if (res.status === 'success') {
        $('#vehicleModal').modal('hide');
        showAlert('success', `<b>${res.vehicle_number}</b> (SR ${res.sr_number}) approved successfully.`);
        loadPendingVehicles($('#searchInput').val());
      } else {
        showAlert('danger', res.message);
      }

    }, 'json');
  });

});
</script>
