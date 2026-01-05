<?php
// submit-vendor.php
include 'connections/connection.php';
require_once 'includes/userlog.php';

header('Content-Type: application/json');

// Read & validate
$vendor_name = trim($_POST['vendor_name'] ?? '');
$vendor_type = strtoupper(trim($_POST['vendor_type'] ?? 'WATER'));
$vendor_code = trim($_POST['vendor_code'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$email       = trim($_POST['email'] ?? '');
$address     = trim($_POST['address'] ?? '');
$is_active   = isset($_POST['is_active']) ? 1 : 0;

if ($vendor_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Vendor name is required.']);
    exit;
}

$validTypes = ['WATER','ELECTRICITY','OTHER'];
if (!in_array($vendor_type, $validTypes, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid vendor type.']);
    exit;
}

// Escape values
$vendor_name_esc = mysqli_real_escape_string($conn, $vendor_name);
$vendor_type_esc = mysqli_real_escape_string($conn, $vendor_type);
$vendor_code_esc = mysqli_real_escape_string($conn, $vendor_code);
$phone_esc       = mysqli_real_escape_string($conn, $phone);
$email_esc       = mysqli_real_escape_string($conn, $email);
$address_esc     = mysqli_real_escape_string($conn, $address);
$is_active_int   = (int)$is_active;

// Simple INSERT – unique key is (vendor_name, vendor_type)
// If you want update-on-duplicate, we can easily swap this to ON DUPLICATE KEY UPDATE
$sql = "
    INSERT INTO tbl_admin_vendors
        (vendor_code, vendor_name, vendor_type, phone, email, address, is_active)
    VALUES
        ('$vendor_code_esc', '$vendor_name_esc', '$vendor_type_esc',
         '$phone_esc', '$email_esc', '$address_esc', $is_active_int)
";

if (!mysqli_query($conn, $sql)) {
    $errno = mysqli_errno($conn);
    $err   = mysqli_error($conn);

    if ($errno == 1062) {
        // duplicate entry for (vendor_name, vendor_type)
        echo json_encode([
            'status'  => 'warning',
            'message' => 'A vendor with this name and type already exists.'
        ]);
    } else {
        try {
            userlog('Vendor INSERT DB ERROR', [
                'errno' => $errno,
                'error' => $err,
                'sql'   => $sql
            ]);
        } catch (Throwable $e) {}

        echo json_encode([
            'status'  => 'error',
            'message' => 'Database error while saving vendor: ' . htmlspecialchars($err)
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

    userlog("✅ $username ($hris) created vendor '$vendor_name' [$vendor_type]", [
        'vendor_id'   => $insertId,
        'vendor_code' => $vendor_code,
        'phone'       => $phone,
        'email'       => $email,
        'address'     => $address,
        'is_active'   => $is_active
    ]);
} catch (Throwable $e) {
    // ignore logging errors
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Vendor created successfully. (ID: ' . (int)$insertId . ')'
]);
exit;
