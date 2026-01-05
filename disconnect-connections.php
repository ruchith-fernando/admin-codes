<!-- disconnect-connections.php -->
<?php
include 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mobile_number'], $_POST['hris'])) {
    $mobile = $conn->real_escape_string(trim($_POST['mobile_number']));
    $hris = $conn->real_escape_string(trim($_POST['hris']));

    if ($mobile !== '' && $hris !== '') {
        $sql = "UPDATE tbl_admin_mobile_issues 
                SET connection_status = 'disconnected', 
                    disconnection_date = NOW() 
                WHERE mobile_no = '$mobile' AND hris_no = '$hris'";

        if ($conn->query($sql)) {
            if ($conn->affected_rows > 0) {
                echo "Mobile connection disconnected successfully.";
            } else {
                echo "No matching active connection found for this mobile number and HRIS.";
            }
        } else {
            echo "Database error: " . $conn->error;
        }
    } else {
        echo "Invalid input. Mobile number and HRIS are required.";
    }
} else {
    echo "Invalid request method.";
}
?>
