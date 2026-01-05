<?php
// ajax-water-get-branch-name.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing code']);
    exit;
}

try {
    $sql = "
        SELECT branch_name
        FROM tbl_admin_branch_water
        WHERE branch_code = ?
        ORDER BY updated_at DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        userlog('Water branch name lookup', ['branch_code' => $code, 'branch_name' => $res['branch_name']]);
        echo json_encode(['status' => 'ok', 'branch_name' => $res['branch_name']]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
