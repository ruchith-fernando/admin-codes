<?php
// process-electricity-csv.php
// Upload handler for Electricity CSV -> tbl_admin_actual_electricity

header('Content-Type: text/html; charset=utf-8');

require_once 'connections/connection.php';

/* ---- Handle $con alias if your connection file used $con ---- */
if (!isset($conn) || !($conn instanceof mysqli)) {
  if (isset($con) && $con instanceof mysqli) { $conn = $con; }
}
if (!($conn instanceof mysqli)) {
  http_response_code(500);
  echo "<div class='alert alert-danger'><b>DB Error:</b> No mysqli connection.</div>";
  exit;
}

/* ---- Validate upload ---- */
if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
  http_response_code(400);
  echo "<div class='alert alert-danger'><b>Error:</b> No CSV uploaded.</div>";
  exit;
}

$raw = file_get_contents($_FILES['csv_file']['tmp_name']);
if ($raw === false) {
  http_response_code(400);
  echo "<div class='alert alert-danger'><b>Error:</b> Unable to read uploaded file.</div>";
  exit;
}

/* ---- Strip UTF-8 BOM if present ---- */
if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
  $raw = substr($raw, 3);
}
$lines = preg_split("/\r\n|\n|\r/", $raw);
if (!$lines || count($lines) < 2) {
  echo "<div class='alert alert-danger'><b>Error:</b> CSV seems empty.</div>";
  exit;
}

/* ---- Detect delimiter from the header line ---- */
function detect_delim($line){
  $cands = [",",";","\t","|"];
  $best = ",";
  $bestCnt = 0;
  foreach($cands as $d){
    $c = substr_count($line, $d);
    if ($c > $bestCnt){ $bestCnt = $c; $best = $d; }
  }
  return $best;
}
$delimiter = detect_delim($lines[0]);

/* ---- Open a memory stream and use fgetcsv ---- */
$fh = fopen('php://memory', 'r+');
fwrite($fh, $raw);
rewind($fh);

$header = fgetcsv($fh, 0, $delimiter);
if (!$header) {
  echo "<div class='alert alert-danger'><b>Error:</b> Could not read CSV header.</div>";
  exit;
}

/* ---- Header normalization and mapping ---- */
function norm_col($s){
  $s = strtolower(trim($s));
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}
$idx = [];  // column index map

foreach ($header as $i => $h) {
  $k = norm_col($h);

  // Branch code
  if (in_array($k, ['branch code','branch_code','branchcode','code'])) {
    $idx['branch_code'] = $i;
  }
  // Branch
  elseif ($k === 'branch') {
    $idx['branch'] = $i;
  }
  // Account No (typos tolerated)
  elseif (in_array($k, ['account no','acount no','account #','acc no','acc no.'])) {
    $idx['account_no'] = $i;
  }
  // Dates
  elseif (in_array($k, ['bill from date','from date','from'])) {
    $idx['bill_from_date'] = $i;
  } elseif (in_array($k, ['to bill','bill to date','to date','to'])) {
    $idx['bill_to_date'] = $i;
  }
  // Amounts
  elseif (in_array($k, ['amount','bill amount','total'])) {
    $idx['bill_amount'] = $i;
  } elseif (in_array($k, ['paid amount','paid'])) {
    $idx['paid_amount'] = $i;
  }
  // Units
  elseif (in_array($k, ['unit','units'])) {
    $idx['actual_units'] = $i;
  }
  // Number of days
  elseif (in_array($k, ['no of date','no of days','number of days','days'])) {
    $idx['number_of_days'] = $i;
  }
  // Applicable Month
  elseif (in_array($k, ['applicable month','applicable-month','month'])) {
    $idx['month_applicable'] = $i;
  }
}

/* ---- Required columns ---- */
$required = ['branch_code','branch','account_no','bill_from_date','bill_to_date','bill_amount','number_of_days','actual_units','paid_amount'];
$missing = array_values(array_diff($required, array_keys($idx)));
if ($missing){
  echo "<div class='alert alert-danger'><b>Missing columns:</b> ".htmlspecialchars(implode(', ', $missing))."</div>";
  fclose($fh);
  exit;
}

/* ---- Helpers ---- */
function parse_date($s){
  $s = trim((string)$s);
  if ($s === '' || $s === '0' || $s === '-') return null;
  // unify separators
  $try = [$s, str_replace('.', '/', $s)];
  foreach ($try as $t) {
    $ts = strtotime($t);
    if ($ts) return date('Y-m-d', $ts);
  }
  return null;
}
function parse_money($s){
  $s = trim((string)$s);
  if ($s === '' || $s === '-' || $s === '—') return null;
  $n = preg_replace('/[^\d\.\-]/', '', $s); // remove commas, spaces, Rs., etc.
  if ($n === '' || $n === '-' || $n === '.') return null;
  return number_format((float)$n, 2, '.', '');
}
function parse_int($s){
  $s = trim((string)$s);
  if ($s === '' || $s === '-') return null;
  $n = preg_replace('/[^\d\-]/', '', $s);
  if ($n === '' || $n === '-') return null;
  return (int)$n;
}
function month_from_inputs($appMonthCsv, $from, $to){
  // Prefer explicit "Applicable Month" from CSV if present
  $m = trim((string)$appMonthCsv);
  if ($m !== '') return $m;

  // Fallback: month of From date (then To)
  $base = $from ?: $to;
  if (!$base) return null;
  $ts = strtotime($base);
  if (!$ts) return null;
  return date('F Y', $ts); // e.g., "April 2025"
}

