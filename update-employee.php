<?php
include 'connections/connection.php';

$hris_no = $_POST['hris_no'];
$mobile_no = $_POST['mobile_no'];
$voice_data = $_POST['voice_data'];
$company_contribution = $_POST['company_contribution'];
$remarks = $_POST['remarks'];
$connection_status = $_POST['connection_status'];

$sql = "UPDATE tbl_admin_mobile_issues 
        SET mobile_no = ?, 
            voice_data = ?, 
            company_contribution = ?, 
            remarks = ?, 
            connection_status = ?
        WHERE hris_no = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Debugging line to see why it failed
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit;
}

$stmt->bind_param("ssssss", $mobile_no, $voice_data, $company_contribution, $remarks, $connection_status, $hris_no);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}

$stmt->close();
$conn->close();
?>
