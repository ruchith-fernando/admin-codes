<?php
// branch-water-monthly-save.php — FULL NEW DROP-IN VERSION
// Uses new reference format: WTR-YYYY-######## (no branch involved)
// Serial resets yearly, separate per utility

require_once 'connections/connection.php';
require_once 'includes/helpers/reference-helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

/* ============================================================
   SESSION USER
============================================================ */
$entered_hris = $_SESSION['hris'] ?? 'N/A';
$entered_name = $_SESSION['name'] ?? 'Unknown';

/* ============================================================
   POST DATA
============================================================ */
$month          = trim($_POST['month'] ?? '');
$branch_code    = trim($_POST['branch_code'] ?? '');
$branch_name    = trim($_POST['branch_name'] ?? '');
$water_type     = trim($_POST['water_type'] ?? '');
$account_number = trim($_POST['account_number'] ?? '');

$from_date      = trim($_POST['from_date'] ?? '');
$to_date        = trim($_POST['to_date'] ?? '');
$number_of_days = trim($_POST['number_of_days'] ?? '');
$usage_qty      = trim($_POST['usage_qty'] ?? '');
$amount_raw     = str_replace(',', '', trim($_POST['amount'] ?? '0'));
$amount         = (float)$amount_raw;

/* ============================================================
   VALIDATION
============================================================ */
if (!$month || !$branch_code || !$branch_name || !$water_type || !$from_date || !$to_date || !$number_of_days) {
    echo json_encode([
        'success' => false,
        'message' => '⚠️ Please fill all required fields.'
    ]);
    exit;
}

/* ============================================================
   CHECK IF ENTRY ALREADY EXISTS
============================================================ */
$chk = mysqli_query($conn, "SELECT id, approval_status
        FROM tbl_admin_actual_water
        WHERE branch_code='$branch_code'
        AND month_applicable='$month'
        LIMIT 1");

if ($chk && mysqli_num_rows($chk) > 0) {
    $r = mysqli_fetch_assoc($chk);
    $status = strtolower($r['approval_status']);

    $msg = match($status) {
        'approved' => '❌ Entry already approved for this month. Contact Admin to modify.',
        'pending'  => '⏳ Entry already exists and is still pending approval.',
        'rejected' => '⚠️ A rejected entry exists. Contact Admin if you need to re-submit.',
        'deleted'  => '⚠️ A deleted entry exists. Contact Admin to restore or reset.',
        default    => "⚠️ An entry already exists (Status: $status)."
    };

    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

/* ============================================================
   LOAD MASTER SETTINGS
============================================================ */
$m = mysqli_query($conn, "SELECT * FROM tbl_admin_branch_water
        WHERE branch_code='$branch_code' LIMIT 1");
$master = mysqli_fetch_assoc($m);

if (!$master) {
    echo json_encode(['success' => false, 'message' => '❌ Branch master settings not found.']);
    exit;
}

/* ============================================================
   MACHINE CALCULATION
============================================================ */
if ($water_type === 'MACHINE') {
    $machines = max(1, (int)$master['no_of_machines']);
    $monthly_charge = (float)$master['monthly_charge'];
    $amount = $monthly_charge * $machines;
}

/* ============================================================
   BOTTLE CALCULATION
============================================================ */
if ($water_type === 'BOTTLE') {
    $rate   = (float)$master['bottle_rate'];
    $rental = (float)$master['cooler_rental_rate'];
    $sscl   = (float)$master['sscl_percentage'];
    $vat    = (float)$master['vat_percentage'];
    $qty    = (float)$usage_qty;

    $subtotal = ($rate * $qty) + $rental;
    $sscl_amt = $subtotal * ($sscl / 100);
    $vat_amt  = ($subtotal + $sscl_amt) * ($vat / 100);
    $amount = $subtotal + $sscl_amt + $vat_amt;
}

/* ============================================================
   GENERATE NEW REFERENCE NO (WTR-YYYY-########)
============================================================ */
$reference_no = generate_reference_no($conn, 'WTR');

/* ============================================================
   INSERT RECORD
============================================================ */
$insert_sql = "INSERT INTO tbl_admin_actual_water (
        reference_no, month_applicable, branch_code, branch, water_type, account_number,
        from_date, to_date, number_of_days, usage_qty, total_amount,
        entered_hris, entered_name, entered_at, approval_status
    ) VALUES (
        '$reference_no', '$month', '$branch_code', '$branch_name', '$water_type', '$account_number',
        '$from_date', '$to_date', '$number_of_days', " . ($usage_qty ? "'$usage_qty'" : "NULL") . ",
        '$amount', '$entered_hris', '$entered_name', NOW(), 'approved'
    )";

if (!mysqli_query($conn, $insert_sql)) {
    echo json_encode(['success' => false, 'message' => '❌ Failed to save entry. DB Error.']);
    exit;
}

/* ============================================================
   LOG ENTRY
============================================================ */
mysqli_query($conn, "INSERT INTO tbl_admin_utility_reference_log (
        reference_no, utility_code, branch_code, branch_details, month_applicable,
        entered_by_hris, entered_by_name, entered_at
    ) VALUES (
        '$reference_no', 'WTR', '$branch_code', '$branch_name', '$month',
        '$entered_hris', '$entered_name', NOW()
    )");

echo json_encode([
    'success' => true,
    'message' => "✅ Saved successfully. Ref: <b>$reference_no</b>"
]);
?>
