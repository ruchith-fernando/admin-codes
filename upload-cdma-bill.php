<?php
session_start();

/* ============================= PROCESSOR ============================= */
if (isset($_GET['process'])) {
    // Quiet UI; log details
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/upload-cdma.log');
    ini_set('zlib.output_compression', '0');
    error_reporting(E_ALL);
    set_time_limit(0);
    if (function_exists('gc_enable')) { gc_enable(); }

    ob_start(); // buffer entire response

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=utf-8');

    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            while (ob_get_level()) { @ob_end_clean(); }
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo "<div class='alert alert-danger fw-bold'>Processing failed. Please try again.</div>";
            error_log("FATAL: {$e['message']} in {$e['file']}:{$e['line']}");
        }
    });

    // Autoload + DB (+ optional SR)
    require_once __DIR__ . '/vendor/autoload.php';
    include __DIR__ . '/connections/connection.php'; // $conn (mysqli)
    $HAS_SR = is_file(__DIR__ . '/includes/sr-generator.php');
    if ($HAS_SR) { include __DIR__ . '/includes/sr-generator.php'; }

    /* ---------------- Helpers (original/strict) ---------------- */
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
                return [$month.'-'.$m[3], $line];
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
        if (preg_match('/CONTRACT\s*NUMBER\s*[:\-#]?\s*([A-Za-z0-9\/\-\_]+)/i', $line, $m)) return trim($m[1]);
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
            strpos($u, 'CONTRACT CHARGE') !== false ||
            strpos($u, 'CONTRACT SUMMARY') !== false
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
        if ($c < 11) return $out;
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
    function normalizeFiles($keySingle, $keyMulti) {
        // supports either single "pdf_file" or multi "pdf_files[]"
        $out = [];
        if (isset($_FILES[$keySingle]) && is_uploaded_file($_FILES[$keySingle]['tmp_name'])) {
            $out[] = $_FILES[$keySingle];
            return $out;
        }
        if (isset($_FILES[$keyMulti]) && is_array($_FILES[$keyMulti]['name'])) {
            $f = $_FILES[$keyMulti];
            $count = count($f['name']);
            for ($i=0; $i<$count; $i++){
                if (empty($f['tmp_name'][$i]) || !is_uploaded_file($f['tmp_name'][$i])) continue;
                $out[] = [
                    'name' => $f['name'][$i],
                    'type' => $f['type'][$i],
                    'tmp_name' => $f['tmp_name'][$i],
                    'error' => $f['error'][$i],
                    'size' => $f['size'][$i]
                ];
            }
        }
        return $out;
    }

    /* ---------------- Core processing ---------------- */
    function process_one_pdf($conn, $file, $uploadedBy, $HAS_SR){
        $name = $file['name'];
        $err  = (int)$file['error'];
        $tmp  = $file['tmp_name'];

        if ($err !== UPLOAD_ERR_OK || !$tmp) {
            error_log("UPLOAD ERROR [{$name}] code={$err}");
            return "<div class='result-block' style='border-left:4px solid #dc3545'><div class='fw-bold text-danger'>".htmlspecialchars($name)."</div><div>Upload error.</div></div>";
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($tmp);
            $text   = $pdf->getText();
        } catch (Throwable $e) {
            error_log("PDF parse failed [{$name}]: ".$e->getMessage());
            return "<div class='result-block' style='border-left:4px solid #dc3545'><div class='fw-bold text-danger'>".htmlspecialchars($name)."</div><div>Failed to read PDF.</div></div>";
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        [$Update_date, $billLine] = extractUpdateMonth($lines);
        if (!$Update_date) {
            error_log("[{$name}] BILL PERIOD not detected.");
            // clean up
            unset($pdf, $parser, $text, $lines);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            return "<div class='result-block' style='border-left:4px solid #dc3545'><div class='fw-bold text-danger'>".htmlspecialchars($name)."</div><div>No BILL PERIOD found.</div></div>";
        }
        $bill_date_sql = monthToBillDate($Update_date);
        $origSafe = preg_replace('/[^A-Za-z0-9\.\-\_]+/', '_', basename($name));

        /* Duplicate check without imported-files ledger:
        /* Duplicate check: skip only exact same (month + exact file name) */
        if ($st = $conn->prepare("SELECT COUNT(1) c FROM tbl_admin_cdma_monthly_data WHERE Update_date=? AND file_name=?"
        )) {
            $st->bind_param("ss", $Update_date, $origSafe);
            $st->execute();
            $res = $st->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $exists = ($row && (int)$row['c'] > 0);
            $st->close();

            if ($exists) {
                unset($pdf, $parser, $text, $lines);
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
                return "<div class='result-block' style='border-left:4px solid #ffc107'>
                          <div><b>File:</b> ".htmlspecialchars($name)."</div>
                          <div><b>Billing Month:</b> <code>".htmlspecialchars($Update_date)."</code></div>
                          <div class='mt-2'><span class='fw-bold' style='color:#b26a00'>Skipped:</span> Already imported for this month.</div>
                        </div>";
            }
        }
        // Header
        $html = "<div class='result-block' style='border-left:4px solid #0d6efd'>
                    <div><b>File:</b> ".htmlspecialchars($name)."</div>
                    <div><b>BILL PERIOD line:</b> <code>".htmlspecialchars($billLine)."</code></div>
                    <div><b>Billing Month:</b> <code>".htmlspecialchars($Update_date)."</code></div>
                 </div>";

        // Parse rows strictly
        $inserted=0; $skipped=0; $dupCount=0;
        $currentContract=''; $withinContract=false;
        $contractRows=[]; $contractSubsTotal=0.0; $contractTotalOnPdf=null;

        $flush = function() use (&$conn,&$contractRows,&$contractSubsTotal,&$contractTotalOnPdf,&$inserted,&$skipped,&$dupCount,$Update_date,$bill_date_sql,$HAS_SR,$uploadedBy,$origSafe){
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

                // row dup: (month + contract + subscription)
                if ($ds = $conn->prepare("SELECT id FROM tbl_admin_cdma_monthly_data WHERE Update_date=? AND contract_number=? AND subscription_number=? LIMIT 1")) {
                    $ds->bind_param("sss", $r['Update_date'], $r['contract_number'], $r['subscription_number']);
                    $ds->execute(); $rs = $ds->get_result(); $exists = $rs && $rs->num_rows > 0; $ds->close();
                    if ($exists) {
                        $dupCount++;
                        logSkipped($conn, $r['contract_number'], $r['subscription_number'], 'Duplicate (month+contract+subscription)', $r['Update_date'], $r['file_name']);
                        continue;
                    }
                }

                $newId = insertRowGetId($conn, $r);
                if ($newId === false) { $skipped++; continue; }

                if ($HAS_SR && function_exists('generate_sr_number')) {
                    $sr = generate_sr_number($conn, 'tbl_admin_cdma_monthly_data', $newId);
                    if (!$sr) error_log("SR generation failed for ID $newId");
                }
                $inserted++;
            }

            $contractRows = []; $contractSubsTotal = 0.0; $contractTotalOnPdf = null;
        };

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

            // strict subscription id: 5+ of [A-Za-z0-9/-], must contain a digit
            if (preg_match('/^\s*([A-Za-z0-9\/\-]{5,})\s+(.*)$/', $line, $m)) {
                $subscription = trim($m[1]);
                if (!preg_match('/\d/', $subscription)) continue;

                // accumulate wrapped columns until enough or block ends
                $buffer = $m[2]; $look = $ln + 1;
                while (true) {
                    $na = nums($buffer);
                    if (count($na) >= 11) break;
                    if ($look >= count($lines)) break;
                    $next = trim($lines[$look]);
                    if ($next === '') { $look++; continue; }
                    if (preg_match('/^\s*[A-Za-z0-9\/\-]{5,}\s+/', $next)) break;
                    if (isContractChargesLine($next)) break;
                    if (captureContractNumberContext($lines, $look)) break;
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
        if ($withinContract && $contractRows) { $flush(); }

        // Summary
        $html .= "<div class='alert alert-success fw-bold' style='margin-top:10px;'>✅ Processing Complete —
                    File: <strong>".htmlspecialchars($name)."</strong>,
                    Inserted: <strong>{$inserted}</strong>,
                    Duplicates Skipped (rows): <strong>{$dupCount}</strong>,
                    Other Skipped: <strong>{$skipped}</strong>
                  </div>";

        // cleanup memory aggressively for multi-file runs
        unset($pdf, $parser, $text, $lines);
        if (function_exists('gc_collect_cycles')) gc_collect_cycles();

        return $html;
    }

    /* ---------------- Entry point ---------------- */
    $uploadedBy = $_SESSION['hris'] ?? ($_SESSION['user'] ?? '');
    $files = normalizeFiles('pdf_file', 'pdf_files');

    if (!$files) {
        http_response_code(400);
        echo "<div class='alert alert-danger fw-bold'>Please choose at least one PDF file.</div>";
        $payload = ob_get_clean();
        header('Content-Length: ' . strlen($payload));
        echo $payload;
        exit;
    }

    $out = [];
    foreach ($files as $f) {
        $out[] = process_one_pdf($conn, $f, $uploadedBy, $HAS_SR);
        $out[] = "<div class='hr'></div>";
    }

    echo implode("\n", $out);

    $payload = ob_get_clean();
    header('Content-Length: ' . strlen($payload));
    echo $payload;

    if ($conn && $conn instanceof mysqli) { $conn->close(); }
    exit;
}

/* ============================= PAGE (GET) ============================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Upload CDMA Bill PDF</title>
  <style>
    .card{position:relative;background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
    .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
    .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
    .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
    .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
    .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
    @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
    .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
    .card h5{margin:0 0 16px;color:#0d6efd}.mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
    .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
    .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
    .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
    .btn-outline{background:#fff;border-color:#0d6efd;color:#0d6efd}
    .text-danger{color:#dc3545}.text-success{color:#198754}.fw-bold{font-weight:700}
    .mt-2{margin-top:.5rem}.mt-3{margin-top:1rem}.mt-4{margin-top:1.5rem}
    .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;background:#fafafa}
    .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
    .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
    .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
    .center{display:flex;justify-content:center}
    .file-list{border:1px dashed #cdd6f4;border-radius:10px;padding:10px;background:#f9fbff}
    .file-list ul{margin:0;padding-left:18px}
    .file-list .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
    .pill{display:inline-block;font-size:.8rem;padding:.1rem .5rem;border-radius:999px;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer}
    .pill:hover{background:#f1f5f9}
    .pill-danger{border-color:#fecaca;color:#b91c1c}
    .pill-danger:hover{background:#fff1f2}
    .confirm-panel{display:none;border:1px solid #ffe8a1;background:#fff8e1;border-radius:10px;padding:12px}
    .small-muted{font-size:.9rem;color:#666}
    .hr{height:1px;background:#eceff7;margin:16px 0;border:0}
    .file-row{display:flex;gap:8px;align-items:center}
    .file-name{flex:1}
    .confirm-panel {
        display: none;
        border: 1px solid #ffe8a1;
        background: #fff8e1;
        border-radius: 10px;
        padding: 16px; /* added padding for breathing room */
        box-shadow: 0 4px 8px rgba(0,0,0,0.05); /* subtle shadow */
    }
    #cardLoader {
        position: fixed; /* change from absolute to fixed */
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, .9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999; /* much higher so it overlays everything */
    }

  </style>
