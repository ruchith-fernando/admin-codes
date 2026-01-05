<?php
include "../connections/connection.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_pending_requests'])) {
    $type = $_POST['request_type'];
    $hris = $_SESSION['hris_id'];

    $sql = "SELECT * FROM tbl_admin_stationary_orders 
            WHERE request_type = '$type' 
              AND created_by = '$hris'
              AND status != 'completed'
            ORDER BY created_at DESC";

    $res = mysqli_query($conn, $sql);

    echo "<table class='table table-bordered table-sm'>";
    echo "<thead><tr>
            <th>Order No</th>
            <th>Branch</th>
            <th>Request Date</th>
            <th>Status</th>
          </tr></thead><tbody>";

    while ($row = mysqli_fetch_assoc($res)) {
        echo "<tr>";
        echo "<td>{$row['order_number']}</td>";
        echo "<td>{$row['branch_name']}</td>";
        echo "<td>{$row['requested_date']}</td>";
        echo "<td><span class='badge bg-info text-dark'>" . strtoupper($row['status']) . "</span></td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
}
?>
