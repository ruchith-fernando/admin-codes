<?php
session_start();
require_once 'connections/connection.php';

$user_id = $_SESSION['hris_id'] ?? '';
$category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
$month = mysqli_real_escape_string($conn, $_POST['month'] ?? '');

if(!$user_id || !$category || !$month) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

// Check current selection for this user
$check = $conn->query("
    SELECT is_selected FROM tbl_admin_dashboard_month_selection 
    WHERE user_id='$user_id' AND category='$category' AND month_name='$month'");
$current = $check->fetch_assoc();

if($current) {
    $new_status = $current['is_selected'] === 'yes' ? 'no' : 'yes';
    $conn->query("
        UPDATE tbl_admin_dashboard_month_selection 
        SET is_selected='$new_status' 
        WHERE user_id='$user_id' AND category='$category' AND month_name='$month'");
} else {
    $new_status = 'yes'; // Default to yes on first toggle
    $conn->query("
        INSERT INTO tbl_admin_dashboard_month_selection 
        (user_id, category, month_name, is_selected) 
        VALUES ('$user_id', '$category', '$month', 'yes')");
}

echo json_encode(['status' => 'success', 'selected' => $new_status]);
?>
