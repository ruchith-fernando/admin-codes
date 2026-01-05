<?php
// security-monthly-save.php
require_once 'connections/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 🔹 Map your session variables here
$userHris  = $_SESSION['hris_no']   ?? $_SESSION['hris'] ?? null;
$userName  = $_SESSION['full_name'] ?? $_SESSION['name'] ?? null;
$userLogin = $_SESSION['username']  ?? $_SESSION['user_id'] ?? null;

// 🔹 Helper: check if this branch is a 2000-style / invoice-based branch
function is_2000_branch($conn, $branch_code) {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $q = mysqli_query($conn, "
            SELECT branch_code 
            FROM tbl_admin_security_2000_branches
            WHERE active = 'yes'
        ");
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $cache[$r['branch_code']] = true;
            }
        }
    }

    return isset($cache[$branch_code]);
}

// Basic POST data
$firm_id     = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;
$month       = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');
$shifts      = isset($_POST['shifts']) ? (int)$_POST['shifts'] : 0;
$amount_raw  = trim($_POST['amount'] ?? '');
$provision   = ($_POST['provision'] ?? 'no') === 'yes' ? 'yes' : 'no';
$reason_id   = isset($_POST['reason_id']) && $_POST['reason_id'] !== '' ? (int)$_POST['reason_id'] : null;

// clean amount (remove commas)
$amount_raw = str_replace(',', '', $amount_raw);
$amount     = is_numeric($amount_raw) ? (float)$amount_raw : 0.0;

// 🔹 Validation
if (!$firm_id || !$month || !$branch_code || !$branch_name || !$shifts || $amount == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Fill all fields correctly (firm, month, branch, shifts, amount).'
    ]);
    exit;
}

// Optional safety: negative only allowed when provision=yes
if ($provision === 'no' && $amount < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Negative amounts are allowed only when Provision = Yes (adjustments).'
    ]);
    exit;
}

// 🔹 HARD RULE: 2000 branches must NOT be saved here
if (is_2000_branch($conn, $branch_code)) {
    echo json_encode([
        'success' => false,
        'message' => 'This branch is handled via invoice entry (2000-series). Please use the 2000-invoice screen.'
    ]);
    exit;
}