</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <div id="cardLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>
      <h5>Upload CDMA Bill PDF</h5>

      <!-- Post back to THIS script -->
      <form id="cdmaUploadForm"
            enctype="multipart/form-data"
            action="<?=htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)?>?process=1"
            method="post" novalidate>
        <div class="mb-3">
          <label class="form-label" for="pdf_files">Choose one or more PDF files</label>
          <input class="form-control" type="file" id="pdf_files" name="pdf_files[]" accept="application/pdf,.pdf" multiple required />
          <div class="mt-2 small-muted">
            Billing month is auto-detected from <b>BILL PERIOD</b>.
          </div>
        </div>

        <div id="selectedFiles" class="file-list" style="display:none;">
          <div class="header">
            <div class="fw-bold">Selected files</div>
            <button type="button" id="removeAllBtn" class="pill pill-danger" title="Remove all files">Remove all</button>
          </div>
          <ul id="fileListUl"></ul>
        </div>

        <div id="confirmPanel" class="confirm-panel mt-3">
          <div class="fw-bold">Ready to upload?</div>
          <div class="small-muted">The listed files will be uploaded and processed.</div>
          <div class="mt-3">
            <button type="button" id="confirmUploadBtn" class="btn btn-success">Confirm Upload</button>
            <button type="button" id="cancelConfirmBtn" class="btn btn-outline" style="margin-left:.5rem;">Cancel</button>
          </div>
        </div>

        <div class="mt-3">
          <button type="button" class="btn btn-success" id="reviewBtn" disabled>Review &amp; Confirm</button>
        </div>

        <div id="uploadProgress" class="progress-wrap">
          <div id="progressBar" class="progress-bar"></div>
          <div id="progressLabel" class="progress-label">Preparing upload…</div>
        </div>
      </form>

      <div id="uploadResult" class="mt-4"></div>
      <div class="center"><button id="clearResultsBtn" type="button" class="btn" style="margin-top:8px;display:none;">Clear Results</button></div>
    </div>
  </div>
