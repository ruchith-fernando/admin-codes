<?php
require_once 'connections/connection.php';

$query = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE status = 'rejected' ORDER BY received_date DESC";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo "<div class='alert alert-warning'>No rejected maintenance records found.</div>";
} else {
    echo "<div class='table-responsive'><table class='table table-bordered table-sm'>
            <thead class='table-light'>
                <tr>
                    <th>Vehicle No</th>
                    <th>Category</th>
                    <th>Details</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Rejected By</th>
                </tr>
            </thead>
            <tbody>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>
                <td>{$row['vehicle_number']}</td>
                <td>{$row['maintenance_type']}</td>
                <td>{$row['description']}</td>
                <td>{$row['price']}</td>
                <td>{$row['received_date']}</td>
                <td>{$row['rejected_by']}</td>
              </tr>";
    }
    echo "</tbody></table></div>";
}
?>
