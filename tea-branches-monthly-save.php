<?php
// tea-branches-monthly-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$entered_hris = $_SESSION['hris'] ?? 'N/A';
$entered_name = $_SESSION['name'] ?? 'Unknown';

$month            = trim($_POST['month'] ?? '');
$branch_code      = trim($_POST['branch_code'] ?? '');
$branch_name      = trim($_POST['branch_name'] ?? '');
$amount_raw       = str_replace(',', '', trim($_POST['amount'] ?? '0'));
$amount           = (float)$amount_raw;

$provision        = trim($_POST['provision'] ?? 'no');
$provision        = ($provision === 'yes' ? 'yes' : 'no');

$provision_reason = trim($_POST['provision_reason'] ?? '');

if ($month === '' || $branch_code === '' || $branch_name === '' || $amount_raw === '' || !is_numeric($amount_raw) || $amount <= 0) {
  echo json_encode(['success'=>false,'message'=>'⚠️ Please fill all required fields.']);
  exit;
}

$bcEsc = mysqli_real_escape_string($conn, $branch_code);
$moEsc = mysqli_real_escape_string($conn, $month);

// Check existing record
$check = mysqli_query($conn, "
  SELECT id, approval_status, is_provision
  FROM tbl_admin_actual_tea_branches
  WHERE branch_code='{$bcEsc}'
    AND month_applicable='{$moEsc}'
  LIMIT 1
");

$existing_id = null;
$existing_status = '';
$existing_prov = 'no';

if ($check && mysqli_num_rows($check) > 0) {
  $r = mysqli_fetch_assoc($check);
  $existing_id = (int)$r['id'];
  $existing_status = strtolower(trim((string)($r['approval_status'] ?? '')));
  if ($existing_status === '') $existing_status = 'pending';
  $existing_prov = strtolower(trim((string)($r['is_provision'] ?? 'no')));
}

// Water logic: block duplicates if approved/pending AND not provision
if ($existing_id !== null && $existing_prov !== 'yes' && in_array($existing_status, ['approved','pending'], true)) {
  echo json_encode([
    'success'=>false,
    'message'=>"An entry for this branch and month already exists and is {$existing_status}."
  ]);
  exit;
}

// If existing is provision, force provision=no (finalize)
if ($existing_prov === 'yes') {
  $provision = 'no';
}

// If record exists (rejected/deleted/provision) → delete then insert fresh (Water style)
if ($existing_id !== null) {
  mysqli_query($conn, "DELETE FROM tbl_admin_actual_tea_branches WHERE id = {$existing_id} LIMIT 1");
}

// Approval status like Water
$approval_status = ($provision === 'yes') ? 'rejected' : 'pending';

$insert_sql = "
INSERT INTO tbl_admin_actual_tea_branches (
  branch_code, branch, total_amount,
  is_provision, provision_reason, provision_updated_at,
  month_applicable,
  entered_hris, entered_name, entered_by, entered_at,
  approval_status
) VALUES (
  '".mysqli_real_escape_string($conn, $branch_code)."',
  '".mysqli_real_escape_string($conn, $branch_name)."',
  '".mysqli_real_escape_string($conn, (string)$amount)."',
  '".mysqli_real_escape_string($conn, $provision)."',
  '".mysqli_real_escape_string($conn, $provision_reason)."',
  NOW(),
  '".mysqli_real_escape_string($conn, $month)."',
  '".mysqli_real_escape_string($conn, $entered_hris)."',
  '".mysqli_real_escape_string($conn, $entered_name)."',
  '".mysqli_real_escape_string($conn, $entered_name)."',
  NOW(),
  '".mysqli_real_escape_string($conn, $approval_status)."'
)";

if (mysqli_query($conn, $insert_sql)) {
  userlog("💾 Tea Saved | Branch: {$branch_code} | Amount: {$amount} | Provision: {$provision} | Status: {$approval_status}");
  echo json_encode([
    'success'=>true,
    'message'=> ($provision === 'yes')
      ? '⚠️ Provision saved (stored as rejected like Water).'
      : '✅ Record saved successfully (Pending Approval).'
  ]);
} else {
  userlog("❌ Tea Save Failed | Branch: {$branch_code} | Error: " . mysqli_error($conn));
  echo json_encode(['success'=>false,'message'=>'❌ Database error — save failed.']);
}
