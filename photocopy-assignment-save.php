<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$machine_id   = (int)($_POST['machine_id'] ?? 0);
$branch_code  = trim($_POST['branch_code'] ?? '');
$vendor_id_in = trim($_POST['vendor_id'] ?? ''); // may be empty
$installed_at = trim($_POST['installed_at'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');

if ($machine_id <= 0 || $branch_code === '' || $installed_at === '') {
  echo json_encode(['success'=>false,'message'=>'Machine, branch code and installed date are required.']);
  exit;
}

$vendor_id = null;
if ($vendor_id_in !== '') $vendor_id = (int)$vendor_id_in;

// Validate machine exists
$chkM = $conn->prepare("SELECT machine_id, vendor_id FROM tbl_admin_photocopy_machines WHERE machine_id=? LIMIT 1");
$chkM->bind_param("i", $machine_id);
$chkM->execute();
$mr = $chkM->get_result();
$mrow = $mr->fetch_assoc();
if (!$mrow) {
  echo json_encode(['success'=>false,'message'=>'Invalid machine_id.']);
  exit;
}

// Validate branch exists (recommended)
$chkB = $conn->prepare("SELECT branch_name FROM tbl_admin_branches WHERE branch_code=? AND is_active=1 LIMIT 1");
$chkB->bind_param("s", $branch_code);
$chkB->execute();
$br = $chkB->get_result();
$brow = $br->fetch_assoc();
if (!$brow) {
  echo json_encode(['success'=>false,'message'=>'Branch not found in tbl_admin_branches (or inactive).']);
  exit;
}

// If vendor_id empty -> default to machine vendor
if ($vendor_id === null) {
  $vendor_id = ($mrow['vendor_id'] !== null) ? (int)$mrow['vendor_id'] : null;
}

// Start transaction
$conn->begin_transaction();

try {

  // Find existing current assignment
  $getCur = $conn->prepare("
    SELECT assign_id, branch_code, vendor_id, installed_at
    FROM tbl_admin_photocopy_machine_assignments
    WHERE machine_id=? AND removed_at IS NULL
    ORDER BY assign_id DESC
    LIMIT 1
  ");
  $getCur->bind_param("i", $machine_id);
  $getCur->execute();
  $curRes = $getCur->get_result();
  $cur = $curRes->fetch_assoc();

  if ($cur) {
    // If same branch + same vendor -> just update installed_at/remarks (no new row)
    $sameBranch = ($cur['branch_code'] === $branch_code);
    $sameVendor = ((string)($cur['vendor_id'] ?? '') === (string)($vendor_id ?? ''));

    if ($sameBranch && $sameVendor) {
      $upd = $conn->prepare("
        UPDATE tbl_admin_photocopy_machine_assignments
        SET installed_at=?, remarks=?
        WHERE assign_id=?
        LIMIT 1
      ");
      $upd->bind_param("ssi", $installed_at, $remarks, $cur['assign_id']);
      $upd->execute();

      $conn->commit();
      echo json_encode(['success'=>true,'message'=>'Updated current assignment (same branch/vendor).']);
      exit;
    }

    // Close current assignment
    $close = $conn->prepare("
      UPDATE tbl_admin_photocopy_machine_assignments
      SET removed_at=?, remarks=CONCAT(IFNULL(remarks,''), IF(IFNULL(remarks,'')='', '', ' | '), 'Auto-closed on move: ', ?)
      WHERE assign_id=?
      LIMIT 1
    ");
    $close->bind_param("ssi", $installed_at, $remarks, $cur['assign_id']);
    $close->execute();
  }

  // Insert new assignment
  $ins = $conn->prepare("
    INSERT INTO tbl_admin_photocopy_machine_assignments
      (machine_id, branch_code, vendor_id, installed_at, removed_at, remarks)
    VALUES
      (?, ?, ?, ?, NULL, ?)
  ");

  // vendor_id may be NULL
  if ($vendor_id === null) {
    $null = null;
    $ins->bind_param("issss", $machine_id, $branch_code, $null, $installed_at, $remarks);
  } else {
    $ins->bind_param("isiss", $machine_id, $branch_code, $vendor_id, $installed_at, $remarks);
  }
  $ins->execute();

  $conn->commit();
  echo json_encode(['success'=>true,'message'=>'Assignment saved (move recorded by closing previous assignment).']);

} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success'=>false,'message'=>'Save failed: '.$e->getMessage()]);
}
