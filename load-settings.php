<?php
// load-settings.php
// Assuming $name is the logged-in user's name from session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown User';
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="text-warning mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h5>
      </div>

      <!-- Settings Section -->
      <h6 class="text-primary mb-3 mt-2"><i class="fas fa-tools me-1"></i> Settings</h6>
      <div class="row g-3 mb-4">
        
        <!-- User Access -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-shield text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">User Access</h6>
              </div>
              <p class="small text-muted">Manage access permissions for different users.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="user-access-management.php">
                <i class="fas fa-cog me-1"></i> Open
              </button>
            </div>
          </div>
        </div>

        <!-- Add Menu Key -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-key text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Add Menu Key</h6>
              </div>
              <p class="small text-muted">Add or manage menu keys for system navigation.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="add-menu-key.php">
                <i class="fas fa-plus me-1"></i> Open
              </button>
            </div>
          </div>
        </div>

        <!-- Register User -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-plus text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Register User</h6>
              </div>
              <p class="small text-muted">Create a new user account in the system.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="register.php">
                <i class="fas fa-user-plus me-1"></i> Open
              </button>
            </div>
          </div>
        </div>

        <!-- Edit User -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-edit text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Edit User</h6>
              </div>
              <p class="small text-muted">Modify user details and update access.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="edit-user.php">
                <i class="fas fa-edit me-1"></i> Open
              </button>
            </div>
          </div>
        </div>

        <!-- Backup -->
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-database text-primary me-2 fa-lg"></i>
                <h6 class="card-title text-dark mb-0">Backup</h6>
              </div>
              <p class="small text-muted">Create and download a backup of system data.</p>
              <button class="btn btn-outline-primary btn-sm mt-auto load-report" data-page="full-backup.php">
                <i class="fas fa-download me-1"></i> Open
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
$(document).on('click', '.load-report', function () {
  var page = $(this).data('page');
  $('#contentArea').load(page);
});
</script>
