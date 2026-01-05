$(document).ready(function () {
  // ------------------------------
  // Show Alert Messages
  // ------------------------------
  function showAlert(type, message) {
    const alert = $('#requestAlert');
    alert.removeClass('d-none alert-success alert-danger')
      .addClass('alert-' + type)
      .text(message);
    setTimeout(() => alert.addClass('d-none'), 5000);
  }

  // ------------------------------
  // Load Editable Requests (Today’s Courier + This Month’s Stationery)
  // ------------------------------
  function loadRequests() {
    $('#submittedRequestsTable').html('<p class="text-muted">Loading your submitted requests...</p>');
    $.get('fetch-requests.php', function (html) {
      $('#submittedRequestsTable').html(html);
    }).fail(function () {
      $('#submittedRequestsTable').html('<div class="text-danger">Failed to load requests.</div>');
    });
  }

  // ------------------------------
  // Load All Other Requests (Read-only)
  // ------------------------------
  function loadReadonlyRequests() {
    console.log('Loading past requests via AJAX...');  // Debug 1
    $.get('fetch-readonly-requests.php', function (html) {
      console.log('AJAX response received for past requests');  // Debug 2
      $('#readonlyRequestsTable').html(html);
    }).fail(function () {
      console.error('AJAX failed loading past requests');  // Debug 3
      $('#readonlyRequestsTable').html('<div class="text-danger">Failed to load past requests.</div>');
    });
  }


  // -------------------------------
  // View Items Inside Modal
  // -------------------------------
  $(document).on('click', '.view-items-btn', function () {
    const orderNumber = $(this).data('order');
    const isReadonly = $(this).hasClass('readonly-view');  // Check if from read-only table

    $('#modalItemTitle').text('Request Items - Order Number ' + orderNumber);
    $('#modalItemBody').html('<p class="text-muted">Loading items...</p>');
    $('#viewItemsModal').data('readonly', isReadonly).data('order-number', orderNumber).modal('show');

    $.get('fetch-order-items.php', { order_number: orderNumber }, function (html) {
      $('#modalItemBody').html(html);

      // Disable editing controls if read-only
      if (isReadonly) {
        $('#modalItemBody').find('input, select, textarea, button.update-qty-btn, button.delete-item-btn').prop('disabled', true);
      }
    }).fail(function () {
      $('#modalItemBody').html('<div class="text-danger">Failed to load items.</div>');
    });
  });

  // ---------------------------------
  // Update Quantity in Modal View (Editable Only)
  // ---------------------------------
  $(document).on('click', '.update-qty-btn', function () {
    const id = $(this).data('id');
    const qty = $('input.update-qty-input[data-id="' + id + '"]').val();

    if (!qty || isNaN(qty) || qty <= 0) {
      $('#modalAlert')
        .removeClass('d-none alert-success')
        .addClass('alert-danger')
        .text('Enter a valid quantity.');
      return;
    }

    $.post('update-stock-request.php', { id, quantity: qty }, function (res) {
      if (res.status === 'success') {
        $('#modalAlert')
          .removeClass('d-none alert-danger alert-success')
          .addClass('alert-success')
          .text(res.message)
          .show();

        setTimeout(() => {
          $('#modalAlert').fadeOut(300, function () {
            $(this).addClass('d-none').removeClass('alert-success').text('').show();
          });
        }, 3000);

        loadRequests();
        loadReadonlyRequests();  // Refresh both tables
      } else {
        $('#modalAlert')
          .removeClass('d-none alert-success alert-danger')
          .addClass('alert-danger')
          .text(res.message || 'Update failed.')
          .show();
      }
    }, 'json').fail(function () {
      $('#modalAlert')
        .removeClass('d-none alert-success alert-danger')
        .addClass('alert-danger')
        .text('Server error while updating quantity.')
        .show();
    });
  });

  // --------------------------
  // Delete Item in Modal (Editable Only)
  // --------------------------
  $(document).on('click', '.delete-item-btn', function () {
    const id = $(this).data('id');
    if (!confirm('Are you sure you want to delete this item?')) return;

    $.post('delete-stock-request.php', { id }, function (res) {
      if (res.status === 'success') {
        $('#modalAlert')
          .removeClass('d-none alert-danger alert-success')
          .addClass('alert-success')
          .text(res.message)
          .show();

        setTimeout(() => {
          $('#modalAlert').fadeOut(300, function () {
            $(this).addClass('d-none').removeClass('alert-success').text('').show();
          });
        }, 3000);

        const orderNumber = $('#viewItemsModal').data('order-number');
        if (orderNumber) {
          $('#modalItemBody').load(`load-request-items.php?order_number=${orderNumber}`);
        }
        loadRequests();
        loadReadonlyRequests();
      } else {
        $('#modalAlert')
          .removeClass('d-none alert-success alert-danger')
          .addClass('alert-danger')
          .text(res.message || 'Delete failed.')
          .show();
      }
    }, 'json').fail(function () {
      $('#modalAlert')
        .removeClass('d-none alert-success alert-danger')
        .addClass('alert-danger')
        .text('Server error while deleting item.')
        .show();
    });
  });

  // -------------------------------
  // Push Request to Storekeeper
  // -------------------------------
  let selectedPushOrder = null;

  $(document).on('click', '.push-btn', function () {
    selectedPushOrder = $(this).data('order');
    $('#pushReason').val('');
    $('#pushCourierModal').modal('show');
  });

  $('#submitPushBtn').on('click', function () {
    const reason = $('#pushReason').val().trim();
    if (reason === '') {
      alert('Please enter a reason for pushing to storekeeper.');
      return;
    }

    $.ajax({
      url: 'push-to-storekeeper.php',
      method: 'POST',
      data: {
        order_number: selectedPushOrder,
        reason: reason
      },
      success: function (response) {
        $('#pushCourierModal').modal('hide');
        alert('Order pushed successfully.');
        loadRequests();
        loadReadonlyRequests();
      },
      error: function () {
        alert('Failed to push the order.');
      }
    });
  });

  // ---------------------------
  // Initial Load for Both Tables
  // ---------------------------
  loadRequests();            // Editable requests
  loadReadonlyRequests();    // Read-only requests
});
