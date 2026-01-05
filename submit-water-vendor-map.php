<?php
// submit-water-vendor-map.php
include 'connections/connection.php';
require_once 'includes/userlog.php';

header('Content-Type: application/json');

// Read POST
$water_type_id    = (int)($_POST['water_type_id'] ?? 0);
$vendor_master_id = (int)($_POST['vendor_master_id'] ?? 0);
$vendor_name      = trim($_POST['vendor_name'] ?? '');
$is_active        = isset($_POST['is_active']) ? 1 : 0;

// Basic validation
if ($water_type_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Water type is required.']);
    exit;
}
if ($vendor_master_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Vendor is required.']);
    exit;
}

// If display name empty, get from master vendor
if ($vendor_name === '') {
    $q = mysqli_query(
        $conn,
        "SELECT vendor_name FROM tbl_admin_vendors WHERE vendor_id = {$vendor_master_id} LIMIT 1"
    );
    if ($q && $row = mysqli_fetch_assoc($q)) {
        $vendor_name = $row['vendor_name'];
    }
    if ($vendor_name === '' || $vendor_name === null) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to resolve vendor name.']);
        exit;
    }
}

// Escape
$vendor_name_esc = mysqli_real_escape_string($conn, $vendor_name);
$wt_id           = (int)$water_type_id;
$vm_id           = (int)$vendor_master_id;
$is_active_int   = (int)$is_active;

// Simple INSERT – if you have a UNIQUE key (water_type_id, vendor_master_id)
// this will throw 1062 on duplicate
$sql = "
    INSERT INTO tbl_admin_water_vendors
        (water_type_id, vendor_name, is_active, vendor_master_id)
    VALUES
        ($wt_id, '$vendor_name_esc', $is_active_int, $vm_id)
";

if (!mysqli_query($conn, $sql)) {
    $errno = mysqli_errno($conn);
    $err   = mysqli_error($conn);

    if ($errno == 1062) {
        // Mapping already exists
        echo json_encode([
            'status'  => 'warning',
            'message' => 'This water type is already mapped to this vendor.'
        ]);
    } else {
        try {
            userlog('Water vendor mapping DB ERROR', [
                'errno' => $errno,
                'error' => $err,
                'sql'   => $sql
            ]);
        } catch (Throwable $e) {}

        echo json_encode([
            'status'  => 'error',
            'message' => 'Database error while saving mapping: ' . htmlspecialchars($err)
        ]);
    }
    exit;
}

// Success
$insertId = mysqli_insert_id($conn);

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $username = $_SESSION['name'] ?? 'SYSTEM';
    $hris     = $_SESSION['hris'] ?? 'UNKNOWN';

    userlog("✅ $username ($hris) mapped water type to vendor", [
        'water_vendor_id'  => $insertId,
        'water_type_id'    => $water_type_id,
        'vendor_master_id' => $vendor_master_id,
        'vendor_name'      => $vendor_name,
        'is_active'        => $is_active
    ]);
} catch (Throwable $e) {
    // ignore logging errors
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Water vendor mapping saved successfully. (ID: ' . (int)$insertId . ')'
]);
exit;
