<?php

// ajax-full-backup.php
?>
<p>This will create a downloadable ZIP based on your selection:</p>

<form id="backupForm">
  <div class="row mb-3">
    <div class="col-md-4">
      <label for="backup_type" class="form-label">Select Backup Type:</label>
      <select name="backup_type" id="backup_type" class="form-select" required>
        <option value="">-- Choose Option --</option>
        <option value="files">Backup Files & Folders</option>
        <option value="db">Backup Database Only</option>
        <option value="full">Full Backup (Files + DB)</option>
        <option value="root">Backup Only Root Files</option>
      </select>
    </div>
  </div>
  <button type="submit" class="btn btn-primary">Start Backup</button>
</form>

<div id="backupResult" class="mt-4"></div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Backup Created</h5>
      </div>
      <div class="modal-body text-center">
        Your backup has been created successfully.<br><br>
        <a id="downloadLink" class="btn btn-success" download>Download Backup</a>
      </div>
    </div>
  </div>
</div>

<!-- Loader -->
<div id="globalLoader" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:1055;background:rgba(255,255,255,0.85);">
  <div class="d-flex justify-content-center align-items-center h-100">
    <div>
      <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
      <div class="text-primary fw-bold">Processing, please wait...</div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $("#backupForm").on("submit", function (e) {
    e.preventDefault();

    const backupType = $("#backup_type").val();
    if (!backupType) {
      alert("Please select a backup type.");
      return;
    }

    const formData = new FormData();
    formData.append("backup_type", backupType);

    showGlobalLoader();

    $.ajax({
      url: "ajax-process-backup.php",
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (res) {
        hideGlobalLoader();

        try {
          const data = typeof res === "string" ? JSON.parse(res) : res;

          if (data.status === "success") {
            $("#downloadLink").attr("href", data.filename);
            new bootstrap.Modal(document.getElementById("successModal")).show();
            $("#backupResult").html("");
          } else {
            $("#backupResult").html(`<div class="alert alert-danger">${data.message}</div>`);
          }
        } catch (e) {
          console.error("JSON Parse Error:", res);
          $("#backupResult").html('<div class="alert alert-danger">Unexpected error. Try again.</div>');
        }
      },
      error: function (xhr) {
        hideGlobalLoader();
        console.error("AJAX Error:", xhr.responseText);
        $("#backupResult").html('<div class="alert alert-danger">Backup failed. Please try again later.</div>');
      }
    });
  });

  $(document).on("click", "#downloadLink", function () {
    showGlobalLoader();
    setTimeout(() => hideGlobalLoader(), 3000);
  });
});

function showGlobalLoader() {
  $("#globalLoader").fadeIn(200);
}

function hideGlobalLoader() {
  $("#globalLoader").fadeOut(200);
}
</script>
