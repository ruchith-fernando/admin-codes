<?php
include 'connections/connection.php';
session_start();

// Get session details
$branch_code = $_SESSION['branch_code'] ?? '';
$hris = $_SESSION['hris'] ?? '';

// Prepare SQL query
$sql = "SELECT DISTINCT order_number, branch_name, requested_date, status 
        FROM tbl_admin_stationary_orders 
        WHERE branch_code = ? 
        AND (
            status = 'draft' 
            OR (status = 'pending_admin' AND created_by = ?)
        )
        ORDER BY requested_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $branch_code, $hris);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<p class="text-muted">No requests found for your branch.</p>';
} else {
    echo '<table class="table table-bordered">';
    echo '<thead>
            <tr>
              <th>Order Number</th>
              <th>Branch Name</th>
              <th>Requested Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        $order_number = htmlspecialchars($row['order_number']);
        $branch_name = htmlspecialchars($row['branch_name']);
        $requested_date = htmlspecialchars($row['requested_date']);
        $status = htmlspecialchars($row['status']);

        echo '<tr>';
        echo "<td>$order_number</td>";
        echo "<td>$branch_name</td>";
        echo "<td>$requested_date</td>";
        echo "<td>$status</td>";
        echo '<td>
                <button class="btn btn-sm btn-primary edit-boic-btn" data-order="' . $order_number . '">View/Edit</button>
              </td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>
