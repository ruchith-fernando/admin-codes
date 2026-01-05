<?php
// process-cdma-bill.php — SINGLE FILE (no disk save; month+file duplicate via ledger if present; X/Y/Z; SR; logs)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-cdma.log');
error_reporting(E_ALL);
set_time_limit(0);

// Friendly fatal handler
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo "<div class='alert alert-danger fw-bold'>Server error while processing the PDF. Check <code>upload-cdma.log</code>.</div>";
        error_log("FATAL: {$e['message']} in {$e['file']}:{$e['line']}");
    }
});

// PDF parser
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo "<div class='alert alert-danger fw-bold'>Composer autoloader missing. Run: <code>composer require smalot/pdfparser</code></div>";
    exit;
}
require_once $autoload;
if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
    http_response_code(500);
    echo "<div class='alert alert-danger fw-bold'>PDF parser not loaded. Ensure <code>smalot/pdfparser</code> is installed.</div>";
    exit;
}
use Smalot\PdfParser\Parser;

// DB + SR
include __DIR__ . '/connections/connection.php'; // $conn (mysqli)
if (!$conn || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo "<div class='alert alert-danger fw-bold'>Database connection not available.</div>";
    exit;
}
$HAS_SR = is_file(__DIR__ . '/includes/sr-generator.php');
if ($HAS_SR) { include __DIR__ . '/includes/sr-generator.php'; }

