<?php
// ajax-get-existing-tea-branches.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');
$current_hris = trim($_SESSION['hris'] ?? '');

if ($month === '' || $branch_code === '') {
  echo json_encode(['status'=>'error','message'=>'Missing fields']);
  exit;
}

// budget year from month (June 2025 -> 2025)
$budget_year = date("Y", strtotime("1 " . $month));

// 1) Budget branch name
$budget_branch = '';
$budget_sql = "
  SELECT branch_name
  FROM tbl_admin_budget_tea_branches
  WHERE budget_year = '" . mysqli_real_escape_string($conn,$budget_year) . "'
    AND branch_code = '" . mysqli_real_escape_string($conn,$branch_code) . "'
  LIMIT 1
";
$budget_res = mysqli_query($conn, $budget_sql);
if ($budget_res && mysqli_num_rows($budget_res) > 0) {
  $b = mysqli_fetch_assoc($budget_res);
  $budget_branch = trim((string)$b['branch_name']);
}

// 2) Master branch name
$master_branch = '';
$master_sql = "
  SELECT branch_name
  FROM tbl_admin_branch_tea_branches
  WHERE branch_code = '" . mysqli_real_escape_string($conn,$branch_code) . "'
  LIMIT 1
";
$master_res = mysqli_query($conn, $master_sql);
if ($master_res && mysqli_num_rows($master_res) > 0) {
  $m = mysqli_fetch_assoc($master_res);
  $master_branch = trim((string)$m['branch_name']);
}

// 3) Actual record (any status)
$sql = "
  SELECT *
  FROM tbl_admin_actual_tea_branches
  WHERE month_applicable = '" . mysqli_real_escape_string($conn,$month) . "'
    AND branch_code = '" . mysqli_real_escape_string($conn,$branch_code) . "'
  LIMIT 1
";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
  $row = mysqli_fetch_assoc($res);

  $approval_status = strtolower(trim((string)($row['approval_status'] ?? 'pending')));
  $entered_hris = trim((string)($row['entered_hris'] ?? ''));

  // prefer budget name, else actual.branch, else master
  $branch_name = $budget_branch !== '' ? $budget_branch : (trim((string)$row['branch']) !== '' ? $row['branch'] : $master_branch);

  // can edit?
  $can_edit = true;
  $lock_message = '';

  if ($approval_status === 'approved') {
    $can_edit = false;
    $lock_message = "Approved record — locked.";
  } else {
    // maker-only edit (except legacy empty entered_hris)
    if ($entered_hris !== '' && $current_hris !== '' && $entered_hris !== $current_hris) {
      $can_edit = false;
      $lock_message = "Pending/Rejected entry by another user — you cannot edit.";
    }
  }

  echo json_encode([
    'exists' => true,
    'branch' => $branch_name,
    'total_amount' => $row['total_amount'],
    'is_provision' => $row['is_provision'],
    'provision_reason' => $row['provision_reason'],
    'approval_status' => $approval_status,
    'entered_hris' => $entered_hris,
    'can_edit' => $can_edit,
    'lock_message' => $lock_message
  ]);
  exit;
}

// Not exists → return budget/master name if available
echo json_encode([
  'exists' => false,
  'branch' => ($budget_branch !== '' ? $budget_branch : $master_branch),
  'total_amount' => '',
  'is_provision' => 'no',
  'provision_reason' => ''
]);
