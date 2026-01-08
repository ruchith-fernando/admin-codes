<?php
// /public_html/pages/html-to-single-page-pdf.php
// Upload HTML/HTM → single-page PDF (no Composer), Bootstrap-like UI + AJAX JSON

error_reporting(E_ALL);
$DEBUG = isset($_REQUEST['debug']);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('max_execution_time', '360');
ini_set('upload_max_filesize', '256M');
ini_set('post_max_size',       '256M');
ini_set('memory_limit',        '1024M');        // Dompdf can need a lot for big/inline images
ini_set('max_input_time',      '300');
ini_set('pcre.backtrack_limit','10000000');
ini_set('pcre.recursion_limit','10000000');

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['ok'=>false, 'error'=>'Fatal: '.$e['message']]);
  }
});

define('BASE_DIR', __DIR__);
define('PUBLIC_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 1), '/'));

function _bytes($val){
  $val = trim($val);
  $last = strtolower($val[strlen($val)-1] ?? '');
  $num = (float)$val;
  return $last==='g' ? $num*1024*1024*1024 : ($last==='m' ? $num*1024*1024 : ($last==='k' ? $num*1024 : (int)$num));
}
function safe_dir($path){
  if (!is_dir($path)) @mkdir($path, 0755, true);
  if (!is_dir($path) || !is_writable($path)) {
    http_response_code(500);
    exit('Directory not writable: ' . htmlspecialchars($path));
  }
}

// -------- inputs
// $mode      = strtolower($_REQUEST['mode']  ?? 'long');     // long | a4
// $scale     = (float)($_REQUEST['scale'] ?? 1);             // A4 mode scale
// $media     = strtolower($_REQUEST['media'] ?? 'screen');   // screen | print
// $hPtParam  = isset($_REQUEST['h']) ? (int)$_REQUEST['h'] : null;
// $wPxParam  = isset($_REQUEST['w']) ? (int)$_REQUEST['w'] : null; // NEW: page width (px) for long mode

// -------- inputs (force fixed values)
$mode     = 'long';
$scale    = 1.0;
$media    = 'screen';
$hPtParam = 48000;
$wPxParam = 900;


$IS_AJAX   = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || isset($_REQUEST['ajax']);
$PROCESS   = (isset($_REQUEST['do']) && $_REQUEST['do']==='1');

$mem_limit_bytes = _bytes(ini_get('memory_limit'));
$LOW_MEM = ($mem_limit_bytes > 0 && $mem_limit_bytes < 268435456); // < 256M

$requested_mode = $mode;
if ($mode === 'long' && $LOW_MEM) { $mode = 'a4'; }

// ===== UI (GET) =====
if (!$PROCESS):
?>
<?php session_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upload HTML → Single-Page PDF</title>
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
      <h5>Upload HTML/HTM &rarr; Single-Page PDF</h5>

      <form id="builderForm" action="html-to-single-page-pdf.php" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="do" value="1">
        <input type="hidden" name="ajax" value="1">

        <div class="mb-3">
          <label class="form-label" for="htmfile">Choose HTML/HTM File</label>
          <input class="form-control" type="file" id="htmfile" name="htmfile" accept=".html,.htm,text/html" required />
          <div class="mt-2" style="font-size:.9rem;color:#555">
            Select a single <b>.html</b> or <b>.htm</b> file from your computer. (Relative assets aren’t resolved when uploading a single file.)
          </div>
        </div>

        <div class="mb-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
          <div>
            <label class="form-label" for="mode">Mode</label>
            <select id="mode" class="form-control" disabled>
              <option value="long" selected>One very tall page (no breaks)</option>
              <option value="a4">Fit onto one A4 sheet (scaled)</option>
            </select>
            <!-- disabled select won't submit; hidden input will -->
            <input type="hidden" name="mode" value="long">
          </div>

          <div>
            <label class="form-label" for="scale">Scale (A4 mode)</label>
            <input class="form-control" type="number" step="0.01" id="scale" value="1.00" readonly>
            <input type="hidden" name="scale" value="1.00">
          </div>

          <div>
            <label class="form-label" for="media">Media</label>
            <select id="media" class="form-control" disabled>
              <option value="screen" selected>screen</option>
              <option value="print">print</option>
            </select>
            <input type="hidden" name="media" value="screen">
          </div>

          <div>
            <label class="form-label" for="h">Tall height (px, long mode)</label>
            <input class="form-control" type="number" id="h" value="48000" readonly>
            <input type="hidden" name="h" value="48000">
          </div>

          <div>
            <label class="form-label" for="w">Page width (px, long mode)</label>
            <input class="form-control" type="number" id="w" value="900" readonly>
            <input type="hidden" name="w" value="900">
          </div>
        </div>


        <button type="submit" class="btn btn-success">Create PDF</button>

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
  const form   = document.getElementById('builderForm');
  const fileEl = document.getElementById('htmfile');
  const loader = document.getElementById('globalLoader');
  const wrap   = document.getElementById('uploadProgress');
  const bar    = document.getElementById('progressBar');
  const label  = document.getElementById('progressLabel');
  const out    = document.getElementById('uploadResult');

  function setProg(pct, txt){ bar.style.width = (pct||0)+'%'; if (txt) label.textContent = txt; }
  function showProg(){ wrap.style.display = 'block'; setProg(5,'Starting…'); }
  function hideProg(){ setTimeout(()=>{ wrap.style.display='none'; setProg(0,''); }, 600); }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    out.innerHTML = '';

    const f = fileEl.files && fileEl.files[0];
    if(!f){
      out.innerHTML = "<div class='text-danger fw-bold'>Please choose an HTML/HTM file.</div>";
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
        const p = Math.round((e.loaded/e.total)*50);
        setProg(p, 'Uploading… '+p+'%');
      }
    };
    xhr.onreadystatechange = function(){
      if(xhr.readyState === 2){ setProg(60, 'Rendering PDF…'); }
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
        if(json && json.ok){
          setProg(100,'Done');
          out.innerHTML = "<div class='text-success fw-bold'>✅ PDF created.</div>" +
                          "<div class='result-block'><div><b>File:</b> " + (json.file||'output.pdf') + "</div>" +
                          "<div><b>Mode:</b> " + (json.mode||'') + "</div>" +
                          (json.note?("<div class='muted'>"+json.note+"</div>"):'') +
                          "<div style='margin-top:6px'><a target='_blank' href='"+json.url+"'>Open PDF</a></div></div>";
          try{ window.open(json.url,'_blank'); }catch(_){}
        }else{
          throw new Error((json && json.error) || 'Unknown error');
        }
      }catch(err){
        out.innerHTML = "<div class='text-danger fw-bold'>"+ (err.message || 'Failed to parse server response.') +"</div>";
      }
      hideProg();
      form.reset();
    };
    xhr.send(fd);
  });
})();
</script>
</body>
</html>
<?php
exit;
endif;

