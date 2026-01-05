<?php
// working code
ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

/* ---------- constant tag ---------- */
if (!defined('PROCESSING_TAG')) {
    define('PROCESSING_TAG', 'TAL-NODE');
}

/* Always JSON, even on fatals */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal: '.$e['message'],
            'tag'     => PROCESSING_TAG
        ], JSON_UNESCAPED_UNICODE);
    }
});
set_error_handler(function ($sev,$msg,$file,$line) {
    if (!(error_reporting() & $sev)) return;
    throw new ErrorException($msg, 0, $sev, $file, $line);
});
header_remove('Content-Type');

/* ---------- helpers ---------- */
function json_error($msg){
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success'=>false,'error'=>$msg,'tag'=>PROCESSING_TAG], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok($data){
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success'=>true,'tag'=>PROCESSING_TAG]+$data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generate_temp_sr(): string {
    try { $r = bin2hex(random_bytes(3)); }
    catch(Throwable $e){ $r = substr(str_replace(['.',' '],'',microtime(true)),-6); }
    return 'SR-'.date('Ymd-His').'-'.strtoupper($r);
}
function norm_space($s){ return trim(preg_replace('/\s+/u',' ', (string)$s)); }
function nodeText($n){ return strtolower(norm_space($n->textContent ?? '')); }
function parseAmount($raw){
    $s = html_entity_decode(trim((string)$raw), ENT_QUOTES|ENT_HTML5);
    $s = str_replace(["\xC2\xA0","\xA0",","," "], "", $s);
    if ($s==='') return 0.0;
    $neg=false;
    if (preg_match('/^\((.*)\)$/',$s,$m)){ $neg=true; $s=$m[1]; }
    if (isset($s[0]) && $s[0]==='-'){ $neg=true; $s=substr($s,1); }
    if (!preg_match('/^\d+(\.\d+)?$/',$s)) return 0.0;
    $v=(float)$s; return $neg? -$v : $v;
}
function looks_like_connection_label(string $text): bool {
    $raw = norm_space($text); if ($raw==='') return false;
    $low = strtolower($raw);
    foreach (['invoice','account','customer','bill period','date of account','bill period:'] as $w){
        if (str_contains($low, $w)) return false;
    }
    return preg_match('/^(?:0[\d\s-]{7,}|94[\d\s-]{7,}|cen[\w-]+)/i', $raw) === 1;
}
/* call-detail table? */
function isCallDetailTable(DOMNode $tbl): bool {
    $t = nodeText($tbl);
    $hasDial = str_contains($t,'dialled no') || str_contains($t,'dialed no') || str_contains($t,'dialled');
    $hasDur  = str_contains($t,'duration');
    $hasDate = str_contains($t,'date');
    $hasTime = str_contains($t,'time');
    return ($hasDial && $hasDur) || ($hasDate && $hasTime && $hasDur);
}

/* Bill period, from text or label:value rows */
function extractBillPeriodSmart(string $plain, DOMXPath $xp){
    $p = strtolower(preg_replace('/\s+/',' ', $plain));
    $date='([0-3]?\d[\/\.\-][01]?\d[\/\.\-]\d{4})'; $dash='[-–—]';
    if (preg_match("/bill\s*period[:\s]*{$date}\s*{$dash}\s*{$date}/iu",$p,$m)){
        $s=str_replace(['-','.'],'/',trim($m[1])); $e=str_replace(['-','.'],'/',trim($m[2]));
        $ds=DateTime::createFromFormat('d/m/Y',$s); $de=DateTime::createFromFormat('d/m/Y',$e);
        if ($ds && $de) return ['text'=>trim($m[1]).' - '.trim($m[2]), 'start'=>$ds->format('Y-m-d'), 'end'=>$de->format('Y-m-d')];
    }
    $rows=$xp->query("//tr[th[contains(translate(normalize-space(string(.)),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'bill period')] or td[contains(translate(normalize-space(string(.)),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'bill period')]]");
    foreach($rows as $tr){
        $tds=$tr->getElementsByTagName('td');
        $val = $tds->length>=2 ? norm_space($tds->item(1)->textContent) : norm_space($tr->textContent);
        if (preg_match("/{$date}\s*{$dash}\s*{$date}/u",$val,$m)){
            $s=str_replace(['-','.'],'/',trim($m[1])); $e=str_replace(['-','.'],'/',trim($m[2]));
            $ds=DateTime::createFromFormat('d/m/Y',$s); $de=DateTime::createFromFormat('d/m/Y',$e);
            if ($ds && $de) return ['text'=>trim($m[1]).' - '.trim($m[2]), 'start'=>$ds->format('Y-m-d'), 'end'=>$de->format('Y-m-d')];
        }
    }
    return null;
}

