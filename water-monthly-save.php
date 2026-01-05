<?php
// water-monthly-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

/* USER */
$entered_hris = $_SESSION['hris'] ?? 'N/A';
$entered_name = $_SESSION['name'] ?? 'Unknown';

/* INPUTS */
$month       = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');

$type_val       = trim($_POST['type_val'] ?? ''); // optional "id|conn"
$water_type_id  = (int)($_POST['water_type_id'] ?? 0);
$connection_no  = (int)($_POST['connection_no'] ?? 1);

if ($type_val !== '' && strpos($type_val, '|') !== false) {
    [$tid, $cno] = explode('|', $type_val, 2);
    $water_type_id = (int)$tid;
    $connection_no = (int)$cno;
}
if ($connection_no <= 0) $connection_no = 1;

$account_number = trim($_POST['account_number'] ?? '');

$from_date      = trim($_POST['from_date'] ?? '');
$to_date        = trim($_POST['to_date'] ?? '');
$number_of_days = trim($_POST['number_of_days'] ?? '');

$usage_qty      = trim($_POST['usage_qty'] ?? '');
$amount_raw     = str_replace(',', '', trim($_POST['amount'] ?? '0'));
$amount         = (float)$amount_raw;

$provision = strtolower(trim($_POST['provision'] ?? 'no'));
$provision = ($provision === 'yes') ? 'yes' : 'no';

/* VALIDATE */
if (
    $month === '' ||
    $branch_code === '' ||
    $water_type_id === 0 ||
    $from_date === '' ||
    $to_date === '' ||
    $number_of_days === '' ||
    $amount_raw === '' ||
    !is_numeric($amount_raw)
) {
    echo json_encode(['success' => false, 'message' => '⚠️ Please fill all required fields.']);
    exit;
}

/* MASTER SETTINGS */
$bcEsc = mysqli_real_escape_string($conn, $branch_code);

$master_sql = "
    SELECT 
        bw.no_of_machines,
        bw.monthly_charge         AS bw_monthly_charge,
        bw.bottle_rate            AS bw_bottle_rate,
        bw.cooler_rental_rate     AS bw_cooler_rental,
        bw.sscl_percentage        AS bw_sscl,
        bw.vat_percentage         AS bw_vat,
        bw.vendor_id,
        bw.account_number         AS bw_account,
        wt.water_type_name,
        wt.water_type_code
    FROM tbl_admin_branch_water bw
    INNER JOIN tbl_admin_water_types wt
        ON wt.water_type_id = bw.water_type_id
    WHERE bw.branch_code    = '{$bcEsc}'
      AND bw.water_type_id  = '" . (int)$water_type_id . "'
      AND bw.connection_no  = '" . (int)$connection_no . "'
    LIMIT 1
";
$mres = mysqli_query($conn, $master_sql);
$master = $mres ? mysqli_fetch_assoc($mres) : null;

if (!$master) {
    echo json_encode(['success' => false, 'message' => '❌ Branch master settings not found for this water type + connection.']);
    exit;
}

/* MODE */
$mode_source = $master['water_type_code'] ?: $master['water_type_name'] ?: '';
$mode_up = strtoupper($mode_source);

$isMachine = (strpos($mode_up, 'MACH') !== false || strpos($mode_up, 'COOLER') !== false);
$isBottle  = (strpos($mode_up, 'BOTTLE') !== false);
$isTapLine = (strpos($mode_up, 'NWSDB') !== false || strpos($mode_up, 'TAP') !== false || strpos($mode_up, 'LINE') !== false);

/* Tap line: force account number from master if user didn't send */
if ($isTapLine && $account_number === '') {
    $account_number = trim($master['bw_account'] ?? '');
}

/* SETTINGS */
$machines = (int)($master['no_of_machines'] ?? 1);
if ($machines <= 0) $machines = 1;

$monthly_charge = (float)($master['bw_monthly_charge'] ?? 0.0);
$bottle_rate    = (float)($master['bw_bottle_rate'] ?? 0.0);
$cooler_rental  = (float)($master['bw_cooler_rental'] ?? 0.0);

$sscl = (float)($master['bw_sscl'] ?? 0.0);
$vat  = (float)($master['bw_vat']  ?? 0.0);

/* ✅ AUTO CALC ONLY FOR ACTUAL (provision=no) */
if ($provision !== 'yes') {
    if ($isMachine) {
        $base     = $monthly_charge * $machines;
        $sscl_amt = $base * ($sscl / 100.0);
        $with_ssc = $base + $sscl_amt;
        $vat_amt  = $with_ssc * ($vat / 100.0);
        $amount   = $with_ssc + $vat_amt;
    }

    if ($isBottle) {
        $qty = (float)($usage_qty ?? 0);
        $base     = ($bottle_rate * $qty) + $cooler_rental;
        $sscl_amt = $base * ($sscl / 100.0);
        $vat_amt  = ($base + $sscl_amt) * ($vat / 100.0);
        $amount   = $base + $sscl_amt + $vat_amt;
    }
}

/* ---------------------------------------------------------
   DUPLICATE / UPDATE LOGIC
   - Existing ACTUAL (approved/pending) blocks
   - Existing PROVISION can be updated OR converted to actual
--------------------------------------------------------- */
$check_sql = "
    SELECT id, approval_status, is_provision
    FROM tbl_admin_actual_water
    WHERE branch_code      = '" . mysqli_real_escape_string($conn, $branch_code) . "'
      AND water_type_id    = '" . (int)$water_type_id . "'
      AND connection_no    = '" . (int)$connection_no . "'
      AND month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
    LIMIT 1