// ===== PROCESS (AJAX/POST with file) =====

// Dompdf autoloader (non-Composer build at /pages/dompdf)
$autoload = BASE_DIR . '/dompdf/autoload.inc.php';
if (!is_file($autoload)) {
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'dompdf autoloader not found at /pages/dompdf/autoload.inc.php']);
  exit;
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// Validate upload
if (empty($_FILES['htmfile']) || !isset($_FILES['htmfile']['error']) || $_FILES['htmfile']['error'] !== UPLOAD_ERR_OK) {
  $err = [
    UPLOAD_ERR_INI_SIZE=>'upload_max_filesize exceeded',
    UPLOAD_ERR_FORM_SIZE=>'MAX_FILE_SIZE exceeded',
    UPLOAD_ERR_PARTIAL=>'File partially uploaded',
    UPLOAD_ERR_NO_FILE=>'No file uploaded',
    UPLOAD_ERR_NO_TMP_DIR=>'Missing temp folder',
    UPLOAD_ERR_CANT_WRITE=>'Failed to write file to disk',
    UPLOAD_ERR_EXTENSION=>'PHP extension blocked upload'
  ][$_FILES['htmfile']['error'] ?? -1] ?? 'Upload failed';
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Upload error: '.$err]); exit;
}

$ext = strtolower(pathinfo($_FILES['htmfile']['name'] ?? 'upload', PATHINFO_EXTENSION));
if (!in_array($ext, ['html','htm'], true)) {
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Please upload an .html or .htm file']); exit;
}

$uploadTmp = $_FILES['htmfile']['tmp_name'];
if (!is_uploaded_file($uploadTmp)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Upload not recognized']); exit; }

$htmlRaw = file_get_contents($uploadTmp);
if ($htmlRaw === false || trim($htmlRaw) === '') {
  header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'File is empty or unreadable']); exit;
}