/* ---- Import loop ---- */
$inserted = 0;
$updated  = 0;
$unchanged = 0;
$skipped  = 0;
$errors   = [];

mysqli_begin_transaction($conn);

$rownum = 1; // header is row 1
while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
  $rownum++;

  // Pull fields
  $branch_code    = trim((string)($row[$idx['branch_code']] ?? ''));
  $branch         = trim((string)($row[$idx['branch']] ?? ''));
  $account_no     = trim((string)($row[$idx['account_no']] ?? ''));
  $bill_from_date = parse_date($row[$idx['bill_from_date']] ?? '');
  $bill_to_date   = parse_date($row[$idx['bill_to_date']] ?? '');
  $bill_amount    = parse_money($row[$idx['bill_amount']] ?? '');
  $paid_amount    = parse_money($row[$idx['paid_amount']] ?? '');
  $number_of_days = parse_int($row[$idx['number_of_days']] ?? '');
  // units as string (strip grouping commas only)
  $actual_units   = trim((string)($row[$idx['actual_units']] ?? ''));
  $actual_units   = preg_replace('/\s+/', ' ', str_replace(',', '', $actual_units));

  $appMonthCsv    = isset($idx['month_applicable']) ? ($row[$idx['month_applicable']] ?? '') : '';
  $month_applicable = month_from_inputs($appMonthCsv, $bill_from_date, $bill_to_date);

  // Basic validation
  if ($branch_code === '' || $branch === '') {
    $skipped++; $errors[] = "Row $rownum: Missing branch code/branch.";
    continue;
  }
  if (!$bill_from_date && !$bill_to_date) {
    $skipped++; $errors[] = "Row $rownum: Missing bill dates.";
    continue;
  }
  if (!$month_applicable) {
    $skipped++; $errors[] = "Row $rownum: Could not determine Applicable Month.";
    continue;
  }

  // Escape for SQL
  $branch_code_esc = mysqli_real_escape_string($conn, $branch_code);
  $branch_esc      = mysqli_real_escape_string($conn, $branch);
  $acc_esc         = mysqli_real_escape_string($conn, $account_no);
  $units_esc       = mysqli_real_escape_string($conn, $actual_units);
  $mon_esc         = mysqli_real_escape_string($conn, $month_applicable);

  // Build SQL value snippets (NULL-safe)
  $sql_from   = $bill_from_date ? "'$bill_from_date'" : "NULL";
  $sql_to     = $bill_to_date   ? "'$bill_to_date'"   : "NULL";
  $sql_bill   = ($bill_amount   !== null) ? $bill_amount   : "NULL";
  $sql_paid   = ($paid_amount   !== null) ? $paid_amount   : "NULL";
  $sql_days   = ($number_of_days!== null) ? $number_of_days: "NULL";
  // total_amount mirrors bill_amount (as CHAR in schema)
  $total_amount = ($bill_amount !== null) ? number_format((float)$bill_amount, 2, '.', '') : null;
  $total_esc    = $total_amount !== null ? "'".mysqli_real_escape_string($conn, $total_amount)."'" : "NULL";

  // Insert / Update
  $sql = "
    INSERT INTO tbl_admin_actual_electricity
      (branch_code, branch, actual_units, total_amount, is_provision, month_applicable,
       account_no, bill_from_date, bill_to_date, bill_amount, number_of_days, paid_amount)
    VALUES
      ('$branch_code_esc', '$branch_esc', '$units_esc', $total_esc, 'no', '$mon_esc',
       '$acc_esc', $sql_from, $sql_to, $sql_bill, $sql_days, $sql_paid)
    ON DUPLICATE KEY UPDATE
      branch = VALUES(branch),
      actual_units = VALUES(actual_units),
      total_amount = VALUES(total_amount),
      account_no = VALUES(account_no),
      bill_from_date = VALUES(bill_from_date),
      bill_to_date   = VALUES(bill_to_date),
      bill_amount    = VALUES(bill_amount),
      number_of_days = VALUES(number_of_days),
      paid_amount    = VALUES(paid_amount)
  ";

  $ok = mysqli_query($conn, $sql);
  if (!$ok) {
    $skipped++;
    $errors[] = "Row $rownum: DB error - " . htmlspecialchars(mysqli_error($conn));
  } else {
    $aff = mysqli_affected_rows($conn);
    if ($aff === 1) $inserted++;
    elseif ($aff === 2) $updated++;
    else $unchanged++; // 0 when duplicate but values identical (depends on server)
  }
}

fclose($fh);

/* ---- Commit & Report ---- */
mysqli_commit($conn);

echo "<div class='alert alert-success'>
        <b>✅ Import completed.</b>
        Inserted: <b>$inserted</b>,
        Updated: <b>$updated</b>,
        Unchanged: <b>$unchanged</b>,
        Skipped: <b>$skipped</b>.
      </div>";

if ($errors) {
  echo "<div class='alert alert-danger'><b>Details:</b><br>" . implode('<br>', $errors) . "</div>";
}
