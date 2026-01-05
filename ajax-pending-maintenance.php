<?php
// ajax-pending-maintenance.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Ensure session is started
require_once 'connections/connection.php';
$loggedInUser = $_SESSION['hris'] ?? '';

// Fetch pending maintenance entries
$query = "SELECT m.*, v.vehicle_number, v.vehicle_type, v.assigned_user
          FROM tbl_admin_vehicle_maintenance m
          JOIN tbl_admin_vehicle v ON m.vehicle_number = v.vehicle_number
          WHERE m.status = 'Pending'
          ORDER BY m.purchase_date";
$result = $conn->query($query);
?>

<?php if ($result && $result->num_rows > 0): ?>
    <table class="table table-bordered table-responsive">
        <thead class="table-light">
            <tr>
                <th>Vehicle No</th>
                <th>Assigned User</th>
                <th>Vehicle Type</th>
                <th>Maintenance Type</th>
                <th>Repair Date</th>
                <th>View & Approve</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $maintenanceType = ucfirst($row['maintenance_type']);
                    $purchaseDate = ($row['purchase_date'] != '0000-00-00') ? $row['purchase_date'] : '';
                    $repairDate = ($row['repair_date'] != '0000-00-00') ? $row['repair_date'] : '';
                    $dateToDisplay = $purchaseDate ?: $repairDate;
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
                    <td><?= htmlspecialchars($row['assigned_user']) ?></td>
                    <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
                    <td><?= htmlspecialchars($maintenanceType) ?></td>
                    <td><?= htmlspecialchars($dateToDisplay) ?></td>
                    <td>
                        <button
                            class="btn btn-primary btn-sm viewApproveBtn"
                            data-id="<?= $row['id'] ?>"
                            data-vehicle="<?= htmlspecialchars($row['vehicle_number']) ?>"
                            data-type="<?= htmlspecialchars($row['vehicle_type']) ?>"
                            data-mtype="<?= htmlspecialchars($row['maintenance_type']) ?>"
                            data-date="<?= htmlspecialchars($dateToDisplay) ?>"
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
        $(document).on('click', '.viewApproveBtn', function () {
            const id = $(this).data('id');
            const mtype = $(this).data('mtype');

            $.ajax({
                url: 'ajax-get-maintenance-form.php',
                type: 'POST',
                data: { id: id, type: mtype },
                success: function (response) {
                    $('#simpleModalBody').html(response);

                    // Prevent outside click and ESC from closing modal
                    const modalEl = document.getElementById('simpleApprovalModal');
                    const existingModal = bootstrap.Modal.getInstance(modalEl);
                    if (existingModal) {
                        existingModal.dispose();
                    }
                    const modal = new bootstrap.Modal(modalEl, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    modal.show();
                },
                error: function () {
                    $('#simpleModalBody').html('<div class="alert alert-danger">Failed to load form.</div>');
                }
            });
        });
        </script>


<?php else: ?>
    <div class="alert alert-info">No pending maintenance entries found.</div>
<?php endif; ?>
