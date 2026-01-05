<!-- submit-sim-request.php -->
<?php
include 'connections/connection.php';

$request_type = $_POST['request_type'];
$name = $_POST['name'];
$hris = $_POST['hris'];
$nic = $_POST['nic'];
$designation = $_POST['designation'];
$branch_division = $_POST['branch_division'];
$employee_category = $_POST['employee_category'];
$voice_data = $_POST['voice_data'];
$voice_package = $_POST['voice_package'];
$data_package = $_POST['data_package'];
$email = $_POST['email'];

$sql = "INSERT INTO tbl_admin_sim_request (request_type, name, hris, nic, designation, branch_division, employee_category, voice_data, voice_package, data_package, email, status)
VALUES ('$request_type', '$name', '$hris', '$nic', '$designation', '$branch_division', '$employee_category', '$voice_data', '$voice_package', '$data_package', '$email', 'Initiated')";

if ($conn->query($sql) === TRUE) {
    echo "SIM/Transfer request submitted successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
