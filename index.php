<?php define('SKIP_SESSION_CHECK', true); 
// <!-- index.php -->
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Secure Login | Admin Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
    .login-container { margin-top: 80px; }
    .card { border: none; border-radius: 1rem; }
    .login-header { font-size: 1.5rem; font-weight: 600; color: #1d3557; }
    .branding { text-align: center; margin-bottom: 25px; }
    .btn-primary { background-color: #1d3557; border: none; }
    .btn-primary:hover { background-color: #16324f; }
    .footer-text { font-size: 0.875rem; color: #888; margin-top: 30px; text-align: center; }
  </style>
</head>
<body>

<div class="container login-container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow p-4">
        <div class="branding">
          <div class="login-header">Admin Portal Login</div>
          <small class="text-muted">Secure Access for Authorized Personnel</small>
        </div>

        <form id="loginForm">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? '') ?>">

          <div class="mb-3">
            <label class="form-label">HRIS Number</label>
            <input type="text" name="hris" id="hris" class="form-control" placeholder="Enter 8-digit HRIS" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
              <button type="button" class="btn btn-outline-secondary" onmousedown="showPassword()" onmouseup="hidePassword()" onmouseleave="hidePassword()">👁️</button>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary">Login</button>
          </div>
        </form>

        <div class="footer-text mt-4">
          &copy; <?= date('Y') ?> CDB Adminstration. All rights reserved
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="errorModalLabel">Login Error</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="errorModalBody"></div>
    </div>
  </div>
</div>
<!-- testing branches -->
 <!-- Updated index.php in testing-new-branch -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function showPassword() {
  document.getElementById('password').type = 'text';
}
function hidePassword() {
  document.getElementById('password').type = 'password';
}

$('#loginForm').on('submit', function (e) {
  e.preventDefault();
  const hris = $('#hris').val().trim();
  const password = $('#password').val().trim();

  if (hris === '' || password === '') {
    showModal("Please fill in both HRIS and Password.");
    return;
  }
  if (!/^\d{8}$/.test(hris)) {
    showModal("HRIS must be exactly 8 digits.");
    return;
  }

  $.post('ajax-login-user.php', $(this).serialize(), function (response) {
    if (response.status === 'success') {
      window.location.href = response.redirect;
    } else {
      showModal(response.message);
    }
  }, 'json').fail((xhr, status, error) => {
    showModal("Unexpected error occurred. Please try again.");
    console.error("AJAX Error:", status, error);
  });
});

function showModal(message) {
  $('#errorModalBody').text(message);
  const modal = new bootstrap.Modal(document.getElementById('errorModal'));
  modal.show();
}
</script>
</body>
</html>
