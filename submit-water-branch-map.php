<?php
// submit-water-branch-map.php (multi-connection support)
include 'connections/connection.php';
require_once 'includes/userlog.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$branch_code = trim($_POST['branch_code'] ?? '');
$hasArr      = $_POST['has'] ?? [];              // has[wtid][conn] = 1
$vendorArr   = $_POST['vendor_id'] ?? [];        // vendor_id[wtid][conn]
$acctArr     = $_POST['account_number'] ?? [];   // account_number[wtid][conn]
$machinesArr = $_POST['no_of_machines'] ?? [];   // no_of_machines[wtid][conn]

if ($branch_code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Branch code is required.']);
    exit;
}

// Resolve branch_name
$bcEsc = mysqli_real_escape_string($conn, $branch_code);
$bnRes = mysqli_query(
    $conn,
    "SELECT branch_name FROM tbl_admin_branches WHERE branch_code = '{$bcEsc}' LIMIT 1"
);
$branch_name = '';
if ($bnRes && $row = mysqli_fetch_assoc($bnRes)) {
    $branch_name = $row['branch_name'] ?? '';
}
if ($branch_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Branch not found in branch master.']);
    exit;
}

// Load valid active water_type_ids
$waterTypes = [];
$wtRes = mysqli_query(
    $conn,
    "SELECT water_type_id FROM tbl_admin_water_types WHERE is_active = 1"
);
if ($wtRes) {
    while ($row = mysqli_fetch_assoc($wtRes)) {
        $waterTypes[] = (int)$row['water_type_id'];
    }
}
if (!$waterTypes) {
    echo json_encode(['status' => 'error', 'message' => 'No active water types to map.']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Load existing connections for this branch (so we can delete missing ones)
    $existingConns = []; // [wtid] => [connNo, connNo...]
    $stmtLoad = $conn->prepare("
        SELECT water_type_id, connection_no
        FROM tbl_admin_branch_water
        WHERE branch_code = ?
    ");
    $stmtLoad->bind_param('s', $branch_code);
    $stmtLoad->execute();
    $rs = $stmtLoad->get_result();
    while ($r = $rs->fetch_assoc()) {
        $wtid = (int)($r['water_type_id'] ?? 0);
        $cno  = (int)($r['connection_no'] ?? 1);
        if ($wtid > 0) {
            $existingConns[$wtid] ??= [];
            $existingConns[$wtid][] = $cno;
        }
    }
    foreach ($existingConns as $wtid => $list) {
        $existingConns[$wtid] = array_values(array_unique($list));
    }

    // Prepare UPSERT (store vendor/machines as NULL when empty)
    $stmtUpsert = $conn->prepare("
        INSERT INTO tbl_admin_branch_water
            (branch_code, branch_name, water_type_id, connection_no, vendor_id, no_of_machines, account_number)
        VALUES
            (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)
        ON DUPLICATE KEY UPDATE
            branch_name    = VALUES(branch_name),
            vendor_id      = VALUES(vendor_id),
            no_of_machines = VALUES(no_of_machines),
            account_number = VALUES(account_number)
    ");

    // Prepare DELETE
    $stmtDelete = $conn->prepare("
        DELETE FROM tbl_admin_branch_water
        WHERE branch_code = ? AND water_type_id = ? AND connection_no = ?
    ");

    foreach ($waterTypes as $wtid) {
        // Active connections = only CHECKED checkboxes get posted
        $active = [];
        if (isset($hasArr[$wtid]) && is_array($hasArr[$wtid])) {
            $active = array_map('intval', array_keys($hasArr[$wtid]));
            $active = array_values(array_unique($active));
            sort($active);
        }

        $existing = $existingConns[$wtid] ?? [];

        // 1) delete any existing conn that is NOT in posted active list
        foreach ($existing as $connNo) {
            if (!in_array((int)$connNo, $active, true)) {
                $c = (int)$connNo;
                $stmtDelete->bind_param('sii', $branch_code, $wtid, $c);
                $stmtDelete->execute();
            }
        }

        // 2) upsert active conns
        foreach ($active as $connNo) {
            $connNo = (int)$connNo;

            $vendor_id = $vendorArr[$wtid][$connNo] ?? '';
            $acct      = $acctArr[$wtid][$connNo] ?? '';
            $machines  = $machinesArr[$wtid][$connNo] ?? '';

            // bind all as strings where NULLIF is used
            $vendor_id = is_null($vendor_id) ? '' : trim((string)$vendor_id);
            $acct      = is_null($acct) ? '' : trim((string)$acct);
            $machines  = is_null($machines) ? '' : trim((string)$machines);

            $stmtUpsert->bind_param(
                'ssiisss',
                $branch_code,
                $branch_name,
                $wtid,
                $connNo,
                $vendor_id,
                $machines,
                $acct
            );
            $stmtUpsert->execute();
        }
    }

    mysqli_commit($conn);

    try {
        $username = $_SESSION['name'] ?? 'SYSTEM';
        $hris     = $_SESSION['hris'] ?? 'UNKNOWN';
        userlog("✅ $username ($hris) updated branch water mapping for {$branch_code}", [
            'branch_code' => $branch_code,
            'branch_name' => $branch_name,
        ]);
    } catch (Throwable $e) {}

    echo json_encode([
        'status'  => 'success',
        'message' => 'Branch water mapping saved for branch ' .
                     htmlspecialchars($branch_code . ' - ' . $branch_name)
    ]);
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);

    try {
        userlog('Branch water mapping ERROR', [
            'branch_code' => $branch_code,
            'error'       => $e->getMessage()
        ]);
    } catch (Throwable $e2) {}

    echo json_encode([
        'status'  => 'error',
        'message' => 'Error saving mapping: ' . htmlspecialchars($e->getMessage())
    ]);
    exit;
}
