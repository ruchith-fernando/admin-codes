<!-- vehicle-approvals-maintenance.php -->
<?php
session_start();
require_once 'connections/connection.php';

if (!isset($_SESSION['hris'])) {
    echo "<div class='alert alert-danger'>Access denied. Please login.</div>";
    exit;
}
?>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Pending Maintenance Records for Approval</h5>
      
      <!-- Tabs -->
      <ul class="nav nav-pills mb-3">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#maintenance-pending">Pending</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#maintenance-rejected">Rejected</button>
        </li>
      </ul>
      
      <div class="tab-content">
        <div class="tab-pane fade show active" id="maintenance-pending">
          <div id="maintenancePending"></div>
        </div>
        <div class="tab-pane fade" id="maintenance-rejected">
          <div id="maintenanceRejected" class="p-3 text-muted text-center">Loading rejected maintenance records...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'modals/maintenance-modals.php'; ?>
<script src="js/vehicle-approval-maintenance.js?v=1"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
