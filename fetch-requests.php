<?php
include 'connections/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

$branch_code = $_SESSION['branch_code'] ?? '';

if ($branch_code === '') {
    echo '<div class="text-danger">Invalid access.</div>';
    exit;
}

$sql = "SELECT DISTINCT o.order_number, o.request_type, o.requested_date
        FROM tbl_admin_stationary_orders o
        WHERE o.branch_code = ?
        AND (
          (o.request_type = 'daily_courier' AND DATE(o.requested_date) = CURDATE())
          OR
          (o.request_type = 'stationery_pack' AND DATE_FORMAT(o.requested_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m'))
        )
        ORDER BY o.requested_date DESC, o.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<div class="text-muted">No requests submitted yet for today or this month.</div>';
} else {
    echo '<div class="table-responsive">
            <table class="table table-bordered table-sm">
            <thead class="table-light">
              <tr>
                <th>Request Type</th>
                <th>Order Number</th>
                <th>Request Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>';
    while ($row = $res->fetch_assoc()) {
        $reqDate = htmlspecialchars($row['requested_date']);
        echo '<tr>
                <td>' . ucfirst(str_replace('_', ' ', $row['request_type'])) . '</td>
                <td>' . htmlspecialchars($row['order_number']) . '</td>
                <td>' . $reqDate . '</td>
                <td>
                  <button class="btn btn-sm btn-primary view-items-btn" 
                          data-order="' . $row['order_number'] . '" 
                          data-type="' . $row['request_type'] . '">
                    View Order
                  </button>';
        if ($row['request_type'] === 'daily_courier') {
            echo ' <button class="btn btn-sm btn-warning push-btn ms-1" 
                          data-order="' . $row['order_number'] . '">
                    Push to Storekeeper
                  </button>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>
