<?php
// ajax-pending-license.php
require_once 'connections/connection.php';

$sql = "SELECT l.*, 
               v.vehicle_type, 
               u.name AS entered_name, v.assigned_user AS assigned_user
        FROM tbl_admin_vehicle_licensing_insurance l
        LEFT JOIN tbl_admin_vehicle v ON l.vehicle_number = v.vehicle_number
        LEFT JOIN tbl_admin_users u ON l.entered_by = u.hris
        WHERE l.status = 'Pending'
        ORDER BY l.created_at DESC";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "<div class='alert alert-info'>No pending license/insurance entries found.</div>";
    exit;
}
?>

<table class="table table-bordered table-sm table-hover">
    <thead class="table-light">
        <tr>
            <th>Vehicle No</th>
            <th>Assigned User</th>
            <th>Vehicle Type</th>
            <th>Maintenance Type</th>
            <th>License Date</th>
            <th>Handling Person</th>
            <th>View & Approve</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['vehicle_number']) ?></td>
            <td><?= htmlspecialchars($row['assigned_user']) ?></td>
            <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
            <td>License / Insurance</td>
            <td><?= htmlspecialchars($row['revenue_license_date']) ?></td>
            <td><?= htmlspecialchars($row['person_handled']) ?></td>
            <td>
                <button class="btn btn-sm btn-primary btn-verify"
                        data-type="license" 
                        data-id="<?= $row['id'] ?>">
                    View & Approve
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
