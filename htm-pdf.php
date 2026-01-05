<?php
// /public_html/pages/html-to-single-page-pdf.php
// Single file converter: HTML/HTM → single-page PDF (no Composer), hardened & fixed

// ---- debug flag in URL: ?debug=1 ----
error_reporting(E_ALL);
$DEBUG = isset($_REQUEST['debug']);
ini_set('display_errors', $DEBUG ? '1' : '0');
@ini_set('max_execution_time', '360');  // plenty of time for big pages

// ---- constants & helpers ----
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
function fetch_html_from_src(string $src): string {
  if (preg_match('~^https?://~i', $src)) {
    if (!function_exists('curl_init')) {
      throw new RuntimeException('cURL not available on this server');
    }
    $ch = curl_init($src);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_USERAGENT => 'SinglePagePDF/1.0 (dompdf)',
    ]);
    $html = curl_exec($ch);
    if ($html === false) throw new RuntimeException('cURL: '.curl_error($ch));
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code >= 400) throw new RuntimeException("HTTP $code while loading $src");
    return $html;
  }
  // treat as site-relative path like /pages/file.htm
  $local = PUBLIC_ROOT.'/'.ltrim($src, '/');
  if (!is_file($local)) throw new RuntimeException("File not found: $local");
  $data = file_get_contents($local);
  if ($data === false) throw new RuntimeException("Could not read: $local");
  return $data;
}
function add_base_href(string $html, string $baseHref): string {
  if ($baseHref === '') return $html;
  if (stripos($html,'<head') !== false) {
    return preg_replace('~<head([^>]*)>~i', '<head$1><base href="'.htmlspecialchars($baseHref).'">', $html, 1);
  }
  return '<head><base href="'.htmlspecialchars($baseHref).'"></head>'.$html;
}

// -------- inputs (read BEFORE we touch Dompdf) --------
$mode      = strtolower($_REQUEST['mode']  ?? 'long');     // long | a4
$src       = trim($_GET['src'] ?? '');                     // /pages/file.htm or https://...
$scale     = (float)($_REQUEST['scale'] ?? 0.85);          // for a4 mode
$uploadTmp = $_FILES['htmfile']['tmp_name'] ?? '';         // uploaded file
$media     = strtolower($_REQUEST['media'] ?? 'screen');   // screen | print
$sample    = isset($_REQUEST['sample']);                   // sample PDF
// allow custom tall height (reduces memory vs. gigantic single page)
$hPtParam  = isset($_REQUEST['h']) ? (int)$_REQUEST['h'] : null;

// ---- memory-limit reality check (hosts often ignore ini_set for memory) ----
$mem_limit_bytes = _bytes(ini_get('memory_limit'));
$LOW_MEM = ($mem_limit_bytes > 0 && $mem_limit_bytes < 268435456); // < 256M

// If user asked for LONG mode but host is stuck at 128M, auto-fallback to A4
$requested_mode = $mode;
if ($mode === 'long' && $LOW_MEM) {
  $mode = 'a4';
}

