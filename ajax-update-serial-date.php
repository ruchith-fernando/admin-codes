<?php
include 'connections/connection.php';
header('Content-Type: application/json');

$postage_id = $_POST['postage_id'] ?? '';
$postal_serial = trim($_POST['postal_serial_number'] ?? '');
$date_posted = $_POST['date_posted'] ?? '';

if (!$postage_id || !$postal_serial || !$date_posted) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Check for duplicates
$check = $conn->prepare("SELECT COUNT(*) FROM tbl_admin_actual_postage_stamps WHERE postal_serial_number = ? AND id != ?");
$check->bind_param("si", $postal_serial, $postage_id);
$check->execute();
$check->bind_result($count);
$check->fetch();
$check->close();

if ($count > 0) {
    echo json_encode(['success' => false, 'message' => 'This Postal Serial Number is already used.']);
    exit;
}

// Proceed to update
$stmt = $conn->prepare("UPDATE tbl_admin_actual_postage_stamps SET postal_serial_number = ?, date_posted = ? WHERE id = ?");
$stmt->bind_param("ssi", $postal_serial, $date_posted, $postage_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
}
