<?php
  include 'connections/connection.php';
  session_start();

$hris = $_SESSION['hris'] ?? '';
$user_level = $_SESSION['user_level'] ?? '';

$allowed_roles = ['store_keeper', 'head_of_admin', 'super-admin'];

$roles = array_map('trim', explode(',', $user_level));

if (count(array_intersect($roles, $allowed_roles)) === 0) {
    echo '<div class="text-danger p-3">Access denied.</div>';
    exit;
}

?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Pending Stationary Orders</h5>

      <div id="approvalAlert" class="alert d-none"></div>

      <div id="pendingOrdersTable">
        <!-- AJAX loads orders here -->
      </div>
    </div>
  </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="approvalModalTitle">Approve Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- ✅ Alert with margin-top and centered, max-width -->
      <div id="approvalModalAlert" 
           class="alert d-none mx-auto mt-3 text-center px-4 py-2" 
           style="max-width: 600px;">
      </div>

      <div class="modal-body" id="approvalModalBody">
        <!-- Items load here via AJAX -->
      </div>
    </div>
  </div>
</div>


<script src="approval-orders.js"></script>
