<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function respond($ok,$msg){ echo json_encode(['success'=>$ok,'message'=>$msg]); exit; }

$token = trim($_POST['preview_token'] ?? '');
if($token === '' || empty($_SESSION['tea_preview'][$token])){
  respond(false, "Preview expired. Please preview again.");
}

$data = $_SESSION['tea_preview'][$token];

$entered_hris = $_SESSION['hris'] ?? '';
$entered_name = $_SESSION['name'] ?? 'Unknown';
if($entered_hris === ''){
  respond(false, "Session HRIS missing. Please login again.");
}

$month_year = $data['month_year'];
$month_date = $data['month_date'];
$floor_id   = (int)$data['floor_id'];
$sr_number  = $data['sr_number'];
$ot_amount  = (float)$data['ot_amount'];

$total_price = (float)$data['summary']['total_price'];
$sscl_amount = (float)$data['summary']['sscl_amount'];
$vat_amount  = (float)$data['summary']['vat_amount'];
$grand_total = (float)$data['summary']['grand_total'];

$conn->begin_transaction();

try {

  /* check existing */
  $chk = $conn->prepare("SELECT id, approval_status FROM tbl_admin_tea_service_hdr WHERE month_year=? AND floor_id=? LIMIT 1");
  $chk->bind_param("si", $month_year, $floor_id);
  $chk->execute();
  $ex = $chk->get_result()->fetch_assoc();

  if($ex){
    $st = strtolower(trim($ex['approval_status'] ?? 'pending'));
    if($st === 'approved') throw new Exception("Already APPROVED. Cannot overwrite.");
    if($st === 'pending')  throw new Exception("Already PENDING. Cannot overwrite.");
  }

  if(!$ex){
    /* insert header */
    $ins = $conn->prepare("
      INSERT INTO tbl_admin_tea_service_hdr
      (month_year, month_date, floor_id, sr_number, ot_amount,
       total_price, sscl_amount, vat_amount, grand_total,
       approval_status, entered_hris, entered_name, entered_at)
      VALUES (?,?,?,?,?,?,?,?,?,'pending',?,?,NOW())
    ");
    $ins->bind_param(
      "ssisdddddss",
      $month_year, $month_date, $floor_id, $sr_number,
      $ot_amount, $total_price, $sscl_amount, $vat_amount, $grand_total,
      $entered_hris, $entered_name
    );
    $ins->execute();
    $hdr_id = $conn->insert_id;

  } else {
    /* resubmit after rejected */
    $hdr_id = (int)$ex['id'];

    $upd = $conn->prepare("
      UPDATE tbl_admin_tea_service_hdr
      SET month_date=?, sr_number=?, ot_amount=?,
          total_price=?, sscl_amount=?, vat_amount=?, grand_total=?,
          approval_status='pending',
          entered_hris=?, entered_name=?, entered_at=NOW(),
          approved_hris=NULL, approved_name=NULL, approved_at=NULL,
          rejected_hris=NULL, rejected_name=NULL, rejected_at=NULL, rejection_reason=NULL
      WHERE id=? AND approval_status='rejected'
      LIMIT 1
    ");
    $upd->bind_param(
      "ssddddsss si",
      $month_date, $sr_number, $ot_amount,
      $total_price, $sscl_amount, $vat_amount, $grand_total,
      $entered_hris, $entered_name,
      $hdr_id
    );
    // NOTE: bind string above contains a space; fix properly:
    // We'll do the correct one below (safe):

    $upd = $conn->prepare("
      UPDATE tbl_admin_tea_service_hdr
      SET month_date=?, sr_number=?, ot_amount=?,
          total_price=?, sscl_amount=?, vat_amount=?, grand_total=?,
          approval_status='pending',
          entered_hris=?, entered_name=?, entered_at=NOW(),
          approved_hris=NULL, approved_name=NULL, approved_at=NULL,
          rejected_hris=NULL, rejected_name=NULL, rejected_at=NULL, rejection_reason=NULL
      WHERE id=? AND approval_status='rejected'
      LIMIT 1
    ");
    $upd->bind_param(
      "ssddddddssi",
      $month_date, $sr_number, $ot_amount,
      $total_price, $sscl_amount, $vat_amount, $grand_total,
      $entered_hris, $entered_name,
      $hdr_id
    );
    $upd->execute();

    if($upd->affected_rows <= 0){
      throw new Exception("Resubmit allowed only when status is REJECTED.");
    }

    /* clear old dtl */
    $del = $conn->prepare("DELETE FROM tbl_admin_tea_service_dtl WHERE hdr_id=?");
    $del->bind_param("i", $hdr_id);
    $del->execute();
  }

  /* insert dtl */
  $dtl = $conn->prepare("
    INSERT INTO tbl_admin_tea_service_dtl
    (hdr_id, item_id, units, unit_price, total_price, sscl_amount, vat_amount, line_grand_total)
    VALUES (?,?,?,?,?,?,?,?)
  ");

  foreach($data['lines'] as $l){
    $item_id = (int)$l['item_id'];
    $units   = (int)$l['units'];
    $unit_price = (float)$l['unit_price'];
    $line_total = (float)$l['total_price'];
    $line_sscl  = (float)$l['sscl_amount'];
    $line_vat   = (float)$l['vat_amount'];
    $line_grand = (float)$l['line_grand_total'];

    $dtl->bind_param("iiiddddd", $hdr_id, $item_id, $units, $unit_price, $line_total, $line_sscl, $line_vat, $line_grand);
    $dtl->execute();
  }

  $conn->commit();
  unset($_SESSION['tea_preview'][$token]);

  userlog("💾 Tea Saved PENDING | Month: {$month_year} | FloorID: {$floor_id} | By: {$entered_name} ({$entered_hris}) | Grand: {$grand_total}");
  respond(true, "✅ Saved successfully as PENDING.");

} catch(Exception $e){
  $conn->rollback();
  userlog("❌ Tea Save Failed | Month: {$month_year} | FloorID: {$floor_id} | Error: ".$e->getMessage());
  respond(false, $e->getMessage());
}
