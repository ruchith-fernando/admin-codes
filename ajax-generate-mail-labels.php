<?php
// pages/ajax/generate-mail-labels.php
// Option A: Auto-seed a counter ROW for new departments (INSERT IGNORE), no table creation here.
header('Content-Type: application/json');


$VENDOR_AUTOLOAD = 'vendor-composer/vendor/autoload.php';
$CONNECTION_FILE = 'connections/connection.php';

if(!file_exists($VENDOR_AUTOLOAD)){
  echo json_encode(['ok'=>false,'message'=>'Composer autoload not found']); exit;
}
require $VENDOR_AUTOLOAD;

if(!file_exists($CONNECTION_FILE)){
  echo json_encode(['ok'=>false,'message'=>'DB connection file missing']); exit;
}
require $CONNECTION_FILE;

if (session_status() === PHP_SESSION_NONE) session_start();

use Picqer\Barcode\BarcodeGeneratorSVG;

/* timezone + server date (no user date) */
date_default_timezone_set('Asia/Colombo');
$dateYmd   = date('Y-m-d');
$yyyymmdd  = date('Ymd');

/* dept from login (company_hierarchy) */
$dept = trim($_SESSION['company_hierarchy'] ?? '');
if ($dept === '' && isset($_SESSION['hris'])) {
  $hris = $conn->real_escape_string($_SESSION['hris']);
  $rs = mysqli_query($conn, "SELECT company_hierarchy FROM tbl_admin_users WHERE hris='$hris' LIMIT 1");
  if ($rs && $row = mysqli_fetch_assoc($rs)) {
    $dept = trim($row['company_hierarchy'] ?? '');
    $_SESSION['company_hierarchy'] = $dept; // cache
  }
}

/* input */
$input = json_decode(file_get_contents('php://input'), true);
$qty   = (int)($input['qty'] ?? 0);

if($dept==='' || $qty<1 || $qty>500){
  echo json_encode(['ok'=>false,'message'=>'Invalid input (dept/qty).']); exit;
}

/* table names (assumed to exist) */
$T_COUNTERS = 'tbl_admin_mail_label_counters';
$T_LABELS   = 'tbl_admin_mail_labels';

$deptEsc = $conn->real_escape_string($dept);

/* --- Option A core: ensure a ROW exists for this dept (no CREATE TABLE) --- */
mysqli_query($conn, "INSERT IGNORE INTO $T_COUNTERS (dept_code, last_serial) VALUES ('$deptEsc', 0)");

/* Efficient allocation: bump once by qty, then compute range */
$upd = mysqli_query($conn, "UPDATE $T_COUNTERS SET last_serial = last_serial + $qty WHERE dept_code = '$deptEsc'");
if(!$upd || mysqli_affected_rows($conn) === 0){
  echo json_encode(['ok'=>false,'message'=>'Counter row missing for department']); exit;
}
$rs = mysqli_query($conn, "SELECT last_serial FROM $T_COUNTERS WHERE dept_code = '$deptEsc' LIMIT 1");
$last = (int)mysqli_fetch_assoc($rs)['last_serial'];
$first = $last - $qty + 1;

$gen = new BarcodeGeneratorSVG();
$labels = [];

/* Shorter dept token: first 3 A–Z/0–9 of company_hierarchy */
$deptToken = strtoupper(preg_replace('/[^A-Z0-9]/i','', $dept));
$deptToken = substr($deptToken, 0, 3);
if ($deptToken === '') $deptToken = 'DPT';   // fallback

$dateSix = date('ymd'); // YYMMDD (kept server-side)

for($sn = $first; $sn <= $last; $sn++){
  $humanSerial = str_pad((string)$sn, 6, '0', STR_PAD_LEFT);

  /* MUCH shorter payload (no separators): DDDYYMMDD######  e.g., ADM250828000151 */
  $codeText = $deptToken.$dateSix.$humanSerial;


  $escCode = $conn->real_escape_string($codeText);
  $escDate = $conn->real_escape_string($dateYmd);

  /* audit save (table assumed to exist) */
  mysqli_query($conn, "INSERT IGNORE INTO $T_LABELS(dept_code, serial_no, label_date, code_text)
                       VALUES('$deptEsc', $sn, '$escDate', '$escCode')");

    // Slimmer bars: width factor 1, height ~42px (also constrained by CSS)
  $svg = $gen->getBarcode($codeText, $gen::TYPE_CODE_128, 1, 42);

  $labels[] =
    '<div class="label">'.
      '<h6>'.htmlspecialchars($dept).' — #'.htmlspecialchars($humanSerial).'</h6>'.
      '<div class="barcode">'.$svg.'</div>'.
      '<div class="code-text">'.htmlspecialchars($codeText).'</div>'.
    '</div>';

}

$html = '<div class="label-grid">'.implode('', $labels).'</div>';
echo json_encode(['ok'=>true,'html'=>$html]);
