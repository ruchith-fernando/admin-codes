// vehicle-approval-maintenance.js

$(document).ready(function () {
  loadPendingMaintenance();

  // Load rejected maintenance when tab is shown
  $('button[data-bs-target="#maintenance-rejected"]').on('shown.bs.tab', function () {
    $('#maintenanceRejected').load('ajax-get-rejected-maintenance.php');
  });

  // Open approval modal
  $(document).on('click', '.btn-verify', function () {
    const id = $(this).data('id');
    $('#approvalModal').data('entry-type', 'maintenance');
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'), {
      backdrop: 'static',
      keyboard: false
    });
    modal.show();

    $('#approvalModalBody').html("<div class='text-center text-muted p-4'>Loading details...</div>");
    $('#approvalModalBody').load('ajax-verify-vehicle-entry.php', { id, type: 'maintenance' }, function (response, status, xhr) {
      if (status === "error") {
        $('#approvalModalBody').html(`<div class="alert alert-danger">Failed to load details: ${xhr.statusText}</div>`);
      }
    });
  });

  // Reject Maintenance
  $(document).on('click', '#rejectMaintenanceBtn', function () {
    openRejectModal('maintenance');
  });

  $(document).on('change', '#reject_reason', function () {
    if ($(this).val() === 'Other') {
      $('#note_section').slideDown();
      $('#reject_note').attr('required', true);
    } else {
      $('#note_section').slideUp();
      $('#reject_note').val('').removeAttr('required');
    }
  });

  $(document).on('submit', '#rejectReasonForm', function (e) {
    e.preventDefault();
    rejectMaintenance();
  });
});

// Load Pending Maintenance
function loadPendingMaintenance() {
  $('#maintenancePending').load('ajax-pending-maintenance.php');
}

// Reject Maintenance Logic
function rejectMaintenance() {
  const id = $('#reject_id').val();
  const reason = buildFullReason();

  $.post('ajax-reject-maintenance.php', { id, reason }, function (res) {
    if (isSuccess(res)) {
      $('#rejectReasonModal, #approvalModal').modal('hide');
      showSuccessModal('Maintenance entry rejected successfully.');
      loadPendingMaintenance();
      $('#maintenanceRejected').load('ajax-get-rejected-maintenance.php');
    } else {
      showAlert('danger', 'Error: ' + (res.message || res));
    }
  }, 'json').fail(xhr => showAlert('danger', 'Server error: ' + xhr.statusText));
}

// Helpers
function openRejectModal(type, id = null) {
  $('#reject_id').val(id || $('#entry_id').val());
  $('#reject_reason').val('');
  $('#reject_note').val('');
  $('#note_section').hide();
  $('#rejectReasonModal').data('entry-type', type).modal('show');
}

function buildFullReason() {
  const reason = $('#reject_reason').val();
  const note = $('#reject_note').val();
  return note ? `${reason} - ${note}` : reason;
}

function isSuccess(res) {
  return (typeof res === 'string' && res.trim() === 'success') ||
         (typeof res === 'object' && res.status === 'success');
}

function showSuccessModal(message) {
  $('#successModalBody').html(`<p>${message}</p>`);
  const modal = new bootstrap.Modal(document.getElementById('successModal'));
  modal.show();
  modal._element.addEventListener('hidden.bs.modal', function () {
    location.reload();
  });
}

function showAlert(type, message) {
  const html = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  $('#alertArea').html(html);
}
