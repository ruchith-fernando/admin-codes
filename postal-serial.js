// postal-serial.js
$(document).ready(function () {
  // Bootstrap datepicker for date posted
  $('#date_posted').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    endDate: new Date(),
    todayHighlight: true
  });

  // Open modal with data from button
  $('#pendingTableArea').on('click', '[data-bs-target="#serialModal"]', function () {
    const id = $(this).data('id');
    $('#postage_id').val(id);
    $('#postal_serial_number').val('');
    $('#date_posted').datepicker('setDate', new Date());
  });

  // Handle form submission via AJAX
  $('#postalSerialForm').submit(function (e) {
    e.preventDefault();
    $.ajax({
      url: 'ajax-update-serial-date.php',
      method: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $('#serialModal').modal('hide');
          loadPendingTable(); // Refresh pending entries
        } else {
          alert(response.message || 'Failed to update postal serial.');
        }
      },
      error: function () {
        alert('Error communicating with the server.');
      }
    });
  });
});
