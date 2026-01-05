<?php
// ajax-branch-firm-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$firm_id     = intval($_POST['firm_id'] ?? 0);
$branch_code = trim($_POST['branch_code'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');

if (!$firm_id || $branch_code === '' || $branch_name === '') {
    echo json_encode(['success' => false, 'message' => 'Firm, branch code and branch name are required.']);
    exit;
}

// Get user info for log
$hris     = $_SESSION['hris'] ?? 'UNKNOWN';
$username = $_SESSION['name'] ?? 'SYSTEM';

// Get firm name for log
$firm_name = 'Unknown Firm';
$stmt_f = $conn->prepare("SELECT firm_name FROM tbl_admin_security_firms WHERE id = ? LIMIT 1");
if ($stmt_f) {
    $stmt_f->bind_param("i", $firm_id);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    if ($row_f = $res_f->fetch_assoc()) {
        $firm_name = $row_f['firm_name'];
    }
}

// Check if branch_code already exists (any firm)
$stmt = $conn->prepare("
    SELECT id, firm_id 
    FROM tbl_admin_branch_firm_map 
    WHERE branch_code = ? 
    LIMIT 1
");
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // Update existing mapping (reassign firm + name, reactivate)
    $id = $row['id'];
    $old_firm_id = $row['firm_id'];

    $upd = $conn->prepare("
        UPDATE tbl_admin_branch_firm_map
        SET branch_name = ?, firm_id = ?, active = 'yes'
        WHERE id = ?
    ");
    $upd->bind_param("sii", $branch_name, $firm_id, $id);
    if ($upd->execute()) {

        // Userlog for update
        try {
            $msg = sprintf(
                "🔁 %s (%s) updated branch mapping: %s - %s now linked to %s (firm_id=%d, map_id=%d)",
                $username,
                $hris,
                $branch_code,
                $branch_name,
                $firm_name,
                $firm_id,
                $id
            );
            userlog($msg);
        } catch (Throwable $e) {
            // silent fail
        }

        echo json_encode(['success' => true, 'message' => 'Branch mapping updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update mapping.']);
    }
} else {
    // Insert new mapping
    $ins = $conn->prepare("
        INSERT INTO tbl_admin_branch_firm_map (branch_code, branch_name, firm_id, active)
        VALUES (?, ?, ?, 'yes')
    ");
    $ins->bind_param("ssi", $branch_code, $branch_name, $firm_id);
    if ($ins->execute()) {
        $new_id = $ins->insert_id;

        // Userlog for new mapping
        try {
            $msg = sprintf(
                "✅ %s (%s) added branch mapping: %s - %s linked to %s (firm_id=%d, map_id=%d)",
                $username,
                $hris,
                $branch_code,
                $branch_name,
                $firm_name,
                $firm_id,
                $new_id
            );
            userlog($msg);
        } catch (Throwable $e) {
            // silent fail
        }

        echo json_encode(['success' => true, 'message' => 'Branch mapping added.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add mapping.']);
    }
}