/* ---------------- Helpers ---------------- */
function amt($v){
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return 0.00;
    $neg = false;
    if (preg_match('/^\((.*)\)$/', $v, $m)) { $v = $m[1]; $neg = true; }
    if (isset($v[0]) && $v[0] === '-') { $neg = true; $v = ltrim($v, '-'); }
    $v = str_replace([',',' '], '', $v);
    if (!is_numeric($v)) return 0.00;
    $n = (float)$v;
    return $neg ? -$n : $n;
}
function nums($s){
    preg_match_all('/\(?-?\d{1,3}(?:,\d{3})*(?:\.\d+)?\)?|\(?-?\d+(?:\.\d+)?\)?/', $s, $m);
    return $m[0] ?? [];
}
function extractUpdateMonth(array $lines){
    foreach ($lines as $line) {
        if (stripos($line, 'BILL PERIOD') !== false &&
            preg_match('/\d{2}\/\d{2}\/\d{4}\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/', $line, $m)) {
            $month = date('F', mktime(0,0,0,(int)$m[2], 1));
            return [$month.'-'.$m[3], $line]; // e.g., May-2025
        }
    }
    return ['', ''];
}
function monthToBillDate($updateDateStr){
    $dt = DateTime::createFromFormat('F-Y', $updateDateStr);
    if (!$dt) { $dt = DateTime::createFromFormat('M-Y', $updateDateStr); }
    return $dt ? $dt->format('Y-m-01') : null;
}
function captureContractNumber($line){
    if (preg_match('/SUMMARY\s+CONTRACT\s+NUMBER\s*:\s*([A-Za-z0-9\/\-\_]+)/i', $line, $m)) return trim($m[1]);
    if (preg_match('/CONTRACT\s*NUMBER\s*[:\-]?\s*([A-Za-z0-9\/\-\_]+)/i', $line, $m)) return trim($m[1]);
    if (preg_match('/CONTRACT\s*NO\.?\s*[:\-]?\s*([A-Za-z0-9\/\-\_]+)/i', $line, $m)) return trim($m[1]);
    return '';
}
function captureContractNumberContext(array $lines, $idx){
    $cn = captureContractNumber($lines[$idx]); if ($cn) return $cn;
    if (!empty($lines[$idx+1])) {
        $cn = captureContractNumber(trim($lines[$idx].' '.$lines[$idx+1]));
        if ($cn) return $cn;
    }
    return '';
}
function isContractChargesLine($line){
    $u = strtoupper($line);
    return (
        strpos($u, 'CONTRACT CHARGES') !== false ||
        strpos($u, 'TOTAL CONTRACT CHARGES') !== false ||
        strpos($u, 'CHARGES FOR CONTRACT') !== false ||
        strpos($u, 'CONTRACT CHARGE') !== false
    );
}
function extractContractChargesRowTotal(array $lines, $startIndex){
    $limit = min($startIndex + 20, count($lines) - 1);
    $buffer = '';
    for ($i = $startIndex + 1; $i <= $limit; $i++) {
        $line = trim($lines[$i]);
        if ($line === '') continue;
        if (stripos($line, 'Total Charges for Bill Period') !== false ||
            stripos($line, 'GRAND TOTAL') !== false) {
            break;
        }
        $buffer = ($buffer === '') ? $line : ($buffer . ' ' . $line);
        $na = nums($buffer);
        if (count($na) >= 4) {
            return amt(end($na));
        }
    }
    return null;
}
function mapSubscriptionNumbers(array $nums){
    $c = count($nums);
    $out = [
        'rental'=>0,'local_calls'=>0,'national_calls'=>0,'mobile_calls'=>0,'sms'=>0,'idd'=>0,
        'value_added_services'=>0,'discounts'=>0,'late_payment_charges'=>0,
        'government_taxes_levies'=>0,'vat'=>0,'add_to_bill_non_taxable'=>0,'total'=>0
    ];
    if ($c < 2) return $out;
    $out['total'] = amt($nums[$c-1]);

    if ($c >= 13) {
        $out['rental']=amt($nums[1]); $out['local_calls']=amt($nums[2]); $out['national_calls']=amt($nums[3]);
        $out['mobile_calls']=amt($nums[4]); $out['sms']=amt($nums[5]); $out['idd']=amt($nums[6]);
        $out['value_added_services']=amt($nums[7]); $out['discounts']=amt($nums[8]); $out['late_payment_charges']=amt($nums[9]);
        $out['government_taxes_levies']=amt($nums[10]); $out['vat']=amt($nums[11]); $out['add_to_bill_non_taxable']=amt($nums[12]);
        return $out;
    }
    if ($c == 12) {
        $out['rental']=amt($nums[1]); $out['local_calls']=amt($nums[2]); $out['national_calls']=amt($nums[3]);
        $out['mobile_calls']=amt($nums[4]); $out['sms']=amt($nums[5]); $out['idd']=amt($nums[6]);
        $out['value_added_services']=amt($nums[7]); $out['discounts']=amt($nums[8]); $out['late_payment_charges']=amt($nums[9]);
        return $out;
    }
    // c == 11
    $out['rental']=amt($nums[1]); $out['local_calls']=amt($nums[2]); $out['national_calls']=amt($nums[3]);
    $out['mobile_calls']=amt($nums[4]); $out['sms']=amt($nums[5]); $out['idd']=amt($nums[6]);
    $out['value_added_services']=amt($nums[7]); $out['discounts']=amt($nums[8]); $out['late_payment_charges']=amt($nums[9]);
    return $out;
}
function logSkipped($conn, $contract, $sub, $reason, $update_date, $file){
    $sql = "INSERT INTO tbl_admin_cdma_skipped (contract_number, subscription_number, reason, Update_date, file_name)
            VALUES (?,?,?,?,?)";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param("sssss", $contract, $sub, $reason, $update_date, $file);
        $st->execute(); $st->close();
    } else {
        error_log("SKIP [$file][$update_date][$contract][$sub]: $reason");
    }
}
function insertRowGetId($conn, $row){
    $sql = "INSERT INTO tbl_admin_cdma_monthly_data (
      contract_number, subscription_number, service_type,
      rental, local_calls, national_calls, mobile_calls, sms, idd,
      value_added_services, discounts, late_payment_charges,
      government_taxes_levies, vat, add_to_bill_non_taxable, total,
      tax_calculation, total_amount, tax_factor, contract_total_on_pdf,
      contract_subs_total, bill_date, Update_date, file_name, page_no, uploaded_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $st = $conn->prepare($sql);
    if (!$st) { error_log("Prepare failed: ".$conn->error); return false; }
    $types = 'sss' . str_repeat('d', 18) . 'sss' . 'i' . 's';
    $st->bind_param(
        $types,
        $row['contract_number'], $row['subscription_number'], $row['service_type'],
        $row['rental'], $row['local_calls'], $row['national_calls'], $row['mobile_calls'], $row['sms'], $row['idd'],
        $row['value_added_services'], $row['discounts'], $row['late_payment_charges'],
        $row['government_taxes_levies'], $row['vat'], $row['add_to_bill_non_taxable'], $row['total'],
        $row['tax_calculation'], $row['total_amount'], $row['tax_factor'], $row['contract_total_on_pdf'],
        $row['contract_subs_total'], $row['bill_date'], $row['Update_date'], $row['file_name'],
        $row['page_no'], $row['uploaded_by']
    );
    $ok = $st->execute();
    if (!$ok) { error_log("Insert error: " . $st->error); $st->close(); return false; }
    $newId = $st->insert_id;
    $st->close();
    return $newId;
}