/* Simple tax code detector */
function tax_code_of(string $label): ?string {
    $l = strtolower(norm_space($label));
    if (str_contains($l,'recovery in lieu of sscl') || str_contains($l,'sscl')) return 'SSCL';
    if (str_contains($l,'telecommunication levy') || str_contains($l,'levy')) return 'LEVY15';
    if (str_contains($l,'vat')) return 'VAT18';
    if (str_contains($l,'cess')) return 'VAT_CESS';
    return null;
}

/* Labels to exclude from connection sums */
function is_excluded_connection_row_label(string $rowTextLower): bool {
    $t = preg_replace('/\s+/', ' ', trim($rowTextLower));
    $needles = [
        'usage charge',
        'usage charges',
        'payments during the period',
        'sub total',
        'subtotal',
        'total charges for the period',
        'total charges for the period in rs',
        'parity rate'
    ];
    foreach ($needles as $n) {
        if (str_contains($t, $n)) return true;
    }
    return false;
}

/* ---------- parser ---------- */
function parseBillFile($filePath){
    if (!class_exists('DOMDocument')) throw new Exception('PHP DOM extension is not enabled.');
    $raw = file_get_contents($filePath); if ($raw===false) throw new Exception('Unable to read uploaded file.');
    $utf8 = @mb_convert_encoding($raw,'UTF-8','Windows-1252, ISO-8859-1, UTF-8');
    $plain = strip_tags($utf8);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($utf8);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $period = extractBillPeriodSmart($plain, $xp);
    if (!$period) throw new Exception("Bill Period not found.");

    // Collect tables in document order
    $tables = $xp->query('//table');
    if (!$tables || $tables->length===0) throw new Exception("No tables found.");

    // Find the LAST table that clearly contains "Discounts:"
    $lastDiscIdx = -1;
    for ($i=0; $i<$tables->length; $i++){
        $tbl = $tables->item($i);
        $txt = nodeText($tbl);
        if (preg_match('/\bdiscounts\s*:/i', $txt)) {
            $lastDiscIdx = $i;
        }
    }

    // Walk tables in order
    $afterCharges = false;
    $connections = [];
    $currentConn = null;
    $blockSum = 0.0;
    $ignoreBlock = false;

    $discSum = 0.0;
    $lastTax = [];

    for ($i=0; $i<$tables->length; $i++){
        $tbl = $tables->item($i);

        if (!$afterCharges) {
            $t = nodeText($tbl);
            if (str_contains($t,'charges in detail')) { $afterCharges = true; continue; }
        }

        // Skip call-detail grids
        if (isCallDetailTable($tbl)) continue;

        /* -------- DISCOUNTS: use ONLY the LAST "Discounts:" table -------- */
        if ($i == $lastDiscIdx) {
            foreach ($tbl->getElementsByTagName('tr') as $tr){
                $tds = $tr->getElementsByTagName('td');
                if ($tds->length === 0) continue;

                // Build label from all but last td
                $label = '';
                for ($k=0; $k<$tds->length-1; $k++){
                    $label .= ' '.norm_space($tds->item($k)->textContent);
                }
                $label = trim($label);
                $labelLow = strtolower($label);

                // Skip blank and any "total" line to avoid double-counting
                if ($labelLow === '' || str_contains($labelLow,'total')) continue;

                $amt = parseAmount($tds->item($tds->length-1)->textContent ?? '');
                if ($amt == 0.0) continue;
                $discSum += $amt;   // keep sign; abs() after loop
            }
        }
        // If no "Discounts:" table at all, fall back to summing any rows that look like discounts
        elseif ($lastDiscIdx < 0) {
            foreach ($tbl->getElementsByTagName('tr') as $tr){
                $tds = $tr->getElementsByTagName('td'); if ($tds->length<2) continue;
                $label = '';
                for ($k=0; $k<$tds->length-1; $k++){ $label .= ' '.norm_space($tds->item($k)->textContent); }
                $label = trim($label);
                $labLow = strtolower($label);
                if (!str_contains($labLow,'discount') && !str_contains($labLow,'waive')) continue;
                if (str_contains($labLow,'total')) continue;
                $amt = parseAmount($tds->item($tds->length-1)->textContent ?? '');
                if ($amt == 0.0) continue;
                $discSum += $amt;
            }
        }

        /* -------- TAXES: keep LAST occurrence per code -------- */
        foreach ($tbl->getElementsByTagName('tr') as $tr){
            $tds = $tr->getElementsByTagName('td'); if ($tds->length<2) continue;
            $label = '';
            for ($k=0; $k<$tds->length-1; $k++){ $label .= ' '.norm_space($tds->item($k)->textContent); }
            $label = trim($label);
            $code = tax_code_of($label); if (!$code) continue;
            $amt = parseAmount($tds->item($tds->length-1)->textContent ?? '');
            if ($amt == 0.0) continue;
            $lastTax[$code] = $amt; // last wins
        }

        /* -------- CONNECTIONS (ONLY tables above Discounts) -------- */
        // If a Discounts table exists, process ONLY tables with index < lastDiscIdx
        if ($lastDiscIdx >= 0 && $i >= $lastDiscIdx) {
            continue; // everything at/after Discounts is ignored for connections
        }

        $parseConnectionsHere = $afterCharges || $lastDiscIdx<0;
        if ($parseConnectionsHere){
            foreach ($tbl->getElementsByTagName('tr') as $tr){
                $tds = $tr->getElementsByTagName('td');

                // Detect connection header
                $candidates=[];
                foreach (['b','strong'] as $tg){ foreach($tr->getElementsByTagName($tg) as $el){ $candidates[] = $el->textContent; } }
                foreach ($tr->getElementsByTagName('span') as $sp){
                    $st = strtolower($sp->getAttribute('style'));
                    if (str_contains($st,'font-weight') && str_contains($st,'bold')) $candidates[] = $sp->textContent;
                }
                if ($tds->length>0) $candidates[] = $tds->item(0)->textContent;
                if ($tds->length>1) $candidates[] = $tds->item(1)->textContent;

                $newConn = null;
                foreach ($candidates as $cand){
                    if (looks_like_connection_label($cand)) { $newConn = norm_space($cand); break; }
                }
                if ($newConn !== null){
                    // finalize previous block (only if we were counting it)
                    if ($currentConn !== null && !$ignoreBlock){
                        $final = $blockSum;
                        if (abs($final) > 0.0000005){
                            $connections[$currentConn] = ($connections[$currentConn] ?? 0.0) + $final;
                        }
                    }
                    // start new block; if we already saved this connection, skip this whole block
                    $currentConn = $newConn;
                    $blockSum = 0.0;
                    $ignoreBlock = array_key_exists($currentConn, $connections);
                    continue;
                }

                // Inside a connection block
                if ($currentConn !== null && !$ignoreBlock){
                    $rowText = '';
                    for ($k=0; $k<$tds->length-1; $k++){ $rowText .= ' '.norm_space($tds->item($k)->textContent); }
                    $rowText = strtolower(trim($rowText));

                    // Skip excluded connection labels entirely
                    if ($rowText === '' || is_excluded_connection_row_label($rowText)) {
                        continue;
                    }

                    // Skip obvious non-charge rows
                    if (preg_match('/^(date|time|dial)/',$rowText)){
                        continue;
                    }

                    // Parse amount from last cell
                    $last = $tds->length ? $tds->item($tds->length-1) : null;
                    $amtStr = $last ? $last->textContent : '';
                    $amt    = parseAmount($amtStr);

                    if ($amt != 0.0){
                        $blockSum += $amt;
                    }
                }
            }
        }
    }

    // finalize last connection (only if not skipped)
    if ($currentConn !== null && !$ignoreBlock){
        $final = $blockSum;
        if (abs($final) > 0.0000005){
            $connections[$currentConn] = ($connections[$currentConn] ?? 0.0) + $final;
        }
    }

    // Clean zero connections
    foreach ($connections as $k=>$v){ if (abs($v)<=0.0000005) unset($connections[$k]); }

    // Final totals
    $discount_total = abs($discSum);
    $tax_total = 0.0; foreach ($lastTax as $amt){ $tax_total += $amt; }

    return [
        'bill_period' => $period,
        'connections' => $connections,
        'discount_total' => (float)number_format($discount_total, 6, '.', ''),
        'tax_total'      => (float)number_format($tax_total, 6, '.', '')
    ];
}

