<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$serial    = trim($_POST['serial'] ?? '');
$move_date = trim($_POST['move_date'] ?? '');
$to_branch = trim($_POST['to_branch'] ?? '');
$reason    = trim($_POST['reason'] ?? '');

$name = $_SESSION['name'] ?? 'Unknown';

if ($serial==='' || $move_date==='' || $to_branch==='') {
  echo json_encode(['success'=>false,'message'=>'Missing required fields']);
  exit;
}

$md = strtotime($move_date);
if (!$md) { echo json_encode(['success'=>false,'message'=>'Invalid move date']); exit; }

$stm = $conn->prepare("SELECT machine_id FROM tbl_admin_photocopy_machines WHERE serial=? AND is_active=1 LIMIT 1");
$stm->bind_param("s",$serial);
$stm->execute();
$m = $stm->get_result()->fetch_assoc();
if (!$m) { echo json_encode(['success'=>false,'message'=>'Serial not found/inactive']); exit; }
$machine_id = (int)$m['machine_id'];

// find current assignment (open or active on move_date)
$mdStr = date('Y-m-d', $md);
$cur = $conn->prepare("
  SELECT id, branch_code, effective_from, effective_to
  FROM tbl_admin_photocopy_machine_assignments
  WHERE machine_id=?
    AND effective_from <= ?
    AND (effective_to IS NULL OR effective_to >= ?)
  ORDER BY effective_from DESC
  LIMIT 1
");
$cur->bind_param("iss",$machine_id,$mdStr,$mdStr);
$cur->execute();
$c = $cur->get_result()->fetch_assoc();
if (!$c) { echo json_encode(['success'=>false,'message'=>'No active assignment found for this move date']); exit; }

$from_branch = $c['branch_code'];
$assignment_id = (int)$c['id'];

if ($from_branch === $to_branch) {
  echo json_encode(['success'=>false,'message'=>'From and To branch are the same']);
  exit;
}

// close existing assignment one day before move_date (or same day-1)
$endPrev = date('Y-m-d', strtotime($mdStr . ' -1 day'));
$upd = $conn->prepare("UPDATE tbl_admin_photocopy_machine_assignments SET effective_to=? WHERE id=? LIMIT 1");
$upd->bind_param("si",$endPrev,$assignment_id);
if (!$upd->execute()) {
  echo json_encode(['success'=>false,'message'=>'Failed closing old assignment']); exit;
}

// create new assignment starting move_date
$ins = $conn->prepare("
  INSERT INTO tbl_admin_photocopy_machine_assignments
    (machine_id, branch_code, effective_from, effective_to, created_by)
  VALUES (?,?,?,?,?)
");
$null = null;
$ins->bind_param("issss", $machine_id, $to_branch, $mdStr, $null, $name);
if (!$ins->execute()) {
  echo json_encode(['success'=>false,'message'=>'Failed creating new assignment']); exit;
}

// log move
$log = $conn->prepare("
  INSERT INTO tbl_admin_photocopy_machine_moves
    (machine_id, from_branch_code, to_branch_code, move_date, reason, moved_by)
  VALUES (?,?,?,?,?,?)
");
$log->bind_param("isssss",$machine_id,$from_branch,$to_branch,$mdStr,$reason,$name);
$log->execute();

userlog("🚚 Photocopy Machine Move | Serial: {$serial} | {$from_branch} -> {$to_branch} | Date: {$mdStr}");

echo json_encode(['success'=>true,'message'=>'Machine moved successfully.']);
exit;