/* ---------------- Validate upload (single file) ---------------- */
if (!isset($_FILES['pdf_file']) || !is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
    http_response_code(400);
    echo "<div class='alert alert-danger fw-bold'>Please choose a PDF file.</div>";
    exit;
}
$err  = (int)$_FILES['pdf_file']['error'];
$tmp  = $_FILES['pdf_file']['tmp_name'];
$name = $_FILES['pdf_file']['name'];
if ($err !== UPLOAD_ERR_OK || !$tmp) {
    echo "<div class='alert alert-danger'><b>".htmlspecialchars($name)."</b>: Upload error code <code>$err</code>.</div>";
    error_log("UPLOAD ERROR [{$name}] code={$err}");
    exit;
}

$uploadedBy = $_SESSION['hris'] ?? ($_SESSION['user'] ?? '');

/* ---------------- Parse to get month ---------------- */
try {
    $parser = new Parser();
    $pdf    = $parser->parseFile($tmp);
    $text   = $pdf->getText();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>".htmlspecialchars($name)."</b>: Failed to parse PDF.</div>";
    error_log("PDF parse failed [{$name}]: ".$e->getMessage());
    exit;
}

$lines = preg_split('/\r\n|\r|\n/', $text);
[$Update_date, $billLine] = extractUpdateMonth($lines);
if (!$Update_date) {
    echo "<div class='alert alert-danger'><b>".htmlspecialchars($name)."</b>: Could not detect BILL PERIOD month.</div>";
    error_log("[{$name}] BILL PERIOD not detected.");
    exit;
}
$bill_date_sql = monthToBillDate($Update_date);
$origSafe = preg_replace('/[^A-Za-z0-9\.\-\_]+/', '_', basename($name));

/* ---------------- month+file duplicate check ---------------- */
// Prefer ledger if present; else fall back to data-table scan.
$ledgerExists = false;
if ($q = $conn->query("SHOW TABLES LIKE 'tbl_admin_cdma_imported_files'")) {
    $ledgerExists = ($q->num_rows > 0);
    $q->close();
}

$ledgerId = null; $ledgerInserted = false;
if ($ledgerExists) {
    if ($st = $conn->prepare("INSERT INTO tbl_admin_cdma_imported_files (Update_date, original_file_name, uploaded_by, bill_period_line) VALUES (?,?,?,?)")) {
        $st->bind_param("ssss", $Update_date, $origSafe, $uploadedBy, $billLine);
        if (!$st->execute()) {
            if ($st->errno == 1062) {
                echo "<div class='alert alert-warning'><b>".htmlspecialchars($name)."</b>: Skipped. Same original file <code>".htmlspecialchars($origSafe)."</code> already imported for <b>".htmlspecialchars($Update_date)."</b>.</div>";
                error_log("LEDGER DUP SKIP: month={$Update_date}, file={$origSafe}");
                $st->close();
                exit;
            } else {
                echo "<div class='alert alert-danger'>Ledger insert failed for <b>".htmlspecialchars($name)."</b>. See logs.</div>";
                error_log("Ledger insert failed [{$name}]: ".$st->error);
                $st->close();
                exit;
            }
        } else {
            $ledgerInserted = true;
            $ledgerId = $st->insert_id;
            $st->close();
        }
    } else {
        echo "<div class='alert alert-danger'>Ledger prepare failed. See logs.</div>";
        error_log("Ledger prepare failed: ".$conn->error);
        exit;
    }
} else {
    // Fallback: check in data table (also covers legacy timestamp+name patterns)
    if ($st = $conn->prepare("SELECT COUNT(1) c FROM tbl_admin_cdma_monthly_data WHERE Update_date=? AND (file_name=? OR file_name LIKE ?)")) {
        $likeTail = "%_".$origSafe;
        $st->bind_param("sss", $Update_date, $origSafe, $likeTail);
        $st->execute(); $res = $st->get_result(); $row = $res ? $res->fetch_assoc() : null;
        $exists = ($row && (int)$row['c'] > 0); $st->close();
        if ($exists) {
            echo "<div class='alert alert-warning'><b>".htmlspecialchars($name)."</b>: Skipped. Same original file <code>".htmlspecialchars($origSafe)."</code> already imported for <b>".htmlspecialchars($Update_date)."</b>.</div>";
            error_log("FALLBACK DUP SKIP: month={$Update_date}, file={$origSafe}");
            exit;
        }
    } else {
        error_log("Fallback dup-check prepare failed: ".$conn->error);
    }
}

