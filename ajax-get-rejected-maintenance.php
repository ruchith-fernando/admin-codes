<?php
// ajax-get-rejected-maintenance.php
session_start();
include("connections/connection.php");

$current_user = $_SESSION['hris'];

$sql = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE status = 'Rejected' AND entered_by = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Vehicle Number</th>
                    <th>Maintenance Type</th>
                    <th>Date</th>
                    <th>Rejection Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';

    while ($row = $result->fetch_assoc()) {
        $dateField = in_array(strtolower($row['maintenance_type']), ['battery', 'tire']) 
                     ? $row['purchase_date'] 
                     : $row['repair_date'];

        echo '<tr>
                <td>' . htmlspecialchars($row['vehicle_number']) . '</td>
                <td>' . htmlspecialchars($row['maintenance_type']) . '</td>
                <td>' . htmlspecialchars($dateField) . '</td>
                <td>' . nl2br(htmlspecialchars($row['rejection_reason'])) . '</td>
                <td><button class="btn btn-danger btn-sm" onclick="deleteRejectedMaintenance(' . $row['id'] . ')">Delete</button></td>
              </tr>';
    }

    echo '  </tbody>
          </table>';
} else {
    echo '<div class="alert alert-info">No rejected maintenance records found.</div>';
}
?>
