<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function respond($ok, $msg='', $extra=[]){
  echo json_encode(array_merge(['success'=>$ok, 'message'=>$msg], $extra));
  exit;
}

$month_year = trim($_POST['month_year'] ?? '');
$floor_id   = (int)($_POST['floor_id'] ?? 0);
$sr_number  = trim($_POST['sr_number'] ?? '');
$ot_amount  = (float)($_POST['ot_amount'] ?? 0);
$unitsArr   = $_POST['units'] ?? [];

if($month_year === '' || $floor_id <= 0) respond(false, "Month and Floor are required.");
if($ot_amount < 0) respond(false, "OT amount cannot be negative.");

$ts = strtotime('01 ' . $month_year);
if(!$ts) respond(false, "Invalid month format.");
$month_date = date('Y-m-d', $ts);

/* Floor info */
$fl = $conn->prepare("SELECT floor_no FROM tbl_admin_floors WHERE id=? LIMIT 1");
$fl->bind_param("i", $floor_id);
$fl->execute();
$flRow = $fl->get_result()->fetch_assoc();
if(!$flRow) respond(false, "Invalid floor.");

$floor_no = (int)$flRow['floor_no'];

/* ✅ OT floor is ONLY floor_no = 8 */
$isOTFloor = ($floor_no === 8);

/* Budget (optional display) */
$bud = $conn->prepare("SELECT budget_amount FROM tbl_admin_budget_tea_service WHERE month_year=? LIMIT 1");
$bud->bind_param("s", $month_year);
$bud->execute();
$budRow = $bud->get_result()->fetch_assoc();
$budget_amount = $budRow ? (float)$budRow['budget_amount'] : 0.0;

/* Approved total so far (all floors) */
$ap = $conn->prepare("
  SELECT COALESCE(SUM(grand_total),0) AS s
  FROM tbl_admin_tea_service_hdr
  WHERE month_year=? AND approval_status='approved'
");
$ap->bind_param("s", $month_year);
$ap->execute();
$approved_total = (float)($ap->get_result()->fetch_assoc()['s'] ?? 0);

/* ✅ OT-only mode: no items, no taxes */
if($isOTFloor){
  $lines = [];
  $summary = [
    'total_price' => 0.00,
    'sscl_amount' => 0.00,
    'vat_amount'  => 0.00,
    'ot_amount'   => round($ot_amount,2),
    'grand_total' => round($ot_amount,2),
  ];

  $token = bin2hex(random_bytes(16));
  $_SESSION['tea_preview'][$token] = [
    'month_year' => $month_year,
    'month_date' => $month_date,
    'floor_id' => $floor_id,
    'sr_number' => $sr_number,
    'ot_amount' => round($ot_amount,2),
    'ssclP' => 0,
    'vatP' => 0,
    'lines' => $lines,
    'summary' => $summary
  ];

  respond(true, "", [
    'preview_token' => $token,
    'lines' => $lines,
    'summary' => $summary,
    'budget_amount' => round($budget_amount,2),
    'approved_total' => round($approved_total,2),
  ]);
}

/* ✅ Non-OT floors cannot enter OT */
if($ot_amount > 0.00001) respond(false, "OT amount can be entered only for Over Time floor (Floor No 8).");
$ot_amount = 0.0;

/* Tax config (latest effective <= month_date) */
$tax = $conn->prepare("
  SELECT sscl_percentage, vat_percentage
  FROM tbl_admin_tea_tax_config
  WHERE effective_from <= ?
  ORDER BY effective_from DESC
  LIMIT 1
");
$tax->bind_param("s", $month_date);
$tax->execute();
$taxRow = $tax->get_result()->fetch_assoc();
if(!$taxRow) respond(false, "Tea tax config not found for this date.");

$ssclP = (float)$taxRow['sscl_percentage'];
$vatP  = (float)$taxRow['vat_percentage'];

/* Items */
$items = mysqli_query($conn, "SELECT id, item_name FROM tbl_admin_tea_items WHERE is_active=1 ORDER BY sort_order, item_name");

$rateStmt = $conn->prepare("
  SELECT unit_price
  FROM tbl_admin_tea_item_rates
  WHERE item_id=? AND effective_from <= ?
  ORDER BY effective_from DESC
  LIMIT 1
");

$lines = [];
$total = 0.0; $ssclTotal = 0.0; $vatTotal = 0.0;

while($it = mysqli_fetch_assoc($items)){
  $item_id = (int)$it['id'];
  $units   = isset($unitsArr[$item_id]) ? (int)$unitsArr[$item_id] : 0;
  if($units < 0) respond(false, "Units cannot be negative.");

  $rateStmt->bind_param("is", $item_id, $month_date);
  $rateStmt->execute();
  $rateRow = $rateStmt->get_result()->fetch_assoc();
  if(!$rateRow) respond(false, "Rate missing for item: ".$it['item_name']);

  $unit_price = (float)$rateRow['unit_price'];
  $line_total = $units * $unit_price;

  $line_sscl = $line_total * ($ssclP / 100.0);
  $line_vat  = ($line_total + $line_sscl) * ($vatP / 100.0);
  $line_grand = $line_total + $line_sscl + $line_vat;

  $total += $line_total;
  $ssclTotal += $line_sscl;
  $vatTotal  += $line_vat;

  $lines[] = [
    'item_id' => $item_id,
    'item_name' => $it['item_name'],
    'units' => $units,
    'unit_price' => round($unit_price,2),
    'total_price' => round($line_total,2),
    'sscl_amount' => round($line_sscl,2),
    'vat_amount' => round($line_vat,2),
    'line_grand_total' => round($line_grand,2),
  ];
}

$grand_total = $total + $ssclTotal + $vatTotal + $ot_amount;

/* Store preview in session */
$token = bin2hex(random_bytes(16));
$_SESSION['tea_preview'][$token] = [
  'month_year' => $month_year,
  'month_date' => $month_date,
  'floor_id' => $floor_id,
  'sr_number' => $sr_number,
  'ot_amount' => round($ot_amount,2),
  'ssclP' => $ssclP,
  'vatP' => $vatP,
  'lines' => $lines,
  'summary' => [
    'total_price' => round($total,2),
    'sscl_amount' => round($ssclTotal,2),
    'vat_amount'  => round($vatTotal,2),
    'ot_amount'   => round($ot_amount,2),
    'grand_total' => round($grand_total,2),
  ]
];

respond(true, "", [
  'preview_token' => $token,
  'lines' => $lines,
  'summary' => $_SESSION['tea_preview'][$token]['summary'],
  'budget_amount' => round($budget_amount,2),
  'approved_total' => round($approved_total,2),
]);
