<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function normalize_mobile_db($input) {
  $m = preg_replace('/\D+/', '', $input ?? ''); // digits only
  if ($m === '') return '';

  // +94 / 94XXXXXXXXX -> XXXXXXXXX (9 digits)
  if (strpos($m, '94') === 0) {
    $m = substr($m, 2);
  }

  // 0XXXXXXXXX (10 digits) -> XXXXXXXXX (9 digits)
  if (strlen($m) === 10 && $m[0] === '0') {
    $m = substr($m, 1);
  }

  // Now we expect 9 digits
  return $m;
}

$mobile_raw = $_GET['mobile'] ?? '';
$mobile = normalize_mobile_db($mobile_raw);

if ($mobile === '') {
  echo json_encode(['ok'=>false, 'error'=>'Mobile is required']);
  exit;
}

if (!preg_match('/^\d{9}$/', $mobile)) {
  echo json_encode(['ok'=>false, 'error'=>'Mobile must be 9 digits (stored format)']);
  exit;
}

$cur = $conn->prepare("
  SELECT id, mobile_number, hris_no, owner_name, effective_from
  FROM tbl_admin_mobile_allocations
  WHERE TRIM(mobile_number)=?
    AND status='Active'
    AND effective_to IS NULL
  ORDER BY effective_from DESC, id DESC
  LIMIT 1
");
$cur->bind_param("s", $mobile);
$cur->execute();
$activeRow = $cur->get_result()->fetch_assoc();
$cur->close();

echo json_encode([
  'ok' => true,
  'input_mobile' => $mobile_raw,
  'mobile' => $mobile,
  'active' => $activeRow ? true : false,
  'active_row' => $activeRow
]);
