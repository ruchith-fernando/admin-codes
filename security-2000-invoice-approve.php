<?php
// security-2000-invoice-approve.php
require_once 'connections/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Map your session vars
$checkerHris  = $_SESSION['hris_no']   ?? $_SESSION['hris'] ?? null;
$checkerName  = $_SESSION['full_name'] ?? $_SESSION['name'] ?? null;
$checkerLogin = $_SESSION['username']  ?? $_SESSION['user_id'] ?? null;

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$reason = trim($_POST['reason'] ?? '');

if (!$id || !in_array($action, ['approve','reject'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request (id/action missing).'
    ]);
    exit;
}

// Get current record
$stmt = $conn->prepare("
    SELECT 
        entered_by,
        approval_status
    FROM tbl_admin_actual_security_2000_invoices
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$row = $res->fetch_assoc()) {
    echo json_encode([
        'success' => false,
        'message' => 'Record not found.'
    ]);
    exit;
}

$entered_by      = $row['entered_by'];
$approval_status = $row['approval_status'];

// Must be pending
if ($approval_status !== 'pending') {
    echo json_encode([
        'success' => false,
        'message' => 'Record is not pending; nothing to approve/reject.'
    ]);
    exit;
}

// Dual control: checker cannot be the same as maker
if (!empty($entered_by) && $entered_by === $checkerLogin) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot approve/reject a record you entered (dual control).'
    ]);
    exit;
}

if ($action === 'approve') {
    $sql = "
        UPDATE tbl_admin_actual_security_2000_invoices
        SET approval_status  = 'approved',
            approved_hris    = ?,
            approved_name    = ?,
            approved_by      = ?,
            approved_at      = NOW(),
            rejected_hris    = NULL,
            rejected_name    = NULL,
            rejected_by      = NULL,
            rejected_at      = NULL,
            rejection_reason = NULL
        WHERE id = ?
    ";
    $stmt_u = $conn->prepare($sql);
    $stmt_u->bind_param("sssi", $checkerHris, $checkerName, $checkerLogin, $id);

    if ($stmt_u->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Invoice record approved.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error approving record: ' . $stmt_u->error
        ]);
    }
    exit;
}

// action === 'reject'
if ($reason === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Rejection reason is required.'
    ]);
    exit;
}

$sql = "
    UPDATE tbl_admin_actual_security_2000_invoices
    SET approval_status  = 'rejected',
        rejected_hris    = ?,
        rejected_name    = ?,
        rejected_by      = ?,
        rejected_at      = NOW(),
        rejection_reason = ?
    WHERE id = ?
";
$stmt_u = $conn->prepare($sql);
$stmt_u->bind_param("ssssi", $checkerHris, $checkerName, $checkerLogin, $reason, $id);

if ($stmt_u->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Invoice record rejected.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error rejecting record: ' . $stmt_u->error
        ]);
}
