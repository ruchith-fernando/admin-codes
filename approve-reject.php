<?php
include 'connections/connection.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = $_GET['id'];
$action = $_GET['action'];
$user = 'DivisionalHead'; // Assume logged-in user, or session user

// Update status
$status = ($action == 'approved') ? 'Approved' : 'Rejected';
$sql = "UPDATE tbl_admin_sim_request SET status='$status' WHERE id=$id";

if ($conn->query($sql) === TRUE) {
    // Save to approval history
    $conn->query("INSERT INTO tbl_admin_sim_approval_history (request_id, action_by, action) VALUES ($id, '$user', '$status')");
    echo "Request has been $status.";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
