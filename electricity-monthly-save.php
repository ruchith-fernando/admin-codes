<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function elog($m){
  if(!is_dir('logs')) @mkdir('logs',0777,true);
  @file_put_contents('logs/electricity.log', "[".date('Y-m-d H:i:s')."] SAVE: $m\n", FILE_APPEND);
}

$month        = isset($_POST['month']) ? trim($_POST['month']) : '';
$branch_code  = isset($_POST['branch_code']) ? trim($_POST['branch_code']) : '';
$branch_name  = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
$account_no   = isset($_POST['account_no']) ? trim($_POST['account_no']) : '';
$bank_paid_to = isset($_POST['bank_paid_to']) ? trim($_POST['bank_paid_to']) : '';
$units_raw    = isset($_POST['units']) ? trim($_POST['units']) : '';
$amount_raw   = isset($_POST['amount']) ? trim($_POST['amount']) : '';
$provision    = isset($_POST['provision']) ? trim($_POST['provision']) : 'no';
$provision_reason = isset($_POST['provision_reason']) ? trim($_POST['provision_reason']) : '';

$bill_from_date = $_POST['bill_from_date'] ?? null;
$bill_to_date   = $_POST['bill_to_date']   ?? null;
$number_of_days = $_POST['number_of_days'] ?? null;
$bill_amount    = $_POST['bill_amount']    ?? null;
$paid_amount    = $_POST['paid_amount']    ?? null;
$cheque_number  = $_POST['cheque_number']  ?? null;
$cheque_date    = $_POST['cheque_date']    ?? null;
$ar_cr          = $_POST['ar_cr']          ?? null;
$cheque_amount  = $_POST['cheque_amount']  ?? null;

elog("POST=".json_encode($_POST));

if ($month === '' || $branch_code === '' || $branch_name === '' || $amount_raw === '') {
  echo json_encode(['success'=>false,'message'=>'Missing required fields (Month, Branch, Amount). Units required when Provision = No.']); exit;
}

$units  = ($units_raw === '' ? '' : str_replace(',', '', $units_raw));
$amount = str_replace(',', '', $amount_raw);

if (!is_numeric($amount) || (float)$amount <= 0) {
  echo json_encode(['success'=>false,'message'=>'Amount must be a number > 0']); exit;
}
if ($provision === 'no') {
  if ($units === '' || !is_numeric($units) || (float)$units <= 0) {
    echo json_encode(['success'=>false,'message'=>'Units must be a number > 0 when finalizing (Provision = No)']); exit;
  }
} else {
  if ($units !== '' && !is_numeric($units)) {
    echo json_encode(['success'=>false,'message'=>'Units must be numeric']); exit;
  }
}

