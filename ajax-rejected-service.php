<?php
// ajax-rejected-service.php
session_start();
require_once 'connections/connection.php';

$current_user = $_SESSION['hris'];

$sql = "SELECT * FROM tbl_admin_vehicle_service WHERE status = 'Rejected' AND entered_by = ? ORDER BY service_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Vehicle Number</th>
                    
                    <th>Service Date</th>
                    <th>Amount</th>
                    <th>Rejection Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';

    while ($row = $result->fetch_assoc()) {
        echo '<tr>
                <td>' . htmlspecialchars($row['vehicle_number']) . '</td>
                
                <td>' . htmlspecialchars($row['service_date']) . '</td>
                <td>' . number_format((float)$row['amount'], 2) . '</td>
                <td>' . nl2br(htmlspecialchars($row['rejection_reason'])) . '</td>
                <td><button class="btn btn-danger btn-sm" onclick="deleteRejectedService(' . $row['id'] . ')">Delete</button></td>
              </tr>';
    }

    echo '  </tbody>
          </table>';
} else {
    echo '<div class="alert alert-info">No rejected service records found.</div>';
}
?>