// 🔹 Find existing record for this firm + branch + month (single-row logic)
$stmt = $conn->prepare("
    SELECT 
        id,
        approval_status,
        provision
    FROM tbl_admin_actual_security_firmwise
    WHERE firm_id = ? 
      AND branch_code = ? 
      AND month_applicable = ?
    LIMIT 1
");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("iss", $firm_id, $branch_code, $month);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {

    // ==========================
    // EXISTING RECORD (UPDATE)
    // ==========================
    $actual_id           = (int)$row['id'];
    $old_status          = strtolower((string)($row['approval_status'] ?? 'pending'));
    $old_provision       = strtolower((string)($row['provision'] ?? 'no'));
    $new_provision       = $provision;

    // If user is converting from provision=yes to provision=no => must go pending (dual control)
    $is_convert_to_actual = ($old_provision === 'yes' && $new_provision === 'no');

    // RULE:
    // - If new provision=yes => auto APPROVED (no dual control)
    // - If new provision=no  => PENDING (dual control)
    $autoApprove = ($new_provision === 'yes');

    if ($autoApprove) {

        $sql = "
            UPDATE tbl_admin_actual_security_firmwise
            SET branch               = ?,
                actual_shifts        = ?,
                total_amount         = ?,
                provision            = ?,
                provision_updated_at = NOW(),
                reason_id            = ?,

                approval_status      = 'approved',
                approved_hris        = ?,
                approved_name        = ?,
                approved_by          = ?,
                approved_at          = NOW(),

                rejected_hris        = NULL,
                rejected_name        = NULL,
                rejected_by          = NULL,
                rejected_at          = NULL,
                rejection_reason     = NULL
            WHERE id = ?
            LIMIT 1
        ";

        $stmt_u = $conn->prepare($sql);
        if (!$stmt_u) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed: ' . $conn->error
            ]);
            exit;
        }

        // types: s i d s i s s s i  => "sidsisssi"
        $stmt_u->bind_param(
            "sidsisssi",
            $branch_name,
            $shifts,
            $amount,
            $provision,
            $reason_id,
            $userHris,
            $userName,
            $userLogin,
            $actual_id
        );

        if ($stmt_u->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Saved as APPROVED (Provision).'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating record: ' . $stmt_u->error
            ]);
        }
        exit;

    } else {

        // new_provision = no  => pending + clear approvals/rejections
        $sql = "
            UPDATE tbl_admin_actual_security_firmwise
            SET branch               = ?,
                actual_shifts        = ?,
                total_amount         = ?,
                provision            = ?,
                provision_updated_at = NOW(),
                reason_id            = ?,

                approval_status      = 'pending',
                approved_hris        = NULL,
                approved_name        = NULL,
                approved_by          = NULL,
                approved_at          = NULL,

                rejected_hris        = NULL,
                rejected_name        = NULL,
                rejected_by          = NULL,
                rejected_at          = NULL,
                rejection_reason     = NULL
            WHERE id = ?
            LIMIT 1
        ";

        $stmt_u = $conn->prepare($sql);
        if (!$stmt_u) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed: ' . $conn->error
            ]);
            exit;
        }

        // types: s i d s i i  => "sidsii"
        $stmt_u->bind_param(
            "sidsii",
            $branch_name,
            $shifts,
            $amount,
            $provision,
            $reason_id,
            $actual_id
        );

        if ($stmt_u->execute()) {
            $msg = $is_convert_to_actual
                ? 'Updated to ACTUAL and sent for approval (Pending).'
                : (($old_status === 'approved')
                    ? 'Updated. Record re-submitted for approval (Pending).'
                    : 'Updated and sent for approval (Pending).');

            echo json_encode([
                'success' => true,
                'message' => $msg
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating record: ' . $stmt_u->error
            ]);
        }
        exit;
    }

} else {

    // ==========================
    // NEW RECORD (INSERT)
    // ==========================

    // sr_number left NULL here, adjust if you use it
    $sr_number = null;

    if ($provision === 'yes') {
        // Provision records are auto-approved
        $sql = "
            INSERT INTO tbl_admin_actual_security_firmwise
            (
                firm_id,
                branch_code,
                branch,
                actual_shifts,
                total_amount,
                month_applicable,
                sr_number,
                provision,
                provision_updated_at,
                reason_id,
                entered_hris,
                entered_name,
                entered_by,
                entered_at,
                approval_status,
                approved_hris,
                approved_name,
                approved_by,
                approved_at
            )
            VALUES
            (?,?,?,?,?,?,?,?, NOW(), ?,?,?,?, NOW(), 'approved', ?,?,?, NOW())
        ";

        $stmt_i = $conn->prepare($sql);
        if (!$stmt_i) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed: ' . $conn->error
            ]);
            exit;
        }

        // types (15): i s s i d s i s i s s s s s s  => "issidsisissssss"
        $stmt_i->bind_param(
            "issidsisissssss",
            $firm_id,
            $branch_code,
            $branch_name,
            $shifts,
            $amount,
            $month,
            $sr_number,
            $provision,
            $reason_id,
            $userHris,
            $userName,
            $userLogin,
            $userHris,
            $userName,
            $userLogin
        );

        if ($stmt_i->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Saved as APPROVED (Provision).'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error inserting record: ' . $stmt_i->error
            ]);
        }
        exit;

    } else {

        // Non-provision records are pending (dual control)
        $sql = "
            INSERT INTO tbl_admin_actual_security_firmwise
            (
                firm_id,
                branch_code,
                branch,
                actual_shifts,
                total_amount,
                month_applicable,
                sr_number,
                provision,
                provision_updated_at,
                reason_id,
                entered_hris,
                entered_name,
                entered_by,
                entered_at,
                approval_status
            )
            VALUES
            (?,?,?,?,?,?,?,?, NOW(), ?,?,?,?, NOW(), 'pending')
        ";

        $stmt_i = $conn->prepare($sql);
        if (!$stmt_i) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed: ' . $conn->error
            ]);
            exit;
        }

        // types (12): i s s i d s i s i s s s  => "issidsisissss"
        $stmt_i->bind_param(
            "issidsisissss",
            $firm_id,
            $branch_code,
            $branch_name,
            $shifts,
            $amount,
            $month,
            $sr_number,
            $provision,
            $reason_id,
            $userHris,
            $userName,
            $userLogin
        );

        if ($stmt_i->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Saved and sent for approval (Pending).'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error inserting record: ' . $stmt_i->error
            ]);
        }
        exit;
    }
}
