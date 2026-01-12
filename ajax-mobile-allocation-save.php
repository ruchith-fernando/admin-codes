<?php
require_once 'connections/connection.php';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Asia/Colombo');

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function is_date_ymd($d){
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

$mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
$hris   = trim($_POST['hris'] ?? '');
$owner  = trim($_POST['owner'] ?? '');
$eff    = trim($_POST['effective_from'] ?? '');

if ($mobile === '' || !preg_match('/^\d{9}$/', $mobile)) {
  echo "<div class='alert alert-danger'>Mobile must be exactly 9 digits (example: 765455585).</div>";
  exit;
}
if ($hris === '') {
  echo "<div class='alert alert-danger'>HRIS is required.</div>";
  exit;
}
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  echo "<div class='alert alert-danger'>Numeric HRIS must be exactly 6 digits (example: 006428). Text HRIS is allowed.</div>";
  exit;
}
if ($eff === '' || !is_date_ymd($eff)) {
  echo "<div class='alert alert-danger'>Effective From must be a valid date (YYYY-MM-DD).</div>";
  exit;
}

$conn->begin_transaction();

try {
  // current active allocation?
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
    $oldId   = (int)$active['id'];
    $oldFrom = $active['effective_from'];

    if ($eff <= $oldFrom) {
      throw new Exception("Effective From must be after existing effective_from ($oldFrom).");
    }

    $closeTo = date('Y-m-d', strtotime($eff . ' -1 day'));

    $upd = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to = ?, status='Inactive'
      WHERE id = ? AND effective_to IS NULL
    ");
    $upd->bind_param("si", $closeTo, $oldId);
    if (!$upd->execute()) throw new Exception("Failed to close previous allocation: ".$upd->error);
    $upd->close();

    $action = 'TRANSFER';
  }

  $ins = $conn->prepare("
    INSERT INTO tbl_admin_mobile_allocations
      (mobile_number, hris_no, owner_name, effective_from, effective_to, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, NULL, 'Active', NOW(), NOW())
  ");
  $ins->bind_param("ssss", $mobile, $hris, $owner, $eff);
  if (!$ins->execute()) throw new Exception("Insert failed: ".$ins->error);
  $newId = $ins->insert_id;
  $ins->close();

  $conn->commit();

  echo "<div class='alert alert-success'>
    ✅ Saved successfully (<b>".esc($action)."</b>)<br>
    ID: <b>".esc($newId)."</b> | Mobile: <b>".esc($mobile)."</b> | HRIS: <b>".esc($hris)."</b> | From: <b>".esc($eff)."</b>
  </div>";

} catch (Throwable $e) {
  $conn->rollback();
  echo "<div class='alert alert-danger'>Save failed: ".esc($e->getMessage())."</div>";
}
