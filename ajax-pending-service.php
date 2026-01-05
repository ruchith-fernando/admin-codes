<?php
// ajax-pending-service.php
session_start();
require_once 'connections/connection.php';

$query = "SELECT s.id, s.vehicle_number, s.service_date, v.vehicle_type, v.assigned_user
          FROM tbl_admin_vehicle_service s
          JOIN tbl_admin_vehicle v ON s.vehicle_number = v.vehicle_number
          WHERE s.status = 'Pending'
          ORDER BY s.service_date DESC";
$result = $conn->query($query);
?>

<?php if ($result && $result->num_rows > 0): ?>
<table class="table table-bordered table-responsive">
    <thead class="table-light">
        <tr>
            <th>Vehicle No</th>
            <th>Assigned User</th>
            <th>Vehicle Type</th>
            <!-- <th>Service Type</th> -->
             <th>Maintenance Type</th>
            <th>Service Date</th>
            <th>View & Approve</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
                <td><?= htmlspecialchars($row['assigned_user']) ?></td>
                <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
                <td>Vehicle Service</td>
                <td><?= htmlspecialchars($row['service_date']) ?></td>
                <td>
                    <button
                        class="btn btn-primary btn-sm viewServiceApproveBtn"
                        data-id="<?= $row['id'] ?>"
                    >View & Approve</button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
    <!-- Modal -->
    <!-- <div class="modal fade" id="simpleApprovalModal" tabindex="-1" aria-labelledby="simpleApprovalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-0" id="simpleModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div> -->
<script>
$(document).on('click', '.viewServiceApproveBtn', function () {
    const id = $(this).data('id');

    $.ajax({
        url: 'ajax-get-service-form.php',
        type: 'POST',
        data: { id: id },
        success: function (response) {
            $('#simpleModalBody').html(response); 
            const modalEl = document.getElementById('simpleApprovalModal');
            const existing = bootstrap.Modal.getInstance(modalEl);
            if (existing) existing.dispose();
            const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
            modal.show();
        },
        error: function () {
            $('#simpleModalBody').html('<div class="alert alert-danger">Failed to load form.</div>');
        }
    });
});
</script>


<?php else: ?>
    <div class="alert alert-info">No pending service entries found.</div>
<?php endif; ?>
