<?php
// ajax-get-existing-record.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');
$firm_id     = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;

file_put_contents(
    'existing-debug.log',
    date("Y-m-d H:i:s") . " | branch_code={$branch_code} | month={$month} | firm_id={$firm_id}\n",
    FILE_APPEND
);

// Helper: is 2000 branch
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

if (empty($branch_code) || empty($month) || !$firm_id) {
    echo json_encode(['exists' => false]);
    exit;
}

// 2000 branches use invoice table, not single monthly record
if (is_2000_branch($conn, $branch_code)) {
    echo json_encode(['exists' => false]);
    exit;
}

// Normal branches – check existing single record
$stmt = $conn->prepare("
    SELECT
        id,
        branch,
        actual_shifts,
        provision,
        total_amount,
        approval_status,
        rejection_reason
    FROM tbl_admin_actual_security_firmwise
    WHERE branch_code = ?
      AND month_applicable = ?
      AND firm_id = ?
    LIMIT 1
");
$stmt->bind_param("ssi", $branch_code, $month, $firm_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $row = $res->fetch_assoc()) {
    echo json_encode([
        'exists'           => true,
        'id'               => (int)$row['id'],
        'branch'           => $row['branch'],
        'shifts'           => $row['actual_shifts'],
        'provision'        => $row['provision'],
        'amount'           => $row['total_amount'],
        'approval_status'  => $row['approval_status'],
        'rejection_reason' => $row['rejection_reason'],
    ]);
} else {
    echo json_encode(['exists' => false]);
}
