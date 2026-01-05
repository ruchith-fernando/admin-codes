<?php
// photocopy-rate-profiles-deactivate.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$id = isset($_POST['rate_profile_id']) ? (int)$_POST['rate_profile_id'] : 0;
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid rate profile id."]);
    exit;
}

$stmt = $conn->prepare("UPDATE tbl_admin_photocopy_rate_profiles SET is_active = 0 WHERE rate_profile_id = ? LIMIT 1");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    userlog("⛔ Photocopy Rate Profile Deactivated | ID: {$id}");
    echo json_encode(["success" => true, "message" => "Rate profile deactivated."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed: " . mysqli_error($conn)]);
}
