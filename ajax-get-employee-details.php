<?php
include 'connections/connection.php';

$hris = $_POST['hris'];
$sql = "SELECT * FROM tbl_admin_employee_details WHERE hris = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $hris);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
  echo '<table class="table table-sm">';
  echo '<tr><th>HRIS</th><td>' . $data['hris'] . '</td></tr>';
  echo '<tr><th>Full Name</th><td>' . $data['full_name'] . '</td></tr>';
  echo '<tr><th>NIC</th><td>' . $data['nic_no'] . '</td></tr>';
  echo '<tr><th>Mobile</th><td>' . $data['mobile_no'] . '</td></tr>';
  echo '<tr><th>Designation</th><td>' . $data['designation'] . '</td></tr>';
  echo '<tr><th>Category</th><td>' . $data['category'] . '</td></tr>';
  echo '</table>';
} else {
  echo '<div class="alert alert-danger">Employee not found</div>';
}
?>