/* ---------------- Show detected info (no disk save) ---------------- */
echo "<div class='result-block' style='border-left:4px solid #0d6efd'>
        <div><b>File:</b> ".htmlspecialchars($name)."</div>
        <div><b>BILL PERIOD line:</b> <code>".htmlspecialchars($billLine)."</code></div>
        <div><b>Billing Month:</b> <code>".htmlspecialchars($Update_date)."</code></div>
      </div>";

/* ---------------- Parse rows (contracts) ---------------- */
$inserted=0; $skipped=0; $dupCount=0; $contractsDone=0;
$currentContract=''; $withinContract=false;
$contractRows=[]; $contractSubsTotal=0.0; $contractTotalOnPdf=null;

$flush = function() use (&$conn,&$contractRows,&$contractSubsTotal,&$contractTotalOnPdf,&$inserted,&$skipped,&$dupCount,&$contractsDone,$Update_date,$bill_date_sql,$HAS_SR,$uploadedBy,$origSafe){
    if (!$contractRows) return;

    $X = (float)$contractSubsTotal;
    $Y = is_null($contractTotalOnPdf) ? null : (float)$contractTotalOnPdf;
    $Z = (!is_null($Y) && $X != 0.0) ? round($Y / $X, 10) : null;

    foreach ($contractRows as $r) {
        $rowTotal = (float)$r['total'];
        $taxCalc  = ($Z !== null) ? round($Z * $rowTotal, 2) : 0.00;
        $totalAmt = round($rowTotal + $taxCalc, 2);

        $r['tax_calculation'] = $taxCalc;
        $r['total_amount']    = $totalAmt;
        $r['tax_factor']      = ($Z !== null) ? $Z : 0.0;
        $r['contract_total_on_pdf'] = $Y ?? 0.0;
        $r['contract_subs_total']   = $X;

        $r['Update_date'] = $Update_date;
        $r['bill_date']   = $bill_date_sql;

        // row-level duplicate (month+contract+subscription)
        if ($ds = $conn->prepare("SELECT id FROM tbl_admin_cdma_monthly_data WHERE Update_date=? AND contract_number=? AND subscription_number=? LIMIT 1")) {
            $ds->bind_param("sss", $r['Update_date'], $r['contract_number'], $r['subscription_number']);
            $ds->execute(); $rs = $ds->get_result(); $exists = $rs && $rs->num_rows > 0; $ds->close();
            if ($exists) {
                $GLOBALS['dupCount']++;
                logSkipped($conn, $r['contract_number'], $r['subscription_number'], 'Duplicate (month+contract+subscription)', $r['Update_date'], $r['file_name']);
                continue;
            }
        } else {
            error_log("Row-dup check prepare failed: ".$conn->error);
        }

        $newId = insertRowGetId($conn, $r);
        if ($newId === false) { $GLOBALS['skipped']++; continue; }

        if ($GLOBALS['HAS_SR'] && function_exists('generate_sr_number')) {
            $sr = generate_sr_number($conn, 'tbl_admin_cdma_monthly_data', $newId);
            if (!$sr) error_log("SR generation failed for ID $newId");
        }
        $GLOBALS['inserted']++;
    }

    // reset for next contract
    $contractRows = []; $contractSubsTotal = 0.0; $contractTotalOnPdf = null; $contractsDone++;
};