// Refresh branch data from master (server-trust)
$br = mysqli_query($conn, "SELECT branch_name, account_no, bank_paid_to FROM tbl_admin_branch_electricity WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."' LIMIT 1");
if ($br && mysqli_num_rows($br) > 0) {
  $brd = mysqli_fetch_assoc($br);
  $branch_name  = $brd['branch_name'];
  $account_no   = $brd['account_no'];
  $bank_paid_to = $brd['bank_paid_to'];
} else {
  echo json_encode(['success'=>false,'message'=>'Branch code not found in Electricity Branch Master']); exit;
}

// Normalize optional numerics/dates
$bill_amount    = ($bill_amount !== null && $bill_amount !== '' ? (float)$bill_amount : null);
$paid_amount    = ($paid_amount !== null && $paid_amount !== '' ? (float)$paid_amount : null);
$cheque_amount  = ($cheque_amount !== null && $cheque_amount !== '' ? (float)$cheque_amount : null);
$number_of_days = ($number_of_days !== null && $number_of_days !== '' ? (int)$number_of_days : null);

// Existing?
$chk = mysqli_query($conn, "
  SELECT id, is_provision 
  FROM tbl_admin_actual_electricity 
  WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."' 
    AND month_applicable = '".mysqli_real_escape_string($conn,$month)."'
  LIMIT 1
");

if ($chk && mysqli_num_rows($chk) > 0) {
  $ex = mysqli_fetch_assoc($chk);
  if ($ex['is_provision'] === 'yes') {
    // Allow update; if flipping to 'no', enforce units > 0 (already validated above)
    $set_units = ($units === '' ? "actual_units = NULL" : "actual_units = '".mysqli_real_escape_string($conn,$units)."'");
    $flip_ts   = ($provision === 'no' ? " , provision_updated_at = NOW() " : "");
    $sql = "
      UPDATE tbl_admin_actual_electricity SET
        branch = '".mysqli_real_escape_string($conn,$branch_name)."',
        total_amount = '".mysqli_real_escape_string($conn,$amount)."',
        $set_units,
        is_provision = '".mysqli_real_escape_string($conn,$provision)."',
        provision_reason = ".($provision_reason !== '' ? "'".mysqli_real_escape_string($conn,$provision_reason)."'" : "NULL").",
        account_no = '".mysqli_real_escape_string($conn,$account_no)."',
        bank_paid_to = '".mysqli_real_escape_string($conn,$bank_paid_to)."',
        bill_from_date = ".($bill_from_date ? "'".mysqli_real_escape_string($conn,$bill_from_date)."'" : "NULL").",
        bill_to_date   = ".($bill_to_date   ? "'".mysqli_real_escape_string($conn,$bill_to_date)."'"   : "NULL").",
        bill_amount    = ".($bill_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$bill_amount)."'" : "NULL").",
        number_of_days = ".($number_of_days !== null ? "'".mysqli_real_escape_string($conn,(string)$number_of_days)."'" : "NULL").",
        paid_amount    = ".($paid_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$paid_amount)."'" : "NULL").",
        cheque_number  = ".($cheque_number ? "'".mysqli_real_escape_string($conn,$cheque_number)."'" : "NULL").",
        cheque_date    = ".($cheque_date   ? "'".mysqli_real_escape_string($conn,$cheque_date)."'"   : "NULL").",
        ar_cr          = ".($ar_cr ? "'".mysqli_real_escape_string($conn,$ar_cr)."'" : "NULL").",
        cheque_amount  = ".($cheque_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$cheque_amount)."'" : "NULL")."
        $flip_ts
      WHERE id = ".$ex['id']." LIMIT 1
    ";
    elog("UPDATE_SQL=".$sql);
    $ok = mysqli_query($conn, $sql);
    if ($ok) {
      $finalized = ($provision === 'no') ? " (finalized)" : " (provisional)";
      $msg = "Updated: $branch_name ($branch_code) — ".($units===''?'—':$units.' units').", Rs. ".number_format((float)$amount,2)." for $month$finalized";
      echo json_encode(['success'=>true,'message'=>$msg]);

      // ✅ Add userlog
      try {
        require_once 'includes/userlog.php';
        $hris = $_SESSION['hris'] ?? 'UNKNOWN';
        $username = $_SESSION['name'] ?? 'SYSTEM';
        userlog("✅ $username ($hris) updated electricity record: $msg");
      } catch (Throwable $e) {}
    } else {
      elog("ERR=".mysqli_error($conn));
      echo json_encode(['success'=>false,'message'=>'DB Error: '.mysqli_error($conn)]);
    }
    exit;

  } else {
    echo json_encode(['success'=>false,'message'=>"Record already finalized for $branch_code in $month (locked)"]); exit;
  }
}

// Insert new
$sql = "
INSERT INTO tbl_admin_actual_electricity
  (branch_code, branch, actual_units, total_amount, month_applicable, 
   is_provision, provision_reason, account_no, bank_paid_to,
   bill_from_date, bill_to_date, bill_amount, number_of_days, paid_amount, 
   cheque_number, cheque_date, ar_cr, cheque_amount)
VALUES
  ('".mysqli_real_escape_string($conn,$branch_code)."',
   '".mysqli_real_escape_string($conn,$branch_name)."',
   ".($units === '' ? "NULL" : "'".mysqli_real_escape_string($conn,$units)."'").",
   '".mysqli_real_escape_string($conn,$amount)."',
   '".mysqli_real_escape_string($conn,$month)."',
   '".mysqli_real_escape_string($conn,$provision)."',
   ".($provision_reason !== '' ? "'".mysqli_real_escape_string($conn,$provision_reason)."'" : "NULL").",
   '".mysqli_real_escape_string($conn,$account_no)."',
   '".mysqli_real_escape_string($conn,$bank_paid_to)."',
   ".($bill_from_date ? "'".mysqli_real_escape_string($conn,$bill_from_date)."'" : "NULL").",
   ".($bill_to_date   ? "'".mysqli_real_escape_string($conn,$bill_to_date)."'"   : "NULL").",
   ".($bill_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$bill_amount)."'" : "NULL").",
   ".($number_of_days !== null ? "'".mysqli_real_escape_string($conn,(string)$number_of_days)."'" : "NULL").",
   ".($paid_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$paid_amount)."'" : "NULL").",
   ".($cheque_number ? "'".mysqli_real_escape_string($conn,$cheque_number)."'" : "NULL").",
   ".($cheque_date   ? "'".mysqli_real_escape_string($conn,$cheque_date)."'"   : "NULL").",
   ".($ar_cr ? "'".mysqli_real_escape_string($conn,$ar_cr)."'" : "NULL").",
   ".($cheque_amount !== null ? "'".mysqli_real_escape_string($conn,(string)$cheque_amount)."'" : "NULL")."
  )
";
elog("INSERT_SQL=".$sql);
$ok = mysqli_query($conn, $sql);

if ($ok) {
  $tail = ($provision === 'yes' ? " (provisional)" : "");
  $msg = "Saved: $branch_name ($branch_code) — ".($units===''?'—':$units.' units').", Rs. ".number_format((float)$amount,2)." for $month$tail";
  echo json_encode(['success'=>true,'message'=>$msg]);

  // ✅ Add userlog
  try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? 'SYSTEM';
    userlog("✅ $username ($hris) added new electricity record: $msg");
  } catch (Throwable $e) {}
} else {
  elog("ERR=".mysqli_error($conn));
  echo json_encode(['success'=>false,'message'=>'DB Error: '.mysqli_error($conn)]);
}
