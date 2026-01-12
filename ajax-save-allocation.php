<?php
// ajax-save-allocation.php  (DROP-IN)
// - Mobile stored in DB as 9 digits (e.g. 765455585) ✅
// - Accepts input like 0765455585 / +94765455585 / 94765455585 -> saves as 765455585 ✅
// - HRIS: if numeric -> MUST be exactly 6 digits (006428). If non-numeric (jbvbd) -> allowed ✅
// - If mobile already has an active open allocation -> TRANSFER: closes old record day before new effective_from ✅
// - If not active -> NEW allocation ✅

require_once 'connections/connection.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function respond($ok, $arr = []) {
  echo json_encode(array_merge(['ok' => $ok], $arr));
  exit;
}

function normalize_mobile_db($input) {
  $m = preg_replace('/\D+/', '', $input ?? ''); // digits only
  if ($m === '') return '';

  // 94XXXXXXXXX / +94XXXXXXXXX -> XXXXXXXXX
  if (strpos($m, '94') === 0) {
    $m = substr($m, 2);
  }

  // 0XXXXXXXXX -> XXXXXXXXX
  if (strlen($m) === 10 && $m[0] === '0') {
    $m = substr($m, 1);
  }

  return $m; // should be 9 digits for your DB
}

function is_valid_date($d) {
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

$mobile_raw = $_POST['mobile_number'] ?? '';
$hris       = trim($_POST['hris_no'] ?? '');
$owner      = trim($_POST['owner_name'] ?? '');
$eff        = trim($_POST['effective_from'] ?? '');

$mobile = normalize_mobile_db($mobile_raw);

if ($mobile === '' || $hris === '' || $eff === '') {
  respond(false, ['error' => 'mobile_number, hris_no, effective_from are required']);
}

if (!preg_match('/^\d{9}$/', $mobile)) {
  respond(false, ['error' => 'Mobile must be 9 digits (stored format). Example: 765455585']);
}

if (!is_valid_date($eff)) {
  respond(false, ['error' => 'effective_from must be a valid date (YYYY-MM-DD)']);
}

// HRIS rule you asked:
// - If numeric => must be exactly 6 digits
// - If not numeric => allow anything (jbvbd etc.)
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  respond(false, ['error' => 'Numeric HRIS must be exactly 6 digits (example: 006428). Non-numeric is allowed.']);
}

/* If owner empty and HRIS is numeric 6 digits, try derive from employee table (optional) */
if ($owner === '' && preg_match('/^\d{6}$/', $hris)) {
  $emp_stmt = $conn->prepare("
    SELECT name_of_employee
    FROM tbl_admin_employee_details
    WHERE TRIM(hris)=?
    LIMIT 1
  ");
  if ($emp_stmt) {
    $emp_stmt->bind_param("s", $hris);
    $emp_stmt->execute();
    $emp_res = $emp_stmt->get_result();
    if ($r = $emp_res->fetch_assoc()) {
      $owner = $r['name_of_employee'] ?? '';
    }
    $emp_stmt->close();
  }
}

$conn->begin_transaction();

try {
  // Check active allocation for this mobile (open record)
  $cur = $conn->prepare("
    SELECT id, effective_from
    FROM tbl_admin_mobile_allocations
    WHERE TRIM(mobile_number)=?
      AND status='Active'
      AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  if (!$cur) throw new Exception("Prepare failed (cur): " . $conn->error);

  $cur->bind_param("s", $mobile);
  $cur->execute();
  $activeRow = $cur->get_result()->fetch_assoc();
  $cur->close();

  $action = 'NEW';

  if ($activeRow) {
    // Transfer: close old allocation the day before new effective_from
    $old_id = (int)$activeRow['id'];
    $old_eff_from = $activeRow['effective_from'];

    if ($eff <= $old_eff_from) {
      throw new Exception("Transfer effective_from must be after existing effective_from ($old_eff_from).");
    }

    $close_to = date('Y-m-d', strtotime($eff . ' -1 day'));

    $upd = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to = ?, status='Inactive'
      WHERE id = ? AND effective_to IS NULL
    ");
    if (!$upd) throw new Exception("Prepare failed (upd): " . $conn->error);

    $upd->bind_param("si", $close_to, $old_id);
    if (!$upd->execute()) {
      throw new Exception("Update failed: " . $upd->error);
    }
    $upd->close();

    $action = 'TRANSFER';
  }

  // Insert new active allocation
  $ins = $conn->prepare("
    INSERT INTO tbl_admin_mobile_allocations
      (mobile_number, hris_no, owner_name, effective_from, effective_to, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, NULL, 'Active', NOW(), NOW())
  ");
  if (!$ins) throw new Exception("Prepare failed (ins): " . $conn->error);

  $ins->bind_param("ssss", $mobile, $hris, $owner, $eff);
  if (!$ins->execute()) {
    throw new Exception("Insert failed: " . $ins->error);
  }
  $new_id = $ins->insert_id;
  $ins->close();

  $conn->commit();

  respond(true, [
    'action' => $action,
    'id' => $new_id,
    'mobile_saved' => $mobile,
    'message' => ($action === 'TRANSFER')
      ? "✅ Transfer completed. Previous allocation closed and new allocation saved."
      : "✅ New allocation saved."
  ]);

} catch (Throwable $e) {
  $conn->rollback();
  respond(false, ['error' => $e->getMessage()]);
}