";
$check_res = mysqli_query($conn, $check_sql);

$existing_id        = null;
$existing_status    = '';
$existing_provision = 'no';

if ($check_res && mysqli_num_rows($check_res) > 0) {
    $r = mysqli_fetch_assoc($check_res);
    $existing_id        = (int)$r['id'];
    $existing_status    = strtolower(trim($r['approval_status'] ?? ''));
    $existing_provision = strtolower(trim($r['is_provision'] ?? 'no'));
}

/* Block if already actual and approved/pending */
if ($existing_id && $existing_provision !== 'yes' && in_array($existing_status, ['approved', 'pending'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'An entry for this branch, type, connection and month already exists and is ' . $existing_status . '.'
    ]);
    exit;
}

/* ---------------------------------------------------------
   APPROVAL STATUS RULE
   - Provision saves: auto-approved (skip dual control)
   - Converting provision -> actual: pending (dual control)
   - New actual saves: pending (dual control)
--------------------------------------------------------- */
$converting_provision_to_actual =
    ($existing_id && $existing_provision === 'yes' && $provision !== 'yes');

$approval_status = ($provision === 'yes' && !$converting_provision_to_actual)
    ? 'approved'
    : 'pending';


if ($existing_id) {
    $upd_sql = "
        UPDATE tbl_admin_actual_water SET
            account_number   = '" . mysqli_real_escape_string($conn, $account_number) . "',
            from_date        = '" . mysqli_real_escape_string($conn, $from_date) . "',
            to_date          = '" . mysqli_real_escape_string($conn, $to_date) . "',
            number_of_days   = '" . mysqli_real_escape_string($conn, $number_of_days) . "',
            usage_qty        = " . ($usage_qty === '' ? "NULL" : "'" . mysqli_real_escape_string($conn, $usage_qty) . "'") . ",
            total_amount     = '" . mysqli_real_escape_string($conn, (string)$amount) . "',
            is_provision     = '" . mysqli_real_escape_string($conn, $provision) . "',
            provision_updated_at = NOW(),
            entered_hris     = '" . mysqli_real_escape_string($conn, $entered_hris) . "',
            entered_name     = '" . mysqli_real_escape_string($conn, $entered_name) . "',
            entered_at       = NOW(),
            approval_status  = '" . mysqli_real_escape_string($conn, $approval_status) . "'
        WHERE id = '" . (int)$existing_id . "'
        LIMIT 1
    ";

    if (mysqli_query($conn, $upd_sql)) {
        userlog("💾 Water Updated | Branch: $branch_code | Type: $water_type_id | Conn: $connection_no | Amount: $amount | Provision: $provision");
        $msg = ($provision === 'yes' && !$converting_provision_to_actual)
    ? '⚠️ Provision saved as APPROVED (dual control skipped).'
    : '✅ Actual saved as PENDING (sent for dual control approval).';

echo json_encode(['success' => true, 'message' => $msg]);

    } else {
        userlog("❌ Update Failed | Branch: $branch_code | Error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => '❌ Database error — update failed.']);
    }
    exit;
}

/* INSERT NEW */
$reference_no = 'WAT-' . date('Ymd-His') . '-' . mt_rand(100, 999);

$insert_sql = "
INSERT INTO tbl_admin_actual_water (
    reference_no,
    month_applicable,
    branch_code, branch,
    water_type_id,
    connection_no,
    account_number,
    from_date, to_date, number_of_days,
    usage_qty, total_amount,
    is_provision, provision_reason, provision_updated_at,
    entered_hris, entered_name, entered_at,
    approval_status
)
VALUES (
    '" . mysqli_real_escape_string($conn, $reference_no) . "',
    '" . mysqli_real_escape_string($conn, $month) . "',
    '" . mysqli_real_escape_string($conn, $branch_code) . "',
    '" . mysqli_real_escape_string($conn, $branch_name) . "',
    '" . (int)$water_type_id . "',
    '" . (int)$connection_no . "',
    '" . mysqli_real_escape_string($conn, $account_number) . "',
    '" . mysqli_real_escape_string($conn, $from_date) . "',
    '" . mysqli_real_escape_string($conn, $to_date) . "',
    '" . mysqli_real_escape_string($conn, $number_of_days) . "',
    " . ($usage_qty === '' ? "NULL" : "'" . mysqli_real_escape_string($conn, $usage_qty) . "'") . ",
    '" . mysqli_real_escape_string($conn, (string)$amount) . "',
    '" . mysqli_real_escape_string($conn, $provision) . "',
    '',
    NOW(),
    '" . mysqli_real_escape_string($conn, $entered_hris) . "',
    '" . mysqli_real_escape_string($conn, $entered_name) . "',
    NOW(),
    '" . mysqli_real_escape_string($conn, $approval_status) . "'
)
";

if (mysqli_query($conn, $insert_sql)) {
    userlog("💾 Water Saved | Branch: $branch_code | Type: $water_type_id | Conn: $connection_no | Amount: $amount | Provision: $provision");
    $msg = ($provision === 'yes')
    ? '⚠️ Provision saved as APPROVED (dual control skipped).'
    : '✅ Actual saved as PENDING (sent for dual control approval).';

    echo json_encode(['success' => true, 'message' => $msg]);

} else {
    userlog("❌ Save Failed | Branch: $branch_code | Error: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => '❌ Database error — save failed.']);
}