// Wrapper + numeric-alignment CSS
$reset = '@page{margin:0}html,body{margin:0;padding:0}*{-webkit-print-color-adjust:exact;print-color-adjust:exact;}';

/* Numeric alignment CSS:
   - Tabular digits
   - Right-align & no-wrap for amount-ish cells (class, data attr, or last column)
*/
$NUMERIC_CSS = 'body, table, td, th{
  font-variant-numeric: tabular-nums lining-nums;
  font-feature-settings: "tnum" 1, "lnum" 1, "kern" 0;
  text-rendering: geometricPrecision;
}
td, th{ vertical-align: top; }
td:last-child, th:last-child,
.amount, [data-col="amount"], [class*="amount"]{
  text-align: right; white-space: nowrap;
  font-family: "DejaVu Sans Mono","DejaVu Sans",sans-serif;
}
table{ border-collapse: collapse; }';

if ($mode === 'a4') {
  $scale = max(0.5, min(1.0, $scale ?: 0.85));
  $page_mm = round(210 / $scale, 3); // scaled width = 210mm
  $style = "<style>$reset $NUMERIC_CSS
    @page{size:A4}
    .wrap{width:210mm;height:297mm;overflow:hidden;position:relative}
    .page{width:{$page_mm}mm;transform-origin:top left;transform:scale($scale)}
  </style>";
  $html  = $style.'<div class="wrap"><div class="page">'.$htmlRaw.'</div></div>';
  $paperNamed = true;
} else {
  // Long single page (custom width/height in px)
  $wPx = $wPxParam !== null ? (int)$wPxParam : 900;        // NEW default wider width
  $wPx = max(600, min(2400, $wPx));                        // clamp to sensible range

  $defaultTall = 50000;
  $hPt = $hPtParam !== null ? (int)$hPtParam : $defaultTall;
  if ($LOW_MEM) { $hPt = min($hPt, 24000); }
  $hPt = max(10000, min(60000, $hPt));

  $style = "<style>$reset $NUMERIC_CSS
    @page{size:{$wPx}px {$hPt}px}
    html,body{width:{$wPx}px}
  </style>";
  $html  = $style.$htmlRaw;
  $paperNamed = false;
  $customRect = [0, 0, $wPx, $hPt];
}

// Dompdf options (memory-savvy)
$tmp = BASE_DIR . '/tmp';
safe_dir($tmp);

$opts = new Options();
$opts->set('isRemoteEnabled', true);
$opts->set('isHtml5ParserEnabled', true);
$opts->set('defaultMediaType', ($media === 'print' ? 'print' : 'screen'));
$opts->set('chroot', PUBLIC_ROOT);
$opts->set('dpi', 72);
$opts->set('isFontSubsettingEnabled', true);
$opts->set('tempDir',  $tmp);
$opts->set('fontCache',$tmp . '/font-cache');
$opts->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($opts);
if (!empty($paperNamed) && $paperNamed === true) { $dompdf->setPaper('A4', 'portrait'); } else { $dompdf->setPaper($customRect); }
$dompdf->loadHtml($html, 'UTF-8');

try { $dompdf->render(); }
catch (Throwable $e) {
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Render error: '.$e->getMessage()]);
  exit;
}

// Save to /pages/output
$outDir = BASE_DIR . '/output';
safe_dir($outDir);
@file_put_contents($outDir.'/_last.html', $html);

$pdfBytes = $dompdf->output();
$fname   = ($mode==='a4'?'one-a4':'single-tall').'-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.pdf';
$absPath = $outDir . '/' . $fname;
$relUrl  = 'output/' . $fname;

if (file_put_contents($absPath, $pdfBytes) === false) {
  header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Could not write PDF']); exit;
}

$noteParts = [];

if ($requested_mode==='long' && $mode==='a4') { $noteParts[] = 'Long mode disabled due to low PHP memory_limit; rendered A4 instead'; }
if ($mode==='long') { $noteParts[] = 'Width used: '.$wPx.'px'; }
$note = $noteParts ? implode(' · ', $noteParts) : null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'url'=>$relUrl,'file'=>$fname,'mode'=>$mode,'note'=>$note], JSON_UNESCAPED_SLASHES);
exit;
