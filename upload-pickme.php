<?php
session_start();
// if (!isset($_SESSION['username']) || $_SESSION['user_level'] !== 'super-admin') {
//     header("Location: access-denied.php");
//     exit;
// }
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="text-primary mb-4">Upload PickMe CSV File</h5>

      <form id="pickmeUploadForm" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="csv_file" class="form-label">Choose CSV file</label>
          <input type="file" name="csv_file" id="csv_file" accept=".csv" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
      </form>

      <div id="uploadResult" class="mt-4"></div>
    </div>
  </div>
</div>

<!-- ✅ Bootstrap Success Modal -->
<div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-labelledby="uploadSuccessModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="uploadSuccessModalLabel">Upload Complete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="uploadSuccessMessage">
        <!-- Message will be inserted here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('#pickmeUploadForm').on('submit', function (e) {
    e.preventDefault();

    let formData = new FormData(this);
    $('#uploadResult').html('<div class="alert alert-info">Uploading...</div>');

    $.ajax({
      url: 'ajax-upload-pickme.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function (res) {
        try {
          let response = JSON.parse(res);

          if (response.status === 'success') {
            // Set modal message content
            $('#uploadSuccessMessage').html(
              '<p>' + response.message + '</p>' +
              '<p><strong>Log File:</strong> ' + response.log_file + '</p>'
            );

            // Show modal (disable outside click and ESC)
            var successModal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'), {
              backdrop: 'static',
              keyboard: false
            });
            successModal.show();

            // Reset form and clear result area
            $('#uploadResult').html('');
            document.getElementById('pickmeUploadForm').reset();
          } else {
            $('#uploadResult').html('<div class="alert alert-danger">Unexpected response.</div>');
          }
        } catch (err) {
          $('#uploadResult').html('<div class="alert alert-danger">Error parsing response.</div>');
        }
      },
      error: function () {
        $('#uploadResult').html('<div class="alert alert-danger">Upload failed. Please try again.</div>');
      }
    });
  });
});
</script>

