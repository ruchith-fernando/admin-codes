<?php
// save-remark.php
session_start();
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

header('Content-Type: application/json');

if (!isset($_SESSION['hris']) || $_SESSION['hris'] === '') {
  echo json_encode(['status'=>'error','message'=>'Not logged in']); exit;
}

$sender   = $_SESSION['hris'];
$category = $_POST['category']    ?? '';
$record   = $_POST['record']      ?? '';
$comment  = $_POST['comment']     ?? '';
$recips   = $_POST['recipients']  ?? [];   // array of HRIS (optional)
$origin   = $_POST['origin_page'] ?? '';   // page file (optional)
$now      = date('Y-m-d H:i:s');

/** Basic validation */
if ($category === '' || $record === '' || $comment === '') {
  echo json_encode(['status'=>'error','message'=>'Missing data']); exit;
}

/** Escape */
$senderEsc   = mysqli_real_escape_string($conn, $sender);
$categoryEsc = mysqli_real_escape_string($conn, $category);
$recordEsc   = mysqli_real_escape_string($conn, $record);
$commentEsc  = mysqli_real_escape_string($conn, $comment);
$originEsc   = mysqli_real_escape_string($conn, $origin);

/**
 * Build a concrete deeplink (origin_url) for Special Notes.
 * Priority:
 *   1) Provided origin_page
 *   2) Route by category
 *   3) dashboard.php
 * Always append ?q=<record> (or &q=...).
 */
function build_origin_url($origin_page, $category, $record) {
  $router = [
    'Security Charges'         => 'security-cost-report.php',
    'Tea Service - Head Office'=> 'tea-budget-vs-actual.php',
    'Printing & Stationary'    => 'budget-vs-actual-stationary.php',
    'Electricity Charges'      => 'electricity-overview.php',
    'Photocopy'                => 'photocopy-budget-report.php',
    'Courier'                  => 'courier-cost-report.php',
    'Vehicle Maintenance'      => 'vehicle-budget-vs-actual.php',
    'Postage & Stamps'         => 'postage-budget-vs-actual.php',
    'Telephone Bills'          => 'telephone-budget-vs-actual.php',
    'Newspaper'                => '#',
    'Water'                    => '#',
  ];

  $base = trim((string)$origin_page);
  if ($base === '') {
    $base = $router[$category] ?? 'dashboard.php';
  }
  // If mapped to '#' or still empty, fall back to dashboard
  if ($base === '#' || $base === '') {
    $base = 'dashboard.php';
  }

  // Append q param
  $hasQuery = (strpos($base, '?') !== false);
  $url = $base . ($hasQuery ? '&' : '?') . 'q=' . rawurlencode($record);

  // Keep within 255 for VARCHAR(255)
  if (strlen($url) > 255) {
    $url = substr($url, 0, 255);
  }
  return $url;
}

$originUrl    = build_origin_url($origin, $category, $record);
$originUrlEsc = mysqli_real_escape_string($conn, $originUrl);

/**
 * 1) Insert the message row (try with origin_url first).
 * If column not found (1054), fall back to legacy insert.
 */
$insertSqlWithUrl = "
  INSERT INTO tbl_admin_remarks
    (hris_id, sender_hris, category, record_key, sr_number, comment, is_read, commented_at, origin_page, origin_url)
  VALUES
    ('$senderEsc', '$senderEsc', '$categoryEsc', '$recordEsc', '', '$commentEsc', 'yes', '$now', '$originEsc', '$originUrlEsc')
";
$okInsert = mysqli_query($conn, $insertSqlWithUrl);

if (!$okInsert) {
  if (mysqli_errno($conn) == 1054) { // Unknown column 'origin_url'
    $insertSqlNoUrl = "
      INSERT INTO tbl_admin_remarks
        (hris_id, sender_hris, category, record_key, sr_number, comment, is_read, commented_at, origin_page)
      VALUES
        ('$senderEsc', '$senderEsc', '$categoryEsc', '$recordEsc', '', '$commentEsc', 'yes', '$now', '$originEsc')
    ";
    if (!mysqli_query($conn, $insertSqlNoUrl)) {
      echo json_encode(['status'=>'error','message'=>'Insert failed']); exit;
    }
  } else {
    echo json_encode(['status'=>'error','message'=>'Insert failed']); exit;
  }
}

$remarkId = (int)mysqli_insert_id($conn);

/** 2) Generate SR and update */
$sr = generate_sr_number($conn, 'tbl_admin_remarks', $remarkId);
if ($sr) {
  $srEsc = mysqli_real_escape_string($conn, $sr);
  mysqli_query($conn, "UPDATE tbl_admin_remarks SET sr_number='$srEsc' WHERE id=$remarkId");
}

/** 3) If origin_url exists in schema but we fell back, try to set it now (noop if column missing) */
if ($originUrl !== '') {
  @mysqli_query($conn, "UPDATE tbl_admin_remarks SET origin_url='$originUrlEsc' WHERE id=$remarkId");
}

/** 4) Fan-out recipients (skip empty/self) */
$sentCount = 0;
if (is_array($recips) && count($recips) > 0) {
  $values = [];
  foreach ($recips as $r) {
    $r = trim((string)$r);
    if ($r === '' || $r === $sender) continue;
    $rEsc = mysqli_real_escape_string($conn, $r);
    $values[] = "($remarkId,'$rEsc','no',NULL)";
  }
  if (!empty($values)) {
    $sqlBulk = "INSERT INTO tbl_admin_remarks_recipients (remark_id, recipient_hris, is_read, read_at)
                VALUES ".implode(',', $values);
    if (mysqli_query($conn, $sqlBulk)) {
      $sentCount = mysqli_affected_rows($conn);
    }
  }
}

/** 5) Respond */
echo json_encode([
  'status'     => 'success',
  'remark_id'  => $remarkId,
  'sent_to'    => $sentCount,
  'sr_number'  => $sr ?? '',
  'origin_url' => $originUrl,
]);
