<?php
// photocopy-monthly-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$entered_hris = $_SESSION['hris'] ?? 'N/A';
$entered_name = $_SESSION['name'] ?? 'Unknown';

$month       = trim($_POST['month'] ?? '');
$machine_id  = (int)($_POST['machine_id'] ?? 0);
$serial      = trim($_POST['serial'] ?? '');
$model       = trim($_POST['model'] ?? '');

$branch_code = trim($_POST['branch_code'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');

$start_count = ($_POST['start_count'] ?? '');
$end_count   = ($_POST['end_count'] ?? '');
$copy_count  = (int)($_POST['copy_count'] ?? 0);

$copy_rate        = (float)($_POST['copy_rate'] ?? 0);
$sscl_percentage  = (float)($_POST['sscl_percentage'] ?? 0);
$vat_percentage   = (float)($_POST['vat_percentage'] ?? 0);

if ($month === '' || !$machine_id || $serial === '' || $branch_code === '' || $copy_count <= 0 || $copy_rate <= 0) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields.']);
    exit;
}

// Validate machine exists and active
$stm = $conn->prepare("SELECT model, serial, vendor_id FROM tbl_admin_photocopy_machines WHERE machine_id=? AND is_active=1 LIMIT 1");
$stm->bind_param("i", $machine_id);
$stm->execute();
$mres = $stm->get_result();
if (!($m = $mres->fetch_assoc())) {
    echo json_encode(['success'=>false,'message'=>'Machine not found/inactive.']);
    exit;
}
if ($model === '') $model = $m['model'];
if ($serial === '') $serial = $m['serial'];

// Validate branch active
$stb = $conn->prepare("SELECT branch_name FROM tbl_admin_branches WHERE branch_code=? AND is_active=1 LIMIT 1");
$stb->bind_param("s", $branch_code);
$stb->execute();
$bres = $stb->get_result();
if (!($br = $bres->fetch_assoc())) {
    echo json_encode(['success'=>false,'message'=>'Branch not found/inactive.']);
    exit;
}
if ($branch_name === '') $branch_name = $br['branch_name'];

// Budget check FY
$ts = strtotime("1 " . $month);
$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$budget_year = ($mn < 4) ? ($y - 1) : $y;

$budStmt = $conn->prepare("SELECT COALESCE(amount,0) AS bud FROM tbl_admin_budget_photocopy WHERE branch_code=? AND budget_year=? LIMIT 1");
$by = (string)$budget_year;
$budStmt->bind_param("ss", $branch_code, $by);
$budStmt->execute();
$budRes = $budStmt->get_result();
$budRow = $budRes ? $budRes->fetch_assoc() : null;
$budAmt = (float)($budRow['bud'] ?? 0);
if ($budAmt <= 0) {
    echo json_encode(['success'=>false,'message'=>"No budget / 0 budget for FY {$budget_year}."]);
    exit;
}

// Calculate amounts (FROZEN PER ROW)
$base = round($copy_count * $copy_rate, 2);
$sscl = round($base * ($sscl_percentage / 100.0), 2);
$vat  = round(($base + $sscl) * ($vat_percentage / 100.0), 2);
$total = round($base + $sscl + $vat, 2);

// start/end optional but if provided ensure end>=start
if ($start_count !== '' && $end_count !== '') {
    $s = (int)$start_count;
    $e = (int)$end_count;
    if ($e < $s) {
        echo json_encode(['success'=>false,'message'=>'End count cannot be less than Start count.']);
        exit;
    }
}

$reference_no = 'PC-' . date('Ymd-His') . '-' . mt_rand(100, 999);

// Duplicate / provision logic
$check = $conn->prepare("
  SELECT id, approval_status, is_provision
  FROM tbl_admin_actual_photocopy
  WHERE machine_id=? AND month_applicable=?
  LIMIT 1
");
$check->bind_param("is", $machine_id, $month);
$check->execute();
$chkRes = $check->get_result();

$existing_id = null;
$existing_status = '';
$existing_prov = 'no';
if ($row = $chkRes->fetch_assoc()) {
    $existing_id = (int)$row['id'];
    $existing_status = strtolower(trim($row['approval_status'] ?? ''));
    $existing_prov = strtolower(trim($row['is_provision'] ?? 'no'));
}

// default: actual (pending)
$provision = 'no';
$approval_status = 'pending';

// block if already actual approved/pending
if ($existing_id && $existing_prov !== 'yes' && in_array($existing_status, ['approved','pending'], true)) {
    echo json_encode(['success'=>false,'message'=>"Entry already exists and is {$existing_status}."]);
    exit;
}

if ($existing_id) {
    $upd = $conn->prepare("
      UPDATE tbl_admin_actual_photocopy SET
        model=?, serial=?,
        branch_code=?, branch=?,
        start_count=?, end_count=?, copy_count=?,
        copy_rate=?,
        base_amount=?,
        sscl_percentage=?, sscl_amount=?,
        vat_percentage=?, vat_amount=?,
        total_amount=?,
        is_provision=?,
        provision_updated_at=NOW(),
        entered_hris=?, entered_name=?, entered_at=NOW(),
        approval_status=?
      WHERE id=?
      LIMIT 1
    ");

    // nullable start/end
    $sc = ($start_count === '') ? null : (int)$start_count;
    $ec = ($end_count === '') ? null : (int)$end_count;

    $upd->bind_param(
      "ssssiiidddddddssssi",
      $model, $serial,
      $branch_code, $branch_name,
      $sc, $ec, $copy_count,
      $copy_rate,
      $base,
      $sscl_percentage, $sscl,
      $vat_percentage, $vat,
      $total,
      $provision,
      $entered_hris, $entered_name,
      $approval_status,
      $existing_id
    );

    if ($upd->execute()) {
        userlog("💾 Photocopy Updated | Month: {$month} | Serial: {$serial} | Total: {$total}");
        echo json_encode(['success'=>true,'message'=>'✅ Actual saved as PENDING (sent for approval).']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Database update failed.']);
    exit;
}

// Insert new
$ins = $conn->prepare("
  INSERT INTO tbl_admin_actual_photocopy (
    reference_no, month_applicable,
    machine_id, model, serial,
    branch_code, branch,
    vendor_id,
    start_count, end_count, copy_count,
    copy_rate, base_amount,
    sscl_percentage, sscl_amount,
    vat_percentage, vat_amount,
    total_amount,
    is_provision, provision_reason, provision_updated_at,
    entered_hris, entered_name, entered_at,
    approval_status
  ) VALUES (
    ?, ?,
    ?, ?, ?,
    ?, ?,
    ?,
    ?, ?, ?,
    ?, ?,
    ?, ?,
    ?, ?,
    ?,
    ?, '', NOW(),
    ?, ?, NOW(),
    ?
  )
");

$vendor_id = (int)($m['vendor_id'] ?? 0);
$sc = ($start_count === '') ? null : (int)$start_count;
$ec = ($end_count === '') ? null : (int)$end_count;

$ins->bind_param(
  "ssissssiiiiidddddddssss",
  $reference_no, $month,
  $machine_id, $model, $serial,
  $branch_code, $branch_name,
  $vendor_id,
  $sc, $ec, $copy_count,
  $copy_rate, $base,
  $sscl_percentage, $sscl,
  $vat_percentage, $vat,
  $total,
  $provision,
  $entered_hris, $entered_name,
  $approval_status
);

if ($ins->execute()) {
    userlog("💾 Photocopy Saved | Month: {$month} | Serial: {$serial} | Total: {$total}");
    echo json_encode(['success'=>true,'message'=>'✅ Actual saved as PENDING (sent for approval).']);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Database insert failed.']);
exit;
