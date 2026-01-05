<?php include 'connections/connection.php'; ?>
<?php
  $allowed_roles = ['super-admin', 'admin'];
  // require_once 'includes/check-permission.php';
?>
<style>
.select2-container--default .select2-selection--single,
.select2-container--default .select2-selection--multiple {
    height: auto !important;
    padding: 6px 12px;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}
.select2-container--default .select2-selection--single .select2-selection__rendered,
.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    line-height: 24px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
    top: 1px;
    right: 6px;
}
.card-title {
    font-weight: 600;
    font-size: 1.6rem;
}
.form-label {
    font-weight: 500;
}
.btn-primary {
    font-weight: 600;
    padding: 10px;
}
</style>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register New User</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .show-password-btn {
      position: absolute;
      right: 15px;
      top: 38px;
      background: none;
      border: none;
      cursor: pointer;
    }
    .position-relative { position: relative; }
  </style>
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card shadow-lg">
        <div class="card-body p-5">
          <h3 class="card-title mb-4 text-center">Create New User</h3>
          <form id="registerForm">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" id="name" class="form-control" placeholder="Enter full name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">HRIS (8-digit)</label>
              <input type="text" name="hris" id="hris" class="form-control" placeholder="Enter 8-digit HRIS" required>
            </div>
            <div class="mb-3 position-relative">
              <label class="form-label">Password</label>
              <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter password" required>
              <button type="button" class="show-password-btn" onmousedown="showPassword()" onmouseup="hidePassword()" onmouseleave="hidePassword()">👁️</button>
            </div>
            <div class="mb-3">
              <label class="form-label">User Level(s)</label>
              <select name="user_level[]" id="user_level" class="form-select" multiple required>
                <?php
                $levels = $conn->query("SELECT level_key, level_label FROM tbl_admin_user_levels ORDER BY level_label ASC");
                while ($row = $levels->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($row['level_key']) . '">' . htmlspecialchars($row['level_label']) . '</option>';
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category" id="category" class="form-select" required>
                <option value="">Select</option>
                <option value="Marketing">Marketing</option>
                <option value="Branch Operation">Branch Operation</option>
                <option value="Operations">Operations</option>
                <option value="All">All (Admin, Acceptor, Issuer)</option>
              </select>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Register User</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="responseModalLabel">Registration Message</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
  $(document).ready(function () {
    $('#user_level').select2({ placeholder: "Select User Level(s)", width: '100%' });

    $('#registerForm').on('submit', function (e) {
      e.preventDefault();

      const hris = $('#hris').val().trim();
      if (!/^\d{8}$/.test(hris)) {
        showModal("HRIS must be exactly 8 digits.");
        return;
      }

      const formData = new FormData(this);

      $.ajax({
        url: 'ajax-register-user.php',
        method: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
          showModal(response);
          $('#registerForm')[0].reset();
          $('#user_level').val('').trigger('change');
        },
        error: function () {
          showModal("\u274C No response received.");
        }
      });
    });
  });

  function showPassword() {
    document.getElementById('passwordField').type = 'text';
  }

  function hidePassword() {
    document.getElementById('passwordField').type = 'password';
  }

  function showModal(message) {
    $('#modalBody').html(message);
    var modal = new bootstrap.Modal(document.getElementById('responseModal'));
    modal.show();
  }
</script>
</body>
</html>
