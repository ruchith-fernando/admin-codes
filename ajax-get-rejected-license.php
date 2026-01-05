<?php
// ajax-get-rejected-license.php
session_start();
include("connections/connection.php");

$current_user = $_SESSION['hris'];

$sql = "SELECT t1.id, t1.vehicle_number, t2.vehicle_type, t1.revenue_license_date, 
t2.assigned_user, t1.person_handled, t1.rejected_by, t1.rejection_reason
FROM tbl_admin_vehicle_licensing_insurance t1 
LEFT JOIN tbl_admin_vehicle t2
ON t1.vehicle_number = t2.vehicle_number
WHERE t1.status = 'rejected' 
ORDER BY t1.rejected_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Vehicle No</th>
                    <th>Type</th>
                    <th>Revenue License Date</th>
                    <th>Assigned User</th>
                    <th>Handled By</th>
                    <th>Rejected By</th>
                    <th>Rejection Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';

    while ($row = $result->fetch_assoc()) {
        echo '<tr>
                <td>' . htmlspecialchars($row['vehicle_number']) . '</td>
                <td>' . htmlspecialchars($row['vehicle_type']) . '</td>
                <td>' . htmlspecialchars($row['revenue_license_date']) . '</td>
                <td>' . htmlspecialchars($row['assigned_user']) . '</td>
                <td>' . nl2br(htmlspecialchars($row['person_handled'])) . '</td>
                <td>' . nl2br(htmlspecialchars($row['rejected_by'])) . '</td>
                <td>' . nl2br(htmlspecialchars($row['rejection_reason'])) . '</td>
                <td><button class="btn btn-danger btn-sm" onclick="deleteRejectedLicense(' . $row['id'] . ')">Delete</button></td>
              </tr>';
    }

    echo '  </tbody>
          </table>';
} else {
    echo '<div class="alert alert-info">No rejected license/insurance records found.</div>';
}
?>
