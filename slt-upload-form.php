<?php
// slt-upload-form.php  — Same server logic as before, wrapped in the "liked" layout
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Colombo');

// --- Runtime limits (match style of the layout you liked) ---
error_reporting(E_ALL);
$DEBUG = isset($_REQUEST['debug']);
ini_set('display_errors', $DEBUG ? '1' : '0');
@ini_set('memory_limit', '1024M');
@ini_set('pcre.backtrack_limit', '10000000');

// ---------- CONFIG ----------
const PDF_MAX_BYTES   = 15 * 1024 * 1024;          // 15 MB
const PDFTOTEXT_ALLOW = false;                     // enable only if you want system pdftotext
const PDFTOTEXT_PATH  = '/usr/bin/pdftotext';      // allowlist path
const UPLOAD_DIR      = 'uploads/slt';             // move outside webroot if possible

function json_fail(string $msg, array $extra = []): void {
  http_response_code(200);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>$msg] + $extra, JSON_UNESCAPED_SLASHES);
  exit;
}
function slog(string $m): void {
  static $logFile = null;
  if ($logFile === null) {
    $logDir = 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $logFile = $logDir . '/slt-upload-' . date('Ymd') . '.log';
  }
  @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $m\n", FILE_APPEND);
}
set_error_handler(function($sev,$msg,$file,$line){
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { throw new ErrorException($msg, 0, $sev, $file, $line); }
  return false;
});

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function require_csrf(): void {
  $t_client = $_POST['csrf_token'] ?? '';
  $t_server = $_SESSION['csrf_token'] ?? '';
  if (!hash_equals($t_server, $t_client)) json_fail('Invalid CSRF token.');
}

