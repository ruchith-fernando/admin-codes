<?php
include 'connections/connection.php';
session_start();

$hris = $_SESSION['hris'] ?? '';

$user_level = $_SESSION['user_level'] ?? '';

$allowed_roles = ['stationary_request', 'head_of_admin','boic'];

$roles = array_map('trim', explode(',', $user_level));

if (count(array_intersect($roles, $allowed_roles)) === 0) {
    echo '<div class="text-danger p-3">Access denied.</div>';
    exit;
}

?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="text-primary mb-3">BOIC Stationary Approvals</h5>

      <div id="boicAlert" class="alert d-none"></div>

      <div id="boicRequestsTable">
        <p class="text-muted">Loading requests...</p>
      </div>
    </div>
  </div>
</div>

<!-- BOIC Modal -->
<div class="modal fade" id="boicModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title">Edit & Approve Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- ✅ Place the alert here -->
      <div id="boicModalAlert" class="alert d-none m-3"></div>

      <div class="modal-body" id="boicModalBody">
        <!-- Request items will be loaded here via AJAX -->
      </div>

    </div>
  </div>
</div>


<script>
$(document).ready(function () {
  // ---------------------------
  // Page-Level Bootstrap Alert
  // ---------------------------
  function showAlert(type, message) {
    $('#boicAlert').removeClass('d-none alert-success alert-danger')
                   .addClass('alert-' + type)
                   .text(message);
    setTimeout(() => $('#boicAlert').addClass('d-none'), 4000);
  }

  // ---------------------------
  // Modal-Level Bootstrap Alert
  // ---------------------------
  function showModalAlert(type, message) {
    const alertBox = $('#boicModalAlert');
    alertBox.removeClass('d-none alert-success alert-danger')
            .addClass('alert-' + type)
            .text(message);
    setTimeout(() => alertBox.addClass('d-none'), 4000);
  }

  // ---------------------------
  // Load BOIC Requests Table
  // ---------------------------
  function loadRequests() {
    $.get('fetch-boic-requests.php', function (html) {
      $('#boicRequestsTable').html(html);
    }).fail(() => {
      $('#boicRequestsTable').html('<div class="text-danger">Failed to load requests.</div>');
    });
  }

  // ---------------------------
  // View/Edit Items in Modal
  // ---------------------------
  $(document).on('click', '.edit-boic-btn', function () {
    const order = $(this).data('order');
    $('#boicModal').data('order-number', order).modal('show');
    $('#boicModalBody').html('<p class="text-muted">Loading...</p>');
    $.get('fetch-order-items.php', { order_number: order }, function (html) {
      $('#boicModalBody').html(html);
    }).fail(() => {
      $('#boicModalBody').html('<div class="text-danger">Failed to load items.</div>');
    });
  });

  // ---------------------------
  // Approve Entire Request
  // ---------------------------
  $(document).on('click', '.approve-boic-btn', function () {
    const order = $(this).data('order');
    $.post('approve-boic-request.php', { order_number: order }, function (res) {
      if (res.status === 'success') {
        showAlert('success', res.message);
        $('#boicModal').modal('hide');
        loadRequests();
      } else {
        showAlert('danger', res.message);
      }
    }, 'json').fail(() => {
      showAlert('danger', 'Server error while approving.');
    });
  });

  // ---------------------------
  // Delete Request (outside modal)
  // ---------------------------
  $(document).on('click', '.delete-boic-btn', function () {
    const order = $(this).data('order');
    if (!confirm('Are you sure you want to delete this request?')) return;

    $.post('delete-boic-request.php', { order_number: order }, function (res) {
      if (res.status === 'success') {
        showAlert('success', res.message);
        loadRequests();
      } else {
        showAlert('danger', res.message);
      }
    }, 'json').fail(() => {
      showAlert('danger', 'Server error while deleting.');
    });
  });

  // ---------------------------
  // Update Quantity in Modal
  // ---------------------------
  $(document).on('click', '.update-qty-btn', function () {
    const id = $(this).data('id');
    const qty = $('input.update-qty-input[data-id="' + id + '"]').val();

    if (!qty || isNaN(qty) || qty <= 0) {
      showModalAlert('danger', 'Enter a valid quantity.');
      return;
    }

    $.post('update-stock-request.php', { id, quantity: qty }, function (res) {
      if (res.status === 'success') {
        showModalAlert('success', res.message);

        // Reload modal content
        const orderNumber = $('#boicModal').data('order-number');
        if (orderNumber) {
          $('#boicModalBody').load('load-request-items.php?order_number=' + orderNumber);
        }
      } else {
        showModalAlert('danger', res.message);
      }
    }, 'json').fail(function () {
      showModalAlert('danger', 'Server error while updating quantity.');
    });
  });

  // ---------------------------
  // Delete Item in Modal
  // ---------------------------
  $(document).on('click', '.delete-item-btn', function () {
    const id = $(this).data('id');
    if (!confirm('Are you sure you want to delete this item?')) return;

    $.post('delete-stock-request.php', { id: id }, function (res) {
      if (res.status === 'success') {
        showModalAlert('success', res.message);

        // Reload modal content
        const orderNumber = $('#boicModal').data('order-number');
        if (orderNumber) {
          $('#boicModalBody').load('load-request-items.php?order_number=' + orderNumber);
        }
      } else {
        showModalAlert('danger', res.message);
      }
    }, 'json').fail(function () {
      showModalAlert('danger', 'Server error while deleting item.');
    });
  });

  // ---------------------------
  // Initial Load of Requests
  // ---------------------------
  loadRequests();
});
</script>

