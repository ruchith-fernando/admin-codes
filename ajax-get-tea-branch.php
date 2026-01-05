<?php
// ajax-get-tea-branch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($branch_code === '' || $month === '') {
  echo json_encode(['success'=>false,'message'=>'Missing branch/month.']);
  exit;
}

$bcEsc = mysqli_real_escape_string($conn, $branch_code);
$moEsc = mysqli_real_escape_string($conn, $month);

// 1) Get branch name from master
$branch_name = '';
$r = mysqli_query($conn, "
  SELECT branch_name
  FROM tbl_admin_branch_tea_branches
  WHERE branch_code = '{$bcEsc}'
  LIMIT 1
");
if ($r && mysqli_num_rows($r) > 0) {
  $row = mysqli_fetch_assoc($r);
  $branch_name = trim((string)$row['branch_name']);
}

if ($branch_name === '') {
  echo json_encode(['success'=>false,'message'=>'Branch not found.','branch_name'=>'']);
  exit;
}

// 2) Check existing record for this branch+month
$ex = mysqli_query($conn, "
  SELECT id, approval_status, is_provision
  FROM tbl_admin_actual_tea_branches
  WHERE branch_code='{$bcEsc}'
    AND month_applicable='{$moEsc}'
  LIMIT 1
");

if ($ex && mysqli_num_rows($ex) > 0) {
  $e = mysqli_fetch_assoc($ex);

  $status = strtolower(trim((string)($e['approval_status'] ?? '')));
  if ($status === '') $status = 'pending'; // treat null/empty as pending

  $is_prov = strtolower(trim((string)($e['is_provision'] ?? 'no')));

  // Water logic: block if approved or pending AND NOT provision
  if ($is_prov !== 'yes' && in_array($status, ['approved','pending'], true)) {
    $msg = ($status === 'approved')
      ? "An entry already exists and is APPROVED for {$month}. You cannot enter again."
      : "An entry already exists and is PENDING approval for {$month}. You cannot enter again.";
    echo json_encode([
      'success' => false,
      'branch_name' => $branch_name,
      'message' => $msg
    ]);
    exit;
  }

  // Allow if rejected/deleted OR provision=yes
  $notice = '';
  if ($is_prov === 'yes') {
    $notice = "A PROVISION entry exists for this branch/month. Saving now will finalize it (provision will be forced to No).";
  } elseif (in_array($status, ['rejected','deleted'], true)) {
    $notice = "A previous entry was {$status}. You can enter again (will go pending).";
  }

  echo json_encode([
    'success' => true,
    'branch_name' => $branch_name,
    'notice' => $notice
  ]);
  exit;
}

// No existing record
echo json_encode([
  'success' => true,
  'branch_name' => $branch_name
]);
