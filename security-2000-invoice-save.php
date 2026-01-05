<?php
// security-2000-invoice-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

function jerr($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function ok($msg) {
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

/**
 * Read a POST value as a scalar string.
 * If array is posted (field[] or multi select), reject to prevent Array->String warnings.
 */
function post_str($key, $required = true) {
    if (!isset($_POST[$key])) {
        if ($required) jerr("Missing field: $key");
        return '';
    }
    if (is_array($_POST[$key])) {
        jerr("Invalid field: $key (array posted)");
    }
    return trim((string)$_POST[$key]);
}

function session_scalar($key) {
    $v = $_SESSION[$key] ?? null;
    if (is_array($v)) return null;
    if ($v === null) return null;
    $v = trim((string)$v);
    return ($v === '') ? null : $v;
}

$firm_id     = isset($_POST['firm_id']) && !is_array($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;
$month       = post_str('month');
$branch_code = post_str('branch_code');
$branch_name = post_str('branch_name');
$invoice_no  = post_str('invoice_no');
$provision   = strtolower(post_str('provision', false) ?: 'no');
$amount_raw  = post_str('amount');

$entered_hris = session_scalar('hris');
$entered_name = session_scalar('name');
$entered_by   = session_scalar('username') ?? session_scalar('user');

// normalize
$provision = ($provision === 'yes') ? 'yes' : 'no';
$amount_raw = str_replace(',', '', $amount_raw);

// validate basics
if ($firm_id <= 0 || $month === '' || $branch_code === '' || $branch_name === '' || $invoice_no === '' || $amount_raw === '') {
    jerr("Fill all fields correctly (firm, month, branch, invoice no, amount).");
}

// validate amount: allow negative, up to 2 decimals
if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $amount_raw)) {
    jerr("Amount is invalid. Use format like 10000.00 or -10000.00");
}

$amount = (float)$amount_raw;
if ($amount == 0) {
    jerr("Amount cannot be 0. Use a positive amount, or negative for reduction.");
}

// if amount is negative, force provision yes (adjustment)
$forcedProvisionMsg = '';
if ($amount < 0 && $provision !== 'yes') {
    $provision = 'yes';
    $forcedProvisionMsg = ' (Provision auto-set to YES for negative adjustment)';
}

// ✅ server-side duplicate guard
$dupSql = "
    SELECT id
    FROM tbl_admin_actual_security_2000_invoices
    WHERE firm_id = ?
      AND branch_code = ?
      AND month_applicable = ?
      AND invoice_no = ?
      AND amount = ?
      AND provision = ?
      AND COALESCE(approval_status,'pending') <> 'deleted'
      AND (entered_hris <=> ?)
      AND entered_at >= (NOW() - INTERVAL 20 SECOND)
    ORDER BY id DESC
    LIMIT 1
";
$dupStmt = mysqli_prepare($conn, $dupSql);
if ($dupStmt) {
    mysqli_stmt_bind_param(
        $dupStmt,
        "isssdss",
        $firm_id,
        $branch_code,
        $month,
        $invoice_no,
        $amount,
        $provision,
        $entered_hris
    );
    mysqli_stmt_execute($dupStmt);
    $dupRes = mysqli_stmt_get_result($dupStmt);
    if ($dupRes && mysqli_fetch_assoc($dupRes)) {
        ok("Duplicate prevented — same invoice was already saved just now{$forcedProvisionMsg}.");
    }
    mysqli_stmt_close($dupStmt);
}

// insert
$insSql = "
    INSERT INTO tbl_admin_actual_security_2000_invoices
    (firm_id, branch_code, branch, month_applicable, invoice_no, amount, provision, entered_hris, entered_name, entered_by, approval_status, entered_at)
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
";
$stmt = mysqli_prepare($conn, $insSql);
if (!$stmt) {
    jerr("DB prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "issssdssss",
    $firm_id,
    $branch_code,
    $branch_name,
    $month,
    $invoice_no,
    $amount,
    $provision,
    $entered_hris,
    $entered_name,
    $entered_by
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    jerr("DB insert failed: " . mysqli_error($conn));
}
mysqli_stmt_close($stmt);

// optional log (safe)
userlog("✅ 2000 Invoice saved | firm_id=$firm_id | month=$month | branch=$branch_code | invoice=$invoice_no | amount=$amount | provision=$provision");

ok("Invoice saved successfully and sent for approval{$forcedProvisionMsg}.");
