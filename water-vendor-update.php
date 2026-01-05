<?php
// water-vendor-update.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function respond($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

try {
    $id          = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    $vendor_code = trim($_POST['vendor_code'] ?? '');
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $vendor_type = strtoupper(trim($_POST['vendor_type'] ?? 'WATER'));
    $phone       = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $is_active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

    if ($id <= 0)          respond('error', 'Invalid vendor ID.');
    if ($vendor_name === '') respond('error', 'Vendor name is required.');

    $validTypes = ['WATER','ELECTRICITY','OTHER'];
    if (!in_array($vendor_type, $validTypes, true)) {
        respond('error', 'Invalid vendor type.');
    }

    $sql = "
        UPDATE tbl_admin_vendors
        SET vendor_code = ?, vendor_name = ?, vendor_type = ?,
            phone = ?, email = ?, address = ?, is_active = ?
        WHERE vendor_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssii',
        $vendor_code,
        $vendor_name,
        $vendor_type,
        $phone,
        $email,
        $address,
        $is_active,
        $id
    );

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() == 1062) {
            // Duplicate vendor_name + vendor_type
            userlog('Vendor UPDATE duplicate', [
                'id' => $id,
                'vendor_name' => $vendor_name,
                'vendor_type' => $vendor_type,
                'error' => $ex->getMessage()
            ]);
            respond('error', 'A vendor with this name and type already exists.');
        }
        userlog('Vendor UPDATE SQL error', [
            'id' => $id,
            'error' => $ex->getMessage(),
            'code' => $ex->getCode()
        ]);
        respond('error', 'Database error during update. ['.$ex->getCode().']');
    }

    if ($stmt->affected_rows >= 0) {
        userlog('Vendor UPDATE success', [
            'id' => $id,
            'vendor_code' => $vendor_code,
            'vendor_name' => $vendor_name,
            'vendor_type' => $vendor_type,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'is_active' => $is_active
        ]);
        respond('ok', 'Vendor updated successfully.');
    } else {
        userlog('Vendor UPDATE no change', ['id' => $id]);
        respond('ok', 'No changes were made.');
    }

} catch (Throwable $e) {
    userlog('Vendor UPDATE fatal', ['error' => $e->getMessage()]);
    respond('error', 'Unexpected error: '.$e->getMessage());
}
