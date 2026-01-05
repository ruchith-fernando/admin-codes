<?php
// water-branch-update.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function respond($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

try {
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $branch_code = strtoupper(trim($_POST['branch_code'] ?? ''));
    $branch_name = trim($_POST['branch_name'] ?? '');
    $region      = trim($_POST['region'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $is_active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

    if ($id <= 0)          respond('error', 'Missing or invalid ID.');
    if ($branch_code === '') respond('error', 'Branch Code is required.');
    if ($branch_name === '') respond('error', 'Branch Name is required.');

    $sql = "
        UPDATE tbl_admin_branches
        SET branch_code = ?, branch_name = ?, region = ?, address = ?, city = ?, is_active = ?
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssii',
        $branch_code,
        $branch_name,
        $region,
        $address,
        $city,
        $is_active,
        $id
    );

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() == 1062) {
            // duplicate branch code
            userlog('Branch UPDATE duplicate code', [
                'id' => $id,
                'branch_code' => $branch_code,
                'error' => $ex->getMessage()
            ]);
            respond('error', 'Branch code already exists for another branch.');
        }
        userlog('Branch UPDATE SQL error', [
            'id' => $id,
            'error' => $ex->getMessage(),
            'code' => $ex->getCode()
        ]);
        respond('error', 'Database error during update. [' . $ex->getCode() . ']');
    }

    if ($stmt->affected_rows >= 0) {
        userlog('Branch UPDATE success', [
            'id' => $id,
            'branch_code' => $branch_code,
            'branch_name' => $branch_name,
            'region' => $region,
            'address' => $address,
            'city' => $city,
            'is_active' => $is_active
        ]);
        respond('ok', 'Branch updated successfully.');
    } else {
        userlog('Branch UPDATE no-change', ['id' => $id]);
        respond('ok', 'No changes were made.');
    }

} catch (Throwable $e) {
    userlog('Branch UPDATE fatal error', ['error' => $e->getMessage()]);
    respond('error', 'Unexpected error: ' . $e->getMessage());
}