// Do we just show the UI?
$SHOW_FORM = ($src === '' && $uploadTmp === '' && !$sample);
if ($SHOW_FORM) {
  ?>
  <!doctype html><html><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HTML → Single-Page PDF</title>
  <style>
  body{font-family:system-ui,Arial,sans-serif;max-width:760px;margin:40px auto;line-height:1.5}
  label{display:block;margin:10px 0 6px;font-weight:600}
  input,select{width:100%;padding:10px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  button{margin-top:16px;padding:12px 16px;cursor:pointer}
  .tip{font-size:13px;color:#555}
  code{background:#f6f6f6;padding:2px 6px;border-radius:4px}
  .or{margin:8px 0;color:#777;text-align:center}
  .warn{padding:8px 10px;background:#fff3cd;border:1px solid #ffe69c}
  </style></head><body>
  <h1>HTML → Single-Page PDF</h1>

  <?php if ($LOW_MEM): ?>
    <p class="warn"><b>Note:</b> Server memory_limit is <code><?php echo htmlspecialchars(ini_get('memory_limit')); ?></code>.
    Very-tall “Long” mode is disabled automatically; use A4 mode, or raise memory_limit to ≥256M.</p>
  <?php endif; ?>

  <form method="get" action="html-to-single-page-pdf.php" target="_blank" style="margin-bottom:28px">
    <label><strong>Use a file already on your site</strong> (best – preserves images/CSS)</label>
    <input name="src" placeholder="/pages/your.html or https://example.com">
    <div class="row">
      <div><label>Mode</label>
        <select name="mode">
          <option value="long" <?php echo $LOW_MEM?'':'selected'; ?>>One very tall page (no breaks)</option>
          <option value="a4"   <?php echo $LOW_MEM?'selected':''; ?>>Fit onto one A4 sheet (scaled)</option>
        </select>
      </div>
      <div><label>Scale (A4 mode)</label>
        <input name="scale" type="number" step="0.01" min="0.50" max="1.00" value="0.85">
      </div>
    </div>
    <div class="row">
      <div><label>Media</label>
        <select name="media">
          <option value="screen" selected>screen</option>
          <option value="print">print</option>
        </select>
      </div>
      <div><label>Tall height (px, long mode)</label>
        <input name="h" type="number" min="10000" step="1000" placeholder="e.g. 24000">
      </div>
    </div>
    <button type="submit">Create PDF (from site path/URL)</button>
  </form>

  <div class="or">— OR —</div>

  <form method="post" action="html-to-single-page-pdf.php" enctype="multipart/form-data" target="_blank">
    <label><strong>Upload an HTML/HTM file from your computer</strong> (quick test)</label>
    <input type="file" name="htmfile" accept=".html,.htm" required>
    <div class="row">
      <div><label>Mode</label>
        <select name="mode">
          <option value="long" <?php echo $LOW_MEM?'':'selected'; ?>>One very tall page (no breaks)</option>
          <option value="a4"   <?php echo $LOW_MEM?'selected':''; ?>>Fit onto one A4 sheet (scaled)</option>
        </select>
      </div>
      <div><label>Scale (A4 mode)</label>
        <input name="scale" type="number" step="0.01" min="0.50" max="1.00" value="0.85">
      </div>
    </div>
    <div class="row">
      <div><label>Media</label>
        <select name="media">
          <option value="screen" selected>screen</option>
          <option value="print">print</option>
        </select>
      </div><div></div>
    </div>
    <button type="submit">Create PDF (from uploaded file)</button>
  </form>

  <p class="tip">
  • If the PDF is blank, try Media = <code>print</code>.<br>
  • After a run, open <code>/pages/output/_last.html</code> to see what dompdf actually rendered.<br>
  • For assets, upload HTML + <code>css/</code> + <code>images/</code> under <code>/public_html/pages/</code>, then use <code>/pages/yourfile.htm</code>.
  </p>
  <p class="tip">
    Sanity check: <a href="html-to-single-page-pdf.php?sample=1" target="_blank">Sample PDF</a>
    <?php if(!$DEBUG){ echo ' · <a href="?debug=1" target="_self">turn on debug</a>'; } ?>
  </p>
  </body></html>
  <?php
  exit;
}

// ---- Dompdf (non-Composer build in /pages/dompdf) ----
$autoload = BASE_DIR . '/dompdf/autoload.inc.php';
if (!is_file($autoload)) {
  http_response_code(500);
  exit('dompdf autoloader not found at /pages/dompdf/autoload.inc.php');
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// -------- HTML to render --------
if ($sample) {
  $htmlRaw = '<!doctype html><meta charset="utf-8"><style>body{font-family:Arial} .box{padding:30px;border:2px solid #000;margin:20px 0}</style><h1>Sample OK</h1><div class="box">dompdf rendered this sample.</div>';
  $baseHref = '';
} elseif ($uploadTmp !== '') {
  if (!is_uploaded_file($uploadTmp)) { http_response_code(400); exit('Upload error'); }
  $htmlRaw = file_get_contents($uploadTmp);
  if ($htmlRaw === false) { http_response_code(500); exit('Could not read uploaded file'); }
  $baseHref = ''; // uploaded files generally cannot resolve relative assets
} else {
  try { $htmlRaw = fetch_html_from_src($src); }
  catch (Throwable $e) { http_response_code(500); exit('Load error: '.htmlspecialchars($e->getMessage())); }
  if (preg_match('~^https?://~i', $src)) {
    // Build absolute base URL for relative assets
    $u = parse_url($src);
    $base = $u['scheme'].'://'.$u['host'].(isset($u['port'])?':'.$u['port']:'');
    $p = $u['path'] ?? '/'; if (substr($p,-1)!=='/') $p = rtrim(dirname($p),'/').'/';
    $baseHref = $base.$p;
  } else {
    // Site path like /pages/file.htm → use URL path, dompdf resolves via chroot
    $baseHref = rtrim(dirname($src),'/\\').'/';
    if ($baseHref === '.' || $baseHref === '') $baseHref = '/';
  }
}
if (trim($htmlRaw) === '') { http_response_code(500); exit('HTML content is empty'); }
if ($baseHref !== '') { $htmlRaw = add_base_href($htmlRaw, $baseHref); }

// -------- wrapper + paper --------
$reset = '@page{margin:0}html,body{margin:0;padding:0}*{-webkit-print-color-adjust:exact;print-color-adjust:exact;}';

/* Numeric alignment CSS:
   - Tabular digits / fallback to mono
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
  // Pre-scale width so scaled result is exactly 210mm (prevents subpixel drift)
  $page_mm = round(210 / $scale, 3);

  $style = "<style>$reset $NUMERIC_CSS
    @page{size:A4}
    .wrap{width:210mm;height:297mm;overflow:hidden;position:relative}
    .page{width:{$page_mm}mm;transform-origin:top left;transform:scale($scale)}
  </style>";
  $html  = $style.'<div class="wrap"><div class="page">'.$htmlRaw.'</div></div>';
  $paperNamed = true;
} else {
  // Long single page (custom size). Keep within dompdf safe limits.
  // 1pt = 1/72in. Keep height <= ~20000pt by default.
  $wPt = 595; // ~A4 width
  $defaultTall = 20000;
  $hPt = $hPtParam !== null ? (int)$hPtParam : $defaultTall;
  if ($LOW_MEM) { $hPt = min($hPt, 24000); }
  $hPt = max(10000, min(60000, $hPt)); // safety clamp

  $style = "<style>$reset $NUMERIC_CSS
    @page{size:{$wPt}px {$hPt}px}
    html,body{width:{$wPt}px}
  </style>";
  $html  = $style.$htmlRaw;
  $paperNamed = false;
  $customRect = [0, 0, $wPt, $hPt];
}

// -------- dompdf options + render (memory-savvy) --------
$tmp = BASE_DIR . '/tmp';
safe_dir($tmp);

$opts = new Options();
$opts->set('isRemoteEnabled', true);
$opts->set('isHtml5ParserEnabled', true);
$opts->set('defaultMediaType', ($media === 'print' ? 'print' : 'screen'));
$opts->set('chroot', PUBLIC_ROOT);

// memory savers:
$opts->set('dpi', 72);                        // lower DPI → less RAM
$opts->set('isFontSubsettingEnabled', true);  // embed only used glyphs
$opts->set('tempDir',  $tmp);
$opts->set('fontCache',$tmp . '/font-cache');
// Prefer stable font metrics
$opts->set('defaultFont', 'DejaVu Sans');

// Build & render
$dompdf = new Dompdf($opts);
if (!empty($paperNamed) && $paperNamed === true) {
  $dompdf->setPaper('A4', 'portrait');
} else {
  $dompdf->setPaper($customRect);
}
$dompdf->loadHtml($html, 'UTF-8');

try {
  $dompdf->render();
} catch (Throwable $e) {
  http_response_code(500);
  exit('Render error: ' . htmlspecialchars($e->getMessage()));
}

// -------- save → redirect (stable on LiteSpeed/HTTP2) --------
ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { ob_end_clean(); }

$outDir = BASE_DIR . '/output';
safe_dir($outDir);
@file_put_contents($outDir.'/_last.html', $html);

// name + write
$pdfBytes = $dompdf->output();
$fname   = ($mode==='a4'?'one-a4':'single-tall').'-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.pdf';
$absPath = $outDir . '/' . $fname;
$relUrl  = 'output/' . $fname; // relative to /pages/

if (file_put_contents($absPath, $pdfBytes) === false) {
  http_response_code(500);
  exit('Could not write PDF to: ' . htmlspecialchars($absPath));
}

// helpful header if we auto-fell back
if ($requested_mode === 'long' && $mode === 'a4') {
  header('X-Note: Long mode disabled due to low PHP memory_limit; rendered A4 instead');
}

header('Cache-Control: no-transform, private, max-age=0');
header('Pragma: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');
header('Location: ' . $relUrl, true, 302);
exit;