/* ---------- controller ---------- */
try{
    $uploader = isset($_SESSION['hris']) ? trim((string)$_SESSION['hris']) : '';
    if ($uploader==='') json_error('Session expired or HRIS missing. Please log in again.');

    if (!isset($_FILES['bill_htm'])) json_error("Upload a .htm/.html file with field name 'bill_htm'.");
    if ($_FILES['bill_htm']['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE=>'The uploaded file exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE=>'The uploaded file exceeds MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL=>'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE=>'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR=>'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE=>'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the file upload.'
        ];
        json_error($errors[$_FILES['bill_htm']['error']] ?? 'Upload error.');
    }
    if (!is_uploaded_file($_FILES['bill_htm']['tmp_name'])) json_error('Invalid upload.');

    $orig = $_FILES['bill_htm']['name'];
    $tmp  = $_FILES['bill_htm']['tmp_name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['htm','html'], true)) json_error('Only .htm/.html allowed.');

    $saveDir = 'uploads'; if (!is_dir($saveDir)) @mkdir($saveDir, 0775, true);
    $stored  = $saveDir.'/'.uniqid('slt_', true).'.'.$ext;
    if (!move_uploaded_file($tmp, $stored)) json_error('Failed to save uploaded file.');

    $parsed = parseBillFile($stored);

    // Header
    $sr = generate_temp_sr();
    $insHeader = sprintf(
        "INSERT INTO tbl_admin_slt_monthly_data
         (original_name, stored_path, bill_period_text, bill_period_start, bill_period_end, uploader_hris, sr_number)
         VALUES ('%s','%s','%s','%s','%s','%s','%s')",
        mysqli_real_escape_string($conn,$orig),
        mysqli_real_escape_string($conn,$stored),
        mysqli_real_escape_string($conn,$parsed['bill_period']['text']),
        mysqli_real_escape_string($conn,$parsed['bill_period']['start']),
        mysqli_real_escape_string($conn,$parsed['bill_period']['end']),
        mysqli_real_escape_string($conn,$uploader),
        mysqli_real_escape_string($conn,$sr)
    );
    if (!mysqli_query($conn,$insHeader)) json_error('DB insert (header) failed: '.mysqli_error($conn));
    $uploadId = mysqli_insert_id($conn);

    // Connections
    if (!empty($parsed['connections'])){
        $stmtConn = mysqli_prepare($conn, "INSERT INTO tbl_admin_slt_monthly_data_connections (upload_id, connection_no, subtotal) VALUES (?, ?, ?)");
        if (!$stmtConn) json_error('Prep (connections) failed: '.mysqli_error($conn));
        foreach ($parsed['connections'] as $connNo=>$subtotal){
            mysqli_stmt_bind_param($stmtConn,'isd',$uploadId,$connNo,$subtotal);
            if (!mysqli_stmt_execute($stmtConn)) json_error("DB insert (connection {$connNo}) failed: ".mysqli_error($conn));
        }
        mysqli_stmt_close($stmtConn);
    }

    // One row monthly totals (discount + tax)
    $stmtCT = mysqli_prepare($conn, "INSERT INTO tbl_admin_slt_monthly_data_charges (upload_id, tax_total, discount_total) VALUES (?, ?, ?)");
    if (!$stmtCT) json_error('Prep (charges) failed: '.mysqli_error($conn));
    $tax_total = $parsed['tax_total'];
    $disc_total = $parsed['discount_total']; // abs() already applied
    mysqli_stmt_bind_param($stmtCT, 'idd', $uploadId, $tax_total, $disc_total);
    if (!mysqli_stmt_execute($stmtCT)) json_error('DB insert (charges) failed: '.mysqli_error($conn));
    mysqli_stmt_close($stmtCT);

    json_ok([
        'upload_id'=>$uploadId,
        'sr_number'=>$sr,
        'bill_period'=>$parsed['bill_period'],
        'connections_saved'=>count($parsed['connections']),
        'totals'=>['tax_total'=>$tax_total,'discount_total'=>$disc_total]
    ]);
}catch(Throwable $e){
    json_error($e->getMessage());
}

// INCLUDE THIS TAG ALSO
//  TAL-NODE
