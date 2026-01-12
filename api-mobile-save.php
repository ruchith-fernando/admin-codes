<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function out($ok, $arr=[]){ echo json_encode(array_merge(['ok'=>$ok], $arr)); exit; }
function is_date($d){ $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }

$mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
$hris   = trim($_POST['hris'] ?? '');
$owner  = trim($_POST['owner'] ?? '');
$eff    = trim($_POST['effective_from'] ?? '');

if ($mobile === '' || $hris === '' || $eff === '') out(false, ['error'=>'Mobile, HRIS, Effective From are required']);
if (!preg_match('/^\d{9}$/', $mobile)) out(false, ['error'=>'Mobile must be exactly 9 digits']);
if (!is_date($eff)) out(false, ['error'=>'Effective From must be YYYY-MM-DD']);

// HRIS rule: numeric must be 6 digits; text allowed
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  out(false, ['error'=>'Numeric HRIS must be exactly 6 digits (example: 006428). Text is allowed.']);
}

$conn->begin_transaction();

try {
  // Find current active allocation for mobile
  $cur = $conn->prepare("
    SELECT id, effective_from
    FROM tbl_admin_mobile_allocations
    WHERE mobile_number = ?
      AND status='Active'
      AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $cur->bind_param("s", $mobile);
  $cur->execute();
  $active = $cur->get_result()->fetch_assoc();
  $cur->close();

  $action = 'NEW';

  if ($active) {
    $oldId = (int)$active['id'];
    $oldFrom = $active['effective_from'];

    if ($eff <= $oldFrom) {
      throw new Exception("Effective From must be after current allocation effective_from ($oldFrom).");
    }

    $closeTo = date('Y-m-d', strtotime($eff . ' -1 day'));

    $upd = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to = ?, status='Inactive'
      WHERE id = ? AND effective_to IS NULL
    ");
    $upd->bind_param("si", $closeTo, $oldId);
    if (!$upd->execute()) throw new Exception("Failed to close previous allocation: " . $upd->error);
    $upd->close();

    $action = 'TRANSFER';
  }

  // Insert new allocation
  $ins = $conn->prepare("
    INSERT INTO tbl_admin_mobile_allocations
      (mobile_number, hris_no, owner_name, effective_from, effective_to, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, NULL, 'Active', NOW(), NOW())
  ");
  $ins->bind_param("ssss", $mobile, $hris, $owner, $eff);
  if (!$ins->execute()) throw new Exception("Insert failed: " . $ins->error);
  $newId = $ins->insert_id;
  $ins->close();

  $conn->commit();

  out(true, [
    'action' => $action,
    'id' => $newId,
    'message' => ($action === 'TRANSFER')
      ? "Transfer done. Previous allocation closed and new saved."
      : "New allocation saved."
  ]);

} catch (Throwable $e) {
  $conn->rollback();
  out(false, ['error'=>$e->getMessage()]);
}