/* ===================== POST: PROCESS PDF ===================== */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    require_csrf();

    // Ensure upload dir
    if (!is_dir(UPLOAD_DIR)) {
      if (!@mkdir(UPLOAD_DIR, 0775, true)) json_fail('Failed to create '.UPLOAD_DIR.' directory. Set permissions (775).');
    }
    if (!is_writable(UPLOAD_DIR)) json_fail('Upload folder not writable. Fix permissions on '.UPLOAD_DIR);

    // Optional vendor (Smalot PDF parser)
    $autoload = 'vendor/autoload.php';
    $haveSmalot = false;
    if (file_exists($autoload)) {
      require_once $autoload;
      $haveSmalot = class_exists(\Smalot\PdfParser\Parser::class);
    }

    // DB
    require_once 'connections/connection.php';
    if (!isset($conn) || !($conn instanceof mysqli)) json_fail('DB connection not available from connections/connection.php');
    mysqli_set_charset($conn, 'utf8mb4');

    // Input
    if (empty($_FILES['slt_file'])) json_fail('No PDF received. Field must be slt_file.');
    $f = $_FILES['slt_file'];

    // Upload errors
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
      $err = [
        UPLOAD_ERR_INI_SIZE=>'upload_max_filesize exceeded',
        UPLOAD_ERR_FORM_SIZE=>'MAX_FILE_SIZE exceeded',
        UPLOAD_ERR_PARTIAL=>'File partially uploaded',
        UPLOAD_ERR_NO_FILE=>'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR=>'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE=>'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION=>'PHP extension blocked upload'
      ][$f['error']] ?? 'Upload failed';
      json_fail('Upload error: '.$err);
    }

    // Size / ext / MIME / magic
    if (!isset($f['size']) || $f['size'] <= 0 || $f['size'] > PDF_MAX_BYTES) {
      json_fail('Invalid file size. Max allowed is '.number_format(PDF_MAX_BYTES/1024/1024,1).' MB.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') json_fail('Only PDF files are supported.');
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']) ?: '';
    if (!in_array($mime, ['application/pdf','application/x-pdf'], true)) {
      json_fail('Upload must be a real PDF (bad MIME: '.$mime.').');
    }
    $fh = fopen($f['tmp_name'], 'rb');
    $head = $fh ? fread($fh, 5) : '';
    if ($fh) fclose($fh);
    if ($head !== '%PDF-') json_fail('Upload must start with %PDF- header.');

    // Save
    $storedBase = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    $storedPath = rtrim(UPLOAD_DIR, '/').'/'.$storedBase;
    if (!@move_uploaded_file($f['tmp_name'], $storedPath)) {
      if (!@rename($f['tmp_name'], $storedPath)) json_fail('Failed to save uploaded file.');
    }
    @chmod($storedPath, 0640);
    $relativeStored = rtrim(UPLOAD_DIR, '/').'/'.$storedBase;

    // Extract → Normalize
    $raw = extractPdfTextSmart($storedPath, $haveSmalot);
    if ($raw === '') json_fail('Unable to extract text from PDF.');
    $norm = normalizeText($raw);
    unset($raw); if (function_exists('gc_collect_cycles')) gc_collect_cycles();

    // Parse
    $bill = extractBillPeriod($norm);
    if (!$bill) json_fail('Bill Period not found in PDF.');

    [$tax_total, $discount_total] = extractChargesTotalsBySection($norm);
    $connSubtotals = extractConnectionSubtotals($norm);

    // DB writes
    mysqli_begin_transaction($conn);

    $sr_number = generate_sr_number_slt_safe($conn);
    $uploader_hris = isset($_SESSION['hris']) && $_SESSION['hris'] !== '' ? $_SESSION['hris'] : 'UNKNOWN';

    // Parent
    $stmt = $conn->prepare("
      INSERT INTO tbl_admin_slt_monthly_data
        (original_name, stored_path, bill_period_text, bill_period_start, bill_period_end, uploader_hris, sr_number)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new RuntimeException('Prepare failed (parent): '.mysqli_error($conn));
    $stmt->bind_param(
      "sssssss",
      $f['name'],
      $relativeStored,
      $bill['text'],
      $bill['start'],
      $bill['end'],
      $uploader_hris,
      $sr_number
    );
    if (!$stmt->execute()) throw new RuntimeException('Insert parent failed: '.mysqli_error($conn));
    $upload_id = (int)$conn->insert_id;
    $stmt->close();

    // Connections
    if (!empty($connSubtotals)) {
      $stmtC = $conn->prepare("
        INSERT INTO tbl_admin_slt_monthly_data_connections (upload_id, connection_no, subtotal)
        VALUES (?, ?, ?)
      ");
      if (!$stmtC) throw new RuntimeException('Prepare failed (connections): '.mysqli_error($conn));
      foreach ($connSubtotals as $connection_no => $subtotal) {
        $c = (string)$connection_no;
        $s = (float)$subtotal;
        $stmtC->bind_param("isd", $upload_id, $c, $s);
        if (!$stmtC->execute()) throw new RuntimeException('Insert connections failed: '.mysqli_error($conn));
      }
      $stmtC->close();
    }

    // Charges totals
    $stmtCh = $conn->prepare("
      INSERT INTO tbl_admin_slt_monthly_data_charges (upload_id, tax_total, discount_total)
      VALUES (?, ?, ?)
    ");
    if (!$stmtCh) throw new RuntimeException('Prepare failed (charges): '.mysqli_error($conn));
    $tax_num  = (float)$tax_total;
    $disc_num = (float)$discount_total;
    $stmtCh->bind_param("idd", $upload_id, $tax_num, $disc_num);
    if (!$stmtCh->execute()) throw new RuntimeException('Insert charges failed: '.mysqli_error($conn));
    $stmtCh->close();

    mysqli_commit($conn);

    // === SUCCESS: LOG USER ACTION ===
require_once 'includes/userlog.php';
$hris = $_SESSION['hris'] ?? 'UNKNOWN';
$username = $_SESSION['name'] ?? getUserInfo();

$actionMessage = sprintf(
    '✅ Uploaded SLT Monthly Bill | File: %s | SR#: %s | Bill Period: %s | Connections: %d | Tax: %.2f | Discount: %.2f',
    $f['name'],
    $sr_number,
    $bill['text'],
    count($connSubtotals),
    (float)$tax_num,
    (float)$disc_num
);
userlog($actionMessage);

  // === FINAL RESPONSE ===
  echo json_encode([
    'ok' => true,
    'results' => [[
      'file' => (string)$f['name'],
      'ok' => true,
      'upload_id' => $upload_id,
      'sr_number' => $sr_number,
      'bill_period_text' => $bill['text'],
      'bill_period_start' => $bill['start'],
      'bill_period_end'   => $bill['end'],
      'connection_count'  => count($connSubtotals),
      'connections_sum'   => (float)number_format(array_sum($connSubtotals), 6, '.', ''),
      'tax_total'         => (float)number_format($tax_num, 6, '.', ''),
      'discount_total'    => (float)number_format($disc_num, 6, '.', '')
    ]]
  ], JSON_UNESCAPED_SLASHES);
  exit;


  } catch (Throwable $e) {
    @mysqli_rollback($conn ?? null);
    slog('ERROR: '.$e->getMessage());
    json_fail($e->getMessage(), ['mem'=>ini_get('memory_limit')]);
  }
}

/* ===================== GET: RENDER FORM (Liked Layout) ===================== */
$session_hris = isset($_SESSION['hris']) ? trim((string)$_SESSION['hris']) : 'UNKNOWN';
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upload SLT Monthly Bill (PDF)</title>
<style>
  #globalLoader{position:fixed;inset:0;background:rgba(255,255,255,.9);display:none;align-items:center;justify-content:center;z-index:9999}
  .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
  .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
  .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
  .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
  .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
  @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
  .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
  .card h5{margin:0 0 16px;color:#0d6efd}.mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
  .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
  .text-danger{color:#dc3545}.text-success{color:#198754}.fw-bold{font-weight:700}
  .mt-2{margin-top:.5rem}.mt-4{margin-top:1.5rem}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;background:#fafafa}
  .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
  .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
  .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
  .muted{color:#666}
</style>
</head>
<body>

<div id="globalLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Upload SLT Monthly Bill (PDF)</h5>

      <form id="sltUploadForm" action="slt-upload-form.php" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="mb-3">
          <label class="form-label" for="slt_file">Select PDF File</label>
          <input class="form-control" type="file" id="slt_file" name="slt_file" accept=".pdf,application/pdf" required />
          <div class="mt-2" style="font-size:.9rem;color:#555">
            Upload the original <b>PDF</b> bill containing <b>Bill Period</b> & <b>Charges in detail</b>.
          </div>
        </div>

        <button type="submit" class="btn btn-success">Upload &amp; Process</button>

        <div id="uploadProgress" class="progress-wrap">
          <div id="progressBar" class="progress-bar"></div>
          <div id="progressLabel" class="progress-label">Preparing…</div>
        </div>
      </form>

      <div id="uploadResult" class="mt-4"></div>
    </div>
  </div>
</div>

<script>
(function(){
  const form   = document.getElementById('sltUploadForm');
  const fileEl = document.getElementById('slt_file');
  const loader = document.getElementById('globalLoader');
  const wrap   = document.getElementById('uploadProgress');
  const bar    = document.getElementById('progressBar');
  const label  = document.getElementById('progressLabel');
  const out    = document.getElementById('uploadResult');
  const uploaderHris = <?php echo json_encode($session_hris, JSON_UNESCAPED_SLASHES); ?>;

  function setProg(pct, txt){ bar.style.width = (pct||0)+'%'; if (txt) label.textContent = txt; }
  function showProg(){ wrap.style.display = 'block'; setProg(5,'Starting…'); }
  function hideProg(){ setTimeout(()=>{ wrap.style.display='none'; setProg(0,''); }, 600); }
  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function num(v){ return (v===null||v===undefined||v==='')?'-':(typeof v==='number'?v:esc(String(v))); }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    out.innerHTML = '';

    const f = fileEl.files && fileEl.files[0];
    if(!f){
      out.innerHTML = "<div class='text-danger fw-bold'>Please choose a PDF file.</div>";
      return;
    }

    const fd = new FormData(form);
    loader.style.display='flex';
    showProg();

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

    xhr.upload.onprogress = function(e){
      if(e.lengthComputable){
        const p = Math.round((e.loaded/e.total)*60);
        setProg(p, 'Uploading… '+p+'%');
      }
    };
    xhr.onreadystatechange = function(){
      if(xhr.readyState === 2){ setProg(70, 'Processing…'); }
    };
    xhr.onerror = function(){
      loader.style.display='none'; hideProg();
      out.innerHTML = "<div class='text-danger fw-bold'>Request failed.</div>";
    };
    xhr.onload = function(){
      loader.style.display='none';
      setProg(95,'Finalizing…');
      try{
        const json = JSON.parse(xhr.responseText || '{}');
        if(json && json.ok && Array.isArray(json.results) && json.results[0] && json.results[0].ok){
          const r = json.results[0];
          setProg(100,'Done');
          out.innerHTML =
            "<div class='text-success fw-bold'>✅ File processed and saved successfully.</div>" +
            "<div class='result-block'>" +
              "<div><b>File:</b> " + esc(r.file||'PDF') + "</div>" +
              "<div><b>SR Number:</b> " + esc(r.sr_number||'') + "</div>" +
              "<div><b>Upload ID:</b> " + esc(String(r.upload_id||'')) + "</div>" +
              "<div><b>Bill Period:</b> " + esc(r.bill_period_text||'') +
                (r.bill_period_start && r.bill_period_end ? " <span class='muted'>(" + esc(r.bill_period_start) + " to " + esc(r.bill_period_end) + ")</span>" : "") +
              "</div>" +
              "<div class='mt-2'><b>Connections Saved:</b> " + num(r.connection_count) + "</div>" +
              "<div><b>Connections Sum:</b> " + num(r.connections_sum) + "</div>" +
              "<div><b>Tax Total:</b> " + num(r.tax_total) + " | <b>Discount Total:</b> " + num(r.discount_total) + "</div>" +
              "<div class='muted' style='margin-top:6px'>Uploader HRIS: <?php echo htmlspecialchars($session_hris, ENT_QUOTES, 'UTF-8'); ?></div>" +
            "</div>";
          form.reset();
        } else {
          throw new Error((json && json.error) || 'Unknown error');
        }
      }catch(err){
        out.innerHTML = "<div class='text-danger fw-bold'>"+ (err.message || 'Failed to parse server response.') +"</div>";
      }
      hideProg();
    };
    xhr.send(fd);
  });
})();
</script>
</body>
</html>
<?php
/* ===================== PARSERS & HELPERS ===================== */
function extractPdfTextSmart(string $path, bool $haveSmalot): string {
  if (PDFTOTEXT_ALLOW && is_string(PDFTOTEXT_PATH) && PDFTOTEXT_PATH !== '' && @is_executable(PDFTOTEXT_PATH)) {
    $cmd = PDFTOTEXT_PATH . ' -layout -nopgbrk ' . escapeshellarg($path) . ' - 2>/dev/null';
    $out = @shell_exec($cmd);
    if (is_string($out) && strlen($out) > 0) { slog('used pdftotext: '.PDFTOTEXT_PATH); return $out; }
  }
  if (!$haveSmalot) return '';
  $parser = new \Smalot\PdfParser\Parser();
  $pdf = $parser->parseFile($path);
  $txt=''; $i=0;
  foreach ($pdf->getPages() as $p) {
    $txt .= $p->getText() . "\n";
    if ((++$i % 3) === 0 && function_exists('gc_collect_cycles')) gc_collect_cycles();
  }
  unset($pdf); if (function_exists('gc_collect_cycles')) gc_collect_cycles();
  return $txt;
}
function normalizeText(string $t): string{
  $t=str_replace(["\xC2\xA0","\xE2\x80\x93","\xE2\x80\x94"],[' ','-','-'],$t);
  $t=str_replace(["\r\n","\r"],"\n",$t);
  $lines=explode("\n",$t); $out=[];
  foreach($lines as $line){ $line=trim(preg_replace('/[ \t]+/',' ',$line)); $out[]=$line; }
  return implode("\n",$out);
}
function extractBillPeriod(string $norm): ?array{
  $flat=preg_replace('/\s+/', ' ', $norm);
  if (preg_match('/bill\s*period\s*:?\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/i',$flat,$m)) {
    return ['text'=>$m[1].' - '.$m[2], 'start'=>toMysqlDate($m[1]), 'end'=>toMysqlDate($m[2])];
  }
  if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4}).{0,40}bill\s*period/i',$flat,$m)) {
    return ['text'=>$m[1].' - '.$m[2], 'start'=>toMysqlDate($m[1]), 'end'=>toMysqlDate($m[2])];
  }
  return null;
}
function toMysqlDate(string $dmy): string{ [$d,$m,$y]=array_map('intval',explode('/',$dmy)); return sprintf('%04d-%02d-%02d',$y,$m,$d); }
function lastAmountInLine(string $line): ?float{
  if (preg_match('/(-?\d{1,3}(?:,\d{3})*\.\d+|-?\d+\.\d+)(?=\s*$)/', $line, $m)) {
    return (float)str_replace(',', '', $m[1]);
  }
  return null;
}
function extractChargesTotalsBySection(string $norm): array {
  $lines = explode("\n", $norm);
  $taxSum = 0.0; $discSum = 0.0; $inDiscounts = false; $inTaxes = false;
  foreach ($lines as $raw) {
    $line = trim($raw); if ($line === '') continue;
    if (preg_match('/^\s*discounts\s*:(.*)$/i', $line, $m)) {
      $inDiscounts = true; $inTaxes = false;
      $amt = lastAmountInLine($m[1] ?? ''); if ($amt !== null) $discSum += abs($amt); continue;
    }
    if (preg_match('/^\s*tax(?:es)?\s*:(.*)$/i', $line, $m)) {
      $inTaxes = true; $inDiscounts = false;
      $amt = lastAmountInLine($m[1] ?? ''); if ($amt !== null) $taxSum += $amt; continue;
    }
    if (($inDiscounts || $inTaxes) && preg_match('/^\s*(sub\s*total|total\s+charges)/i', $line)) { $inDiscounts = $inTaxes = false; continue; }
    if ($inDiscounts || $inTaxes) {
      $amt = lastAmountInLine($line);
      if ($amt !== null) { if ($inDiscounts) $discSum += abs($amt); else $taxSum += $amt; }
    }
  }
  return [round($taxSum, 6), round($discSum, 6)];
}
function findConnectionsWindowIndices(array $lines): array {
  $n = count($lines); $chargesIdx = -1; $amountIdx  = -1;
  for ($i=0; $i<$n; $i++) {
    $l = trim($lines[$i]);
    if ($chargesIdx < 0 && preg_match('/\bcharges\s+in\s+detail\b/i', $l)) {
      $chargesIdx = $i;
      for ($j=$i; $j < min($i+8,$n); $j++) if (preg_match('/\bamount\s*\(\s*rs\s*\)\b/i', $lines[$j])) { $amountIdx = $j; break; }
      break;
    }
  }
  if ($chargesIdx < 0) return [$n,$n];
  $start = max($chargesIdx, $amountIdx) + 1;
  $end = $n;
  for ($j=$start; $j<$n; $j++) if (preg_match('/discounts\s*:/i', $lines[$j])) { $end = $j; break; }
  if ($end < $start) $end = $n;
  return [$start,$end];
}
function isConnectionId(string $line): ?string{
  $s = trim($line); if ($s === '') return null;
  $upper   = strtoupper($s);
  $compact = preg_replace('/\s+/', '', $upper);
  if (preg_match('/\b0\d{9,10}\b/', $compact, $m)) return $m[0];
  if (preg_match('/\b94\d{9,11}\b/', $compact, $m)) return $m[0];
  if (preg_match('/\b[A-Z]{2}\d{5,}\b/', $upper, $m)) return $m[0];
  if (preg_match('/\b[A-Z0-9][A-Z0-9\-_]*FTTH[A-Z0-9\-_]*\b/i', $upper, $m)) return strtoupper($m[0]);
  if (preg_match('/\b[A-Z]{3,}\d{3,}\b/', $upper, $m)) return $m[0];
  return null;
}
function extractConnectionSubtotals(string $norm): array{
  $lines = explode("\n", $norm); [$start,$end] = findConnectionsWindowIndices($lines);
  $subs = []; $cur = null;
  for ($i=$start; $i<$end; $i++) {
    $ln = trim($lines[$i]); if ($ln === '') continue;
    $conn = isConnectionId($ln);
    if ($conn !== null) { $cur = (string)$conn; if (!array_key_exists($cur,$subs)) $subs[$cur] = 0.0; continue; }
    if ($cur !== null) {
      if (preg_match('/\b(total|sub\s*total|subtotal|balance|carried\s+forward)\b/i', $ln)) continue;
      if (preg_match('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/', $ln)) continue;
      $amt = lastAmountInLine($ln);
      if ($amt !== null) $subs[$cur] += $amt;
    }
  }
  foreach ($subs as $k => $v) $subs[$k] = round((float)$v, 6);
  return $subs;
}
function generate_sr_number_slt_safe(mysqli $conn): string{
  $prefix='SLT'.date('ym'); // SLTYYMM
  $q="SELECT sr_number FROM tbl_admin_slt_monthly_data WHERE sr_number LIKE CONCAT(?, '%') ORDER BY sr_number DESC LIMIT 1";
  $stmt = $conn->prepare($q);
  if (!$stmt) throw new RuntimeException('Prepare failed (sr select): '.mysqli_error($conn));
  $stmt->bind_param("s", $prefix);
  $stmt->execute();
  $res = $stmt->get_result();
  $next = 1;
  if ($res && $res->num_rows>0) { $row=$res->fetch_assoc(); $tail=substr($row['sr_number'], strlen($prefix)); $next=((int)$tail)+1; }
  $stmt->close();
  return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}
?>