</div>

<script>
(function(){
  var form     = document.getElementById('cdmaUploadForm');
  var input    = document.getElementById('pdf_files');
  var review   = document.getElementById('reviewBtn');
  var loader   = document.getElementById('cardLoader');
  var wrap     = document.getElementById('uploadProgress');
  var bar      = document.getElementById('progressBar');
  var label    = document.getElementById('progressLabel');
  var result   = document.getElementById('uploadResult');
  var clearBtn = document.getElementById('clearResultsBtn');

  var selWrap  = document.getElementById('selectedFiles');
  var selList  = document.getElementById('fileListUl');
  var removeAllBtn = document.getElementById('removeAllBtn');

  var confirmPanel = document.getElementById('confirmPanel');
  var confirmBtn   = document.getElementById('confirmUploadBtn');
  var cancelConfirmBtn = document.getElementById('cancelConfirmBtn');

  var uploading = false, selectedFiles = [];

  function showFlex(el){ el.style.display = 'flex'; }
  function showBlock(el){ el.style.display = 'block'; }
  function hide(el){ el.style.display = 'none'; }
  function setBar(p){ bar.style.width = p + '%'; label.textContent = 'Uploading… ' + p + '%'; }
  function resetProgress(){ hide(wrap); bar.style.width = '0%'; label.textContent = ''; }
  function html(s){ result.innerHTML = s; showBlock(clearBtn); }
  function appendHtml(s){ result.insertAdjacentHTML('beforeend', s); showBlock(clearBtn); }
  function msgDanger(s){ appendHtml("<div class='text-danger fw-bold'>" + s + "</div>"); }
  function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function humanSize(bytes){ if(bytes===0) return '0 B'; var k=1024, sizes=['B','KB','MB','GB','TB']; var i=Math.floor(Math.log(bytes)/Math.log(k)); return (bytes/Math.pow(k,i)).toFixed(i?1:0)+' '+sizes[i]; }

  clearBtn.addEventListener('click', function(){ result.innerHTML=''; hide(clearBtn); });

  input.addEventListener('change', function(){
    selectedFiles = Array.prototype.slice.call(input.files || []);
    renderList(); hide(confirmPanel);
  });

  function renderList(){
    selList.innerHTML='';
    if(!selectedFiles.length){ hide(selWrap); review.disabled=true; return; }
    var items=[];
    for(var i=0;i<selectedFiles.length;i++){
      var f=selectedFiles[i];
      items.push("<li><div class='file-row'><span class='file-name'>"+escapeHtml(f.name)+" <span class='small-muted'>("+humanSize(f.size)+")</span></span><button type='button' class='pill pill-danger rm-btn' data-index='"+i+"'>Remove</button></div></li>");
    }
    selList.innerHTML=items.join(''); showBlock(selWrap); review.disabled=false;
  }

  selList.addEventListener('click', function(e){
    if(!e.target || !e.target.classList.contains('rm-btn')) return;
    var idx=+e.target.getAttribute('data-index');
    if(idx>=0 && idx<selectedFiles.length){ selectedFiles.splice(idx,1); rebuildInputFromSelected(); renderList(); if(!selectedFiles.length) hide(confirmPanel); }
  });

  removeAllBtn.addEventListener('click', function(){ selectedFiles=[]; rebuildInputFromSelected(); renderList(); hide(confirmPanel); });

  function rebuildInputFromSelected(){ var dt=new DataTransfer(); selectedFiles.forEach(f=>dt.items.add(f)); input.files=dt.files; }

  review.addEventListener('click', function(){
    if(!selectedFiles.length){ msgDanger('Please select at least one PDF.'); return; }
    showBlock(confirmPanel); try{ confirmPanel.scrollIntoView({behavior:'smooth', block:'center'});}catch(e){}
  });

  cancelConfirmBtn.addEventListener('click', function(){ hide(confirmPanel); });

  confirmBtn.addEventListener('click', function(){
    if(uploading) return;
    if(!selectedFiles.length){ msgDanger('Please select at least one PDF.'); return; }
    // Queue: upload ONE FILE PER REQUEST (fixes “only first 1–2 saved”)
    result.innerHTML=''; showBlock(clearBtn);
    doQueueUpload(selectedFiles.slice());
  });

  function doQueueUpload(queue){
    if(!queue.length){ return; }
    uploading = true;

    showFlex(loader); showBlock(wrap); label.textContent='Uploading…';
    review.disabled=true; confirmBtn.disabled=true; input.disabled=true;

    var total = queue.length, done = 0;

    function next(){
      if(!queue.length){
        hide(loader); uploading=false; setTimeout(resetProgress,600);
        input.disabled=false; confirmBtn.disabled=false;
        return;
      }
      var f = queue.shift();
      var fd = new FormData();
      fd.append('pdf_file', f); // IMPORTANT: single-file key

      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);

      if(xhr.upload){
        xhr.upload.addEventListener('progress', function(e){
          if(e.lengthComputable){
            // progress by file count (simple & robust)
            var p = Math.round(((done + e.loaded/e.total)/total)*100);
            setBar(p);
          }
        });
      }

      xhr.onreadystatechange = function(){
        if(xhr.readyState===4){
          done++;
          if(xhr.status>=200 && xhr.status<300){
            appendHtml(xhr.responseText || '');
          } else {
            msgDanger('Upload failed for '+escapeHtml(f.name));
          }
          // advance
          next();
        }
      };

      xhr.onerror = function(){
        done++;
        msgDanger('Network error for '+escapeHtml(f.name));
        next();
      };

      xhr.send(fd);
    }

    next();
  }
})();
</script>
</body>
</html>
