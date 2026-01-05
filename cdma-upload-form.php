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

    /* ---------------- Helpers (lean) ---------------- */
    function nrm_txt($t){
        $t = str_replace(["\xC2\xA0","\xE2\x80\x93","\xE2\x80\x94"], [' ','-','-'], $t);
        $t = str_replace(["\r\n","\r"], "\n", $t);
        $out=[]; foreach (explode("\n",$t) as $ln){ $out[] = preg_replace('/[ \t]+/',' ',trim($ln)); }
        return implode("\n",$out);
    }
    function bill_period_from_text($txt){
        $flat = preg_replace('/\s+/', ' ', $txt);
        if (preg_match('/bill\s*period\s*:?\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*(?:-|to|–)\s*(\d{1,2}\/\d{1,2}\/\d{4})/i', $flat, $m)) {
            return [$m[1].' - '.$m[2], to_mysql_date($m[1]), to_mysql_date($m[2])];
        }
        return [null,null,null];
    }
    function to_mysql_date($dmy){ [$d,$m,$y]=array_map('intval',explode('/',$dmy)); return sprintf('%04d-%02d-%02d',$y,$m,$d); }

    // last number on a line; supports "(7,500.00)" and spaced OCR like "1 , 655 . 50"
    function last_money_on_line($line){
        $x = preg_replace('/\s*,\s*/', ',', $line);
        $x = preg_replace('/\s*\.\s*/', '.', $x);
        $x = preg_replace('/\s+/', ' ', trim($x));
        if (preg_match('/\(\s*(\d{1,3}(?:,\d{3})*\.\d+|\d+\.\d+)\s*\)\s*$/', $x, $m)) return -1.0 * (float)str_replace(',', '', $m[1]);
        if (preg_match('/(-?\d{1,3}(?:,\d{3})*\.\d+|-?\d+\.\d+)\s*$/', $x, $m)) return (float)str_replace(',', '', $m[1]);
        return null;
    }

    // from FIRST PAGE only: Government Taxes & Levies + VAT
    function taxes_first_page_sum(\Smalot\PdfParser\Document $pdf){
        $pages = $pdf->getPages();
        if (!$pages) return 0.0;
        $p1 = nrm_txt($pages[0]->getText());
        $gov=0.0; $vat=0.0;
        foreach (explode("\n",$p1) as $ln){
            if (preg_match('/government\s+taxes\s*&\s*levies/i',$ln)){
                $v = last_money_on_line($ln); if ($v!==null) $gov=$v;
            } elseif (preg_match('/\bVAT\b/i',$ln) && !preg_match('/add\s+to\s+bill/i',$ln)){
                $v = last_money_on_line($ln); if ($v!==null) $vat=$v;
            }
        }
        return round($gov+$vat,6);
    }

    // Parse Summary page(s): SUBSCRIPTION NUMBER + TOTAL, with contract number
    function parse_summary_connections($allText){
        $lines = explode("\n", nrm_txt($allText));
        $n = count($lines);
        $out = []; // [sub] => ['contract'=>..., 'total'=>float]
        $contract=''; $in_table=false;

        for ($i=0;$i<$n;$i++){
            $ln = trim($lines[$i]); if ($ln==='') continue;

            // find contract
            if (preg_match('/^Summary\s+Contract\s+Number\s*:\s*([A-Za-z0-9\/\-_]+)/i', $ln, $m) ||
                preg_match('/^Contract\s+Number\s*:\s*([A-Za-z0-9\/\-_]+)/i', $ln, $m)) {
                $contract = trim($m[1]);
                // don't flip table state here
            }

            // start table when we see "Subscription Charges" close to header
            if (!$in_table && preg_match('/^Subscription\s+Charges$/i', $ln)) { $in_table=true; continue; }

            if ($in_table){
                // enders
                if (preg_match('/^Contract\s+Charges\b/i',$ln) ||
                    preg_match('/^Total\s+Contract\s+Charges\b/i',$ln) ||
                    preg_match('/^Summary\s+Contract\s+Number\s*:/i',$ln)) { $in_table=false; continue; }

                // must begin with subscription number (digits; spaces allowed)
                if (preg_match('/^\s*(?<raw>\d[\d\s]{5,})\b/', $ln, $mm)) {
                    $raw = $mm['raw'];
                    $sub = preg_replace('/\s+/', '', $raw);
                    if (!preg_match('/^\d{6,14}$/', $sub)) continue;

                    // grab TOTAL at end; if not on this line, peek ahead up to 3 lines (stop if a new row begins)
                    $buf = $ln; $amt = last_money_on_line($buf);
                    if ($amt===null){
                        for($k=$i+1; $k<min($i+4,$n); $k++){
                            $peek = trim($lines[$k]); if ($peek==='') continue;
                            if (preg_match('/^\s*\d[\d\s]{5,}\b/', $peek) || // next row starts
                                preg_match('/^Contract\s+Charges\b/i',$peek) ||
                                preg_match('/^Summary\s+Contract\s+Number\s*:/i',$peek)) break;
                            $buf .= ' '.$peek;
                            $amt = last_money_on_line($peek);
                            if ($amt!==null) break;
                        }
                    }
                    if ($amt!==null){
                        $out[$sub] = ['contract'=>$contract, 'total'=>round((float)$amt,6)];
                    }
                }
            }
        }
        return $out;
    }

    // one parent row per file
    function insert_parent(mysqli $conn, $origName, $periodText, $pStart, $pEnd, $uploader){
        $sql = "INSERT INTO tbl_admin_cdma_monthly_data
                  (original_name, bill_period_text, bill_period_start, bill_period_end, uploader_hris)
                VALUES (?,?,?,?,?)";
        $st = $conn->prepare($sql);
        if (!$st) throw new RuntimeException("Parent prepare failed: ".$conn->error);
        $st->bind_param("sssss", $origName, $periodText, $pStart, $pEnd, $uploader);
        if (!$st->execute()) { $st->close(); throw new RuntimeException("Parent insert failed: ".$st->error); }
        $id = $st->insert_id; $st->close();
        return (int)$id;
    }

    function insert_connections(mysqli $conn, $uploadId, array $rows){
        if (!$rows) return 0;
        $st = $conn->prepare("INSERT INTO tbl_admin_cdma_monthly_data_connections
                                (upload_id, contract_number, connection_no, subtotal)
                              VALUES (?,?,?,?)");
        if (!$st) throw new RuntimeException("Conn prepare failed: ".$conn->error);
        $count=0;
        foreach ($rows as $r){
            $upload_id = $uploadId;
            $contract  = (string)($r['contract'] ?? '');
            $conn_no   = (string)$r['subscription'];
            $subtotal  = (float)$r['total'];
            $st->bind_param("issd", $upload_id, $contract, $conn_no, $subtotal);
            if (!$st->execute()) { $st->close(); throw new RuntimeException("Conn insert failed: ".$st->error); }
            $count++;
        }
        $st->close();
        return $count;
    }

    function insert_charges(mysqli $conn, $uploadId, $taxTotal, $discountTotal=0.0){
        $st = $conn->prepare("INSERT INTO tbl_admin_cdma_monthly_data_charges
                                (upload_id, tax_total, discount_total)
                              VALUES (?,?,?)");
        if (!$st) throw new RuntimeException("Charges prepare failed: ".$conn->error);
        $st->bind_param("idd", $uploadId, $taxTotal, $discountTotal);
        if (!$st->execute()) { $st->close(); throw new RuntimeException("Charges insert failed: ".$st->error); }
        $st->close();
    }

    // normalize file inputs
    function normalizeFiles($keySingle, $keyMulti) {
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

    /* ---------------- Core processing (NEW: summary-only) ---------------- */
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
            $text   = '';
            foreach ($pdf->getPages() as $p) { $text .= $p->getText()."\n"; }
            $ntext  = nrm_txt($text);

            // BILL PERIOD
            [$periodText, $pStart, $pEnd] = bill_period_from_text($ntext);
            if (!$periodText) {
                throw new RuntimeException("BILL PERIOD not found.");
            }

            // Duplicate check: same filename + same period
            if ($st = $conn->prepare("SELECT id FROM tbl_admin_cdma_monthly_data WHERE original_name=? AND bill_period_start=? AND bill_period_end=? LIMIT 1")) {
                $st->bind_param("sss", $name, $pStart, $pEnd);
                $st->execute(); $rs = $st->get_result();
                if ($rs && $rs->num_rows > 0) {
                    $st->close();
                    return "<div class='result-block' style='border-left:4px solid #ffc107'>
                              <div><b>File:</b> ".htmlspecialchars($name)."</div>
                              <div><b>Billing Period:</b> <code>".htmlspecialchars($periodText)."</code></div>
                              <div class='mt-2'><span class='fw-bold' style='color:#b26a00'>Skipped:</span> Duplicate for this period.</div>
                            </div>";
                }
                $st->close();
            }

            // TAXES (first page)
            $tax_total = taxes_first_page_sum($pdf);

            // SUMMARY CONNECTIONS (sub + total)
            $pairs = parse_summary_connections($ntext);
            $childRows = [];
            $sum = 0.0;
            foreach ($pairs as $sub => $info){
                $childRows[] = ['subscription'=>$sub,'contract'=>$info['contract'],'total'=>$info['total']];
                $sum += (float)$info['total'];
            }

            // DB writes
            $conn->begin_transaction();
            $upload_id = insert_parent($conn, $name, $periodText, $pStart, $pEnd, $uploadedBy);

            // optional SR number binding if your helper exists (it usually updates the same table)
            if ($HAS_SR && function_exists('generate_sr_number')) {
                // pattern used in your project: generate_sr_number($conn, $table, $id)
                @generate_sr_number($conn, 'tbl_admin_cdma_monthly_data', $upload_id);
            }

            $saved = insert_connections($conn, $upload_id, $childRows);
            insert_charges($conn, $upload_id, (float)$tax_total, 0.0);
            $conn->commit();

            // UI block
            return "<div class='result-block' style='border-left:4px solid #0d6efd'>
                      <div><b>File:</b> ".htmlspecialchars($name)."</div>
                      <div><b>Billing Period:</b> <code>".htmlspecialchars($periodText)."</code></div>
                      <div class='mt-1'>Connections saved: <b>{$saved}</b></div>
                      <div>Sum of subtotals: <b>".number_format($sum,2,'.',',')."</b></div>
                      <div>Taxes (Govt+VAT, p1): <b>".number_format((float)$tax_total,2,'.',',')."</b></div>
                    </div>";

        } catch (Throwable $e) {
            @mysqli_rollback($conn);
            error_log("PDF process failed [{$name}]: ".$e->getMessage());
            return "<div class='result-block' style='border-left:4px solid #dc3545'><div class='fw-bold text-danger'>".htmlspecialchars($name)."</div><div>".htmlspecialchars($e->getMessage())."</div></div>";
        }
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
    #cardLoader {position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,.9); display: none; align-items: center; justify-content: center; z-index: 9999;}
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
            This saves only <b>SUBSCRIPTION NUMBER</b> & <b>TOTAL</b> from the <b>Summary</b> page, and <b>Govt Taxes &amp; Levies + VAT</b> from page 1.
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
        loader.style.display='none'; uploading=false; setTimeout(resetProgress,600);
        input.disabled=false; confirmBtn.disabled=false;
        return;
      }
      var f = queue.shift();
      var fd = new FormData();
      fd.append('pdf_file', f); // single-file key

      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);

      if(xhr.upload){
        xhr.upload.addEventListener('progress', function(e){
          if(e.lengthComputable){
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
          next();
        }
      };

      xhr.onerror = function(){ done++; msgDanger('Network error for '+escapeHtml(f.name)); next(); };

      xhr.send(fd);
    }

    next();
  }
})();
</script>
</body>
</html>
