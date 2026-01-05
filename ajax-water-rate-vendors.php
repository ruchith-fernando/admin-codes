<?php
// ajax-water-rate-vendors.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$water_type_id = (int)($_GET['water_type_id'] ?? 0);

if ($water_type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid water type']);
    exit;
}

$sql = "
    SELECT vendor_id, vendor_name
    FROM tbl_admin_water_vendors
    WHERE water_type_id = ? AND is_active = 1
    ORDER BY vendor_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $water_type_id);
$stmt->execute();
$res = $stmt->get_result();

$vendors = [];
while ($row = $res->fetch_assoc()) {
    $vendors[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'vendors' => $vendors]);