// line-by-line parse
for ($ln = 0; $ln < count($lines); $ln++) {
    $line = trim($lines[$ln]);
    if ($line === '') continue;

    $cn = captureContractNumberContext($lines, $ln);
    if ($cn) {
        if ($withinContract) $flush();
        $currentContract = $cn;
        $withinContract  = true;
        continue;
    }
    if (!$withinContract) continue;

    if (isContractChargesLine($line)) {
        $contractTotalOnPdf = extractContractChargesRowTotal($lines, $ln);
        $flush();
        $withinContract = false; $currentContract = '';
        continue;
    }

    if (preg_match('/^\s*([A-Za-z0-9\/\-]{5,})\s+(.*)$/', $line, $m)) {
        $subscription = trim($m[1]);
        if (!preg_match('/\d/', $subscription)) continue;

        // accumulate wrapped lines until we have enough numerics
        $buffer = $m[2]; $look = $ln + 1;
        while (true) {
            $na = nums($buffer);
            if (count($na) >= 11) break;
            if ($look >= count($lines)) break;

            $next = trim($lines[$look]);
            if ($next === '') { $look++; continue; }
            if (preg_match('/^\s*[A-Za-z0-9\/\-]{5,}\s+/', $next)) break; // next subscription
            if (isContractChargesLine($next)) break;                      // end block
            if (captureContractNumberContext($lines, $look)) break;       // next contract

            $buffer .= ' ' . $next; $look++;
        }

        $na = nums($buffer);
        if (count($na) < 11) {
            $skipped++;
            logSkipped($conn, $currentContract ?: 'UNKNOWN', $subscription, 'Malformed subscription row (insufficient numeric columns)', $Update_date, $origSafe);
            continue;
        }

        // service_type = text up to first number
        $serviceType = '';
        if (preg_match('/\(?-?\d{1,3}(?:,\d{3})*(?:\.\d+)?\)?|\(?-?\d+(?:\.\d+)?\)?/', $buffer, $fm, PREG_OFFSET_CAPTURE)) {
            $serviceType = trim(substr($buffer, 0, $fm[0][1]));
        }

        $mapped = mapSubscriptionNumbers(array_values($na));
        $contractSubsTotal += (float)$mapped['total'];

        $contractRows[] = [
            'contract_number'         => $currentContract ?: 'UNKNOWN',
            'subscription_number'     => $subscription,
            'service_type'            => $serviceType,
            'rental'                  => $mapped['rental'],
            'local_calls'             => $mapped['local_calls'],
            'national_calls'          => $mapped['national_calls'],
            'mobile_calls'            => $mapped['mobile_calls'],
            'sms'                     => $mapped['sms'],
            'idd'                     => $mapped['idd'],
            'value_added_services'    => $mapped['value_added_services'],
            'discounts'               => $mapped['discounts'],
            'late_payment_charges'    => $mapped['late_payment_charges'],
            'government_taxes_levies' => $mapped['government_taxes_levies'],
            'vat'                     => $mapped['vat'],
            'add_to_bill_non_taxable' => $mapped['add_to_bill_non_taxable'],
            'total'                   => $mapped['total'],

            'tax_calculation'         => 0.00,
            'total_amount'            => 0.00,
            'tax_factor'              => 0.0,
            'contract_total_on_pdf'   => 0.0,
            'contract_subs_total'     => 0.0,

            'bill_date'               => $bill_date_sql,
            'Update_date'             => $Update_date,
            'file_name'               => $origSafe,
            'page_no'                 => 0,
            'uploaded_by'             => $uploadedBy
        ];

        if ($look > $ln + 1) $ln = $look - 1;
    }
}
// If file ended mid-contract, flush
if ($withinContract && $contractRows) { $flush(); }

/* ---------------- Summary + ledger stats ---------------- */
echo "<div class='alert alert-success fw-bold'>✅ Processing Complete —
        File: <strong>".htmlspecialchars($name)."</strong>,
        Inserted: <strong>{$inserted}</strong>,
        Duplicates Skipped (rows): <strong>{$dupCount}</strong>,
        Other Skipped: <strong>{$skipped}</strong>
      </div>";

if ($ledgerExists && $ledgerInserted && $st = $conn->prepare("UPDATE tbl_admin_cdma_imported_files SET rows_inserted=?, row_dups=?, row_skipped=? WHERE id=?")) {
    $st->bind_param("iiii", $inserted, $dupCount, $skipped, $ledgerId);
    $st->execute(); $st->close();
}

if ($conn && $conn instanceof mysqli) { $conn->close(); }
