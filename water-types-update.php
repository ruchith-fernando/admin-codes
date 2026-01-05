<?php
// water-types-update.php
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
    $id    = isset($_POST['water_type_id']) ? (int)$_POST['water_type_id'] : 0;
    $code  = strtoupper(trim($_POST['water_type_code'] ?? ''));
    $name  = trim($_POST['water_type_name'] ?? '');
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

    if ($id <= 0)       respond('error', 'Invalid water type ID.');
    if ($code === '')   respond('error', 'Type code is required.');
    if ($name === '')   respond('error', 'Type name is required.');

    $sql = "
        UPDATE tbl_admin_water_types
        SET water_type_code = ?, water_type_name = ?, is_active = ?
        WHERE water_type_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $code, $name, $active, $id);

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() == 1062) {
            userlog('Water type UPDATE duplicate code', [
                'id' => $id, 'code' => $code, 'error' => $ex->getMessage()
            ]);
            respond('error', 'Type code already exists for another record.');
        }
        userlog('Water type UPDATE SQL error', [
            'id' => $id,
            'error' => $ex->getMessage(),
            'code' => $ex->getCode()
        ]);
        respond('error', 'Database error during update. ['.$ex->getCode().']');
    }

    if ($stmt->affected_rows >= 0) {
        userlog('Water type UPDATE success', [
            'id' => $id, 'code' => $code, 'name' => $name, 'is_active' => $active
        ]);
        respond('ok', 'Water type updated successfully.');
    } else {
        userlog('Water type UPDATE no change', ['id' => $id]);
        respond('ok', 'No changes were made.');
    }

} catch (Throwable $e) {
    userlog('Water type UPDATE fatal', ['error' => $e->getMessage()]);
    respond('error', 'Unexpected error: '.$e->getMessage());
}
