<?php
include 'connections/connection.php';

$request_type = $_POST['request_type']; // Always "mobile"
$name = $_POST['name'];
$hris = $_POST['hris'];
$nic = $_POST['nic'];
$designation = $_POST['designation'];
$branch_division = $_POST['branch_division'];
$employee_category = $_POST['employee_category'];
$email = $_POST['email'];

$sql = "INSERT INTO tbl_admin_sim_request (request_type, name, hris, nic, designation, branch_division, employee_category, email, status)
VALUES ('$request_type', '$name', '$hris', '$nic', '$designation', '$branch_division', '$employee_category', '$email', 'Initiated')";

if ($conn->query($sql) === TRUE) {
    echo "Mobile Phone request submitted successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
