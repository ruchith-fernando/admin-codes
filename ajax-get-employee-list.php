<?php
include 'connections/connection.php';

$query = isset($_POST['query']) ? $_POST['query'] : '';
$sql = "SELECT hris, full_name, nic_no, mobile_no FROM tbl_admin_employee_details 
        WHERE hris LIKE ? OR full_name LIKE ? OR nic_no LIKE ? OR mobile_no LIKE ?
        LIMIT 20";
$stmt = $conn->prepare($sql);
$term = "%$query%";
$stmt->bind_param('ssss', $term, $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  echo '<table class="table table-bordered table-striped">';
  echo '<thead><tr><th>HRIS</th><th>Full Name</th><th>NIC</th><th>Mobile</th><th>Action</th></tr></thead><tbody>';
  while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . $row['hris'] . '</td>';
    echo '<td>' . $row['full_name'] . '</td>';
    echo '<td>' . $row['nic_no'] . '</td>';
    echo '<td>' . $row['mobile_no'] . '</td>';
    echo '<td><button class="btn btn-sm btn-primary viewEmployeeBtn" data-hris="' . $row['hris'] . '">View</button></td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
} else {
  echo '<div class="alert alert-warning">No employees found</div>';
}
?>
