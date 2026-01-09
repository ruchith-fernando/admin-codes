<?php
require_once __DIR__ . '/../connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function bad($msg){ echo json_encode(["ok"=>false, "error"=>$msg]); exit; }

$raw = file_get_contents("php://input");
$in = json_decode($raw, true);
if (!is_array($in)) bad("Invalid JSON");

$type = trim($in['request_type'] ?? '');
$mobile = trim($in['mobile_number'] ?? '');
$eff_from = trim($in['effective_from'] ?? '');
$to_hris = trim($in['to_hris_no'] ?? '');
$owner = trim($in['owner_name'] ?? '');
$note = trim($in['note'] ?? '');
$eff_to = trim($in['effective_to'] ?? '');
$close_note = trim($in['close_note'] ?? '');

if ($type === '' || !in_array($type, ['NEW','TRANSFER','CLOSE'], true)) bad("Select a request type");
if ($mobile === '') bad("Mobile number is required");
if ($eff_from === '') bad("Effective from is required");
if (($type === 'NEW' || $type === 'TRANSFER') && $to_hris === '') bad("To HRIS is required");
if ($type === 'CLOSE' && $eff_to === '') bad("Effective to is required");
if ($type === 'CLOSE' && $eff_to < $eff_from) bad("effective_to cannot be earlier than effective_from");

$current_hris = $_SESSION['hris'] ?? null;
$current_name = $_SESSION['name'] ?? null;

$from_hris = null;
/* For TRANSFER/CLOSE, try to find current active HRIS */
if ($type !== 'NEW') {
  $q = $conn->prepare("
    SELECT hris_no
    FROM tbl_admin_mobile_allocations
    WHERE mobile_number = ?
      AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $q->bind_param("s", $mobile);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  $from_hris = $r['hris_no'] ?? null;
  $q->close();
}

$final_note = ($type === 'CLOSE') ? $close_note : $note;

$stmt = $conn->prepare("
  INSERT INTO tbl_admin_mobile_allocation_requests
    (request_type, mobile_number, from_hris_no, to_hris_no, owner_name, effective_from, effective_to, note,
     requested_by_hris, requested_by_name, status)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
");
$stmt->bind_param(
  "ssssssssss",
  $type,
  $mobile,
  $from_hris,
  $to_hris,
  $owner,
  $eff_from,
  ($type === 'CLOSE' ? $eff_to : null),
  $final_note,
  $current_hris,
  $current_name
);

if (!$stmt->execute()) {
  bad("DB error: " . $stmt->error);
}

$request_id = $stmt->insert_id;
$stmt->close();

echo json_encode(["ok"=>true, "request_id"=>$request_id]);
