<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$idsRaw = trim($_POST['ids'] ?? '');
if ($idsRaw === '') {
  echo json_encode(['success'=>false,'message'=>'No IDs provided']);
  exit;
}

$ids = array_filter(array_map('intval', explode(',', $idsRaw)));
$ids = array_values(array_unique($ids));

if (count($ids) === 0) {
  echo json_encode(['success'=>false,'message'=>'No valid IDs provided']);
  exit;
}

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
$current_name = trim((string)($_SESSION['name'] ?? ''));
$current_user = trim((string)($_SESSION['username'] ?? $current_name));

$approved = 0;
$skipped_own = 0;
$skipped_not_pending = 0;

$conn->begin_transaction();

try {
  $chk = $conn->prepare("SELECT entered_hris, approval_status FROM tbl_admin_actual_security_2000_invoices WHERE id=? LIMIT 1");
  $upd = $conn->prepare("
    UPDATE tbl_admin_actual_security_2000_invoices
    SET approval_status='approved',
        approved_hris=?,
        approved_name=?,
        approved_by=?,
        approved_at=NOW()
    WHERE id=? AND approval_status='pending'
  ");

  foreach ($ids as $id) {
    $chk->bind_param("i", $id);
    $chk->execute();
    $res = $chk->get_result();
    $row = $res->fetch_assoc();

    if (!$row) { $skipped_not_pending++; continue; }

    if (trim((string)$row['entered_hris']) !== '' && trim((string)$row['entered_hris']) === $current_hris) {
      $skipped_own++;
      continue;
    }

    if (($row['approval_status'] ?? 'pending') !== 'pending') {
      $skipped_not_pending++;
      continue;
    }

    $upd->bind_param("sssi", $current_hris, $current_name, $current_user, $id);
    $upd->execute();

    if ($upd->affected_rows > 0) $approved++;
  }

  $conn->commit();

  userlog("✅ Bulk approved 2000 invoices | Approved={$approved} | SkippedOwn={$skipped_own} | SkippedNotPending={$skipped_not_pending} | HRIS={$current_hris}");

  echo json_encode([
    'success' => true,
    'message' => "2000 invoices: Approved {$approved}. Skipped own: {$skipped_own}. Skipped not-pending: {$skipped_not_pending}."
  ]);
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['success'=>false,'message'=>'Bulk approve failed: '.$e->getMessage()]);
}
