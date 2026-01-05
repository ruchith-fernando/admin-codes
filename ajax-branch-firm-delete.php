<?php
// ajax-branch-firm-delete.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid record.']);
    exit;
}

// Get mapping + firm for log
$stmt_sel = $conn->prepare("
    SELECT m.branch_code, m.branch_name, m.firm_id, f.firm_name
    FROM tbl_admin_branch_firm_map m
    LEFT JOIN tbl_admin_security_firms f ON m.firm_id = f.id
    WHERE m.id = ?
    LIMIT 1
");
$stmt_sel->bind_param("i", $id);
$stmt_sel->execute();
$res_sel = $stmt_sel->get_result();
$map = $res_sel->fetch_assoc();

if (!$map) {
    echo json_encode(['success' => false, 'message' => 'Mapping not found.']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE tbl_admin_branch_firm_map
    SET active = 'no'
    WHERE id = ?
");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Userlog
    $hris     = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? 'SYSTEM';

    try {
        $msg = sprintf(
            "🗑️ %s (%s) removed branch mapping: %s - %s from %s (firm_id=%d, map_id=%d)",
            $username,
            $hris,
            $map['branch_code'],
            $map['branch_name'],
            $map['firm_name'] ?? 'Unknown Firm',
            $map['firm_id'],
            $id
        );
        userlog($msg);
    } catch (Throwable $e) {
        // silent fail
    }

    echo json_encode(['success' => true, 'message' => 'Branch mapping removed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to remove mapping.']);
}
