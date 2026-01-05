$(document).ready(function () {

  // -------------------------------
  // Load Pending Orders Table
  // -------------------------------
  function loadPendingOrders() {
    $('#pendingOrdersTable').html('<p class="text-muted">Loading pending orders...</p>');
    $.get('ajax-load-pending-orders.php', function (data) {
      $('#pendingOrdersTable').html(data);
    }).fail(function () {
      $('#pendingOrdersTable').html('<div class="text-danger">Failed to load orders.</div>');
    });
  }

  // -------------------------------
  // Open Approval Modal
  // -------------------------------
  $(document).on('click', '.open-approval-modal', function () {
    const orderNumber = $(this).data('order');
    $('#approvalModalTitle').text('Approve Order - ' + orderNumber);
    hideApprovalModalAlert();
    $('#approvalModalBody').html('<p class="text-muted">Loading items...</p>');
    $('#approvalModal').modal('show');

    $.get('ajax-load-approval-items.php', { order_number: orderNumber }, function (data) {
      $('#approvalModalBody').html(data);
    }).fail(function () {
      $('#approvalModalBody').html('<div class="text-danger">Failed to load items.</div>');
    });
  });

  // -------------------------------
  // Approve Button Click
  // -------------------------------
  $(document).on('click', '#approveBtn', function () {
    submitApproval('approved');
  });

  // -------------------------------
  // Reject Button Click
  // -------------------------------
  $(document).on('click', '#rejectBtn', function () {
    const remarks = $('textarea[name="remarks"]').val().trim();
    if (remarks === '') {
      showApprovalModalAlert('Please enter remarks for rejection.', 'danger');
      return;
    }
    submitApproval('rejected');
  });

  // -------------------------------
  // Submit Approval or Rejection
  // -------------------------------
  function submitApproval(actionType) {
    const formData = $('#approvalForm').serialize() + '&action=' + actionType;

    $.post('ajax-submit-approval.php', formData, function (response) {
      if (response.status === 'success') {
        showApprovalModalAlert(response.message, 'success');
        setTimeout(() => {
          $('#approvalModal').modal('hide');
          loadPendingOrders();
        }, 1500);
      } else {
        showApprovalModalAlert(response.message, 'danger');
      }
    }, 'json').fail(function () {
      showApprovalModalAlert('Server error. Please try again.', 'danger');
    });
  }

  // -------------------------------
  // Show Alert Inside Modal
  // -------------------------------
  function showApprovalModalAlert(message, type = 'danger') {
    const alertBox = $('#approvalModalAlert');
    alertBox
      .removeClass('d-none alert-success alert-danger alert-warning alert-info')
      .addClass('alert-' + type)
      .text(message);
  }

  // -------------------------------
  // Hide Modal Alert
  // -------------------------------
  function hideApprovalModalAlert() {
    $('#approvalModalAlert')
      .addClass('d-none')
      .removeClass('alert-success alert-danger alert-warning alert-info')
      .text('');
  }

  // -------------------------------
  // Initial Load
  // -------------------------------
  loadPendingOrders();
});
