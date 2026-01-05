<?php
session_start();
require_once "connections/connection.php";

$table = "tbl_admin_actual_branch_gl_expenses";
$log_table = "tbl_admin_actual_branch_gl_expenses_upload_log";

/** ---------- Helpers ---------- */
function norm_header($h) {
    $h = trim($h ?? "");
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);   // remove BOM
    $h = preg_replace('/\s+/', ' ', $h);
    return strtoupper($h);
}
function title_case_utf8($s) {
    $s = trim($s ?? "");
    if ($s === "") return "";
    $s = mb_strtolower($s, "UTF-8");
    return mb_convert_case($s, MB_CASE_TITLE, "UTF-8");
}
function parse_date_flexible($raw) {
    $raw = trim($raw ?? "");
    if ($raw === "") return null;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $dt = DateTime::createFromFormat('Y-m-d', $raw);
        return ($dt instanceof DateTime) ? $dt : null;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
        $dt = DateTime::createFromFormat('d/m/Y', $raw);
        return ($dt instanceof DateTime) ? $dt : null;
    }
    try { return new DateTime($raw); } catch (Exception $e) { return null; }
}
function get_field($row, $hmap, $headerName, $fallbackIndex) {
    $key = norm_header($headerName);
    if (isset($hmap[$key]) && isset($row[$hmap[$key]])) {
        return $row[$hmap[$key]];
    }
    return $row[$fallbackIndex] ?? "";
}

/**
 * Compute normalized hash for the CSV so "same information" is detected even if filename/order changes.
 * - normalizes DATEOFTRAN (supports YYYY-MM-DD and dd/MM/YYYY)
 * - titlecases ENTERD_BRN_NAME
 * - normalizes debits
 * - sorts rows so order doesn't matter
 */
function compute_normalized_hash($filePath) {
    $handle = fopen($filePath, "r");
    if (!$handle) return [null, "Could not open uploaded file (hash pass)."];

    $headerRow = fgetcsv($handle, 5000, ",");
    if (!$headerRow) { fclose($handle); return [null, "CSV appears to be empty."]; }

    $hmap = [];
    foreach ($headerRow as $i => $h) $hmap[norm_header($h)] = $i;

    $rows = [];

    while (($data = fgetcsv($handle, 5000, ",")) !== false) {
        $gl_code              = trim(get_field($data, $hmap, "GL CODE", 0));
        $gl_description       = trim(get_field($data, $hmap, "GL DESCRIPTION", 1));
        $enterd_brn           = trim(get_field($data, $hmap, "ENTERD_BRN", 2));
        $tran_db_cr_flg       = trim(get_field($data, $hmap, "TRAN_DB_CR_FLG", 3));
        $enterd_brn_name_raw  = trim(get_field($data, $hmap, "ENTERD_BRN_NAME", 4));
        $dateoftran_raw       = trim(get_field($data, $hmap, "DATEOFTRAN", 5));
        $profit_loss_transfer = trim(get_field($data, $hmap, "Profit / Loss Transfer", 6));
        $debits_raw           = trim(get_field($data, $hmap, "DEBITS", 7));
        $brn_code             = trim(get_field($data, $hmap, "BRN_CODE", 8));
        $branch_name          = trim(get_field($data, $hmap, "BRANCH NAME", 9));
        $t1                   = trim(get_field($data, $hmap, "T1", 10));
        $tran_narr_dtl3       = trim(get_field($data, $hmap, "TRAN_NARR_DTL3", 11));
        $tranbat_narr_dtl1    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL1", 12));
        $tranbat_narr_dtl2    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL2", 13));
        $tranbat_narr_dtl3    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL3", 14));

        // Skip clearly invalid rows in hash (same behavior as upload)
        if ($gl_description === "" || $enterd_brn === "" || $dateoftran_raw === "") continue;

        $dt = parse_date_flexible($dateoftran_raw);
        if (!$dt) continue;

        $dateoftran_db    = $dt->format('Y-m-d');
        $applicable_month = $dt->format('F Y');

        $enterd_brn_name = title_case_utf8($enterd_brn_name_raw);

        $debits_clean = str_replace([",", " "], "", $debits_raw);
        $debits = ($debits_clean === "" ? 0 : (float)$debits_clean);
        $debits_norm = number_format($debits, 2, '.', '');

        // Canonical row string (include everything relevant)
        $canonical = implode('|', [
            $applicable_month,
            $dateoftran_db,
            $gl_code,
            $gl_description,
            $enterd_brn,
            $enterd_brn_name,
            $tran_db_cr_flg,
            $profit_loss_transfer,
            $debits_norm,
            $brn_code,
            $branch_name,
            $t1,
            $tran_narr_dtl3,
            $tranbat_narr_dtl1,
            $tranbat_narr_dtl2,
            $tranbat_narr_dtl3
        ]);

        $rows[] = $canonical;
    }

    fclose($handle);

    sort($rows, SORT_STRING);
    $blob = implode("\n", $rows);

    return [hash('sha256', $blob), null];
}

/** ---------- POST upload handler ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    header("Content-Type: application/json; charset=utf-8");

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "CSV upload failed."]);
        exit;
    }

    $file = $_FILES['csv']['tmp_name'];

    // Hash checks
    $file_hash = hash_file('sha256', $file);

    list($normalized_hash, $hash_err) = compute_normalized_hash($file);
    if ($hash_err) {
        echo json_encode(["status" => "error", "message" => $hash_err]);
        exit;
    }

    $file_hash_esc = mysqli_real_escape_string($conn, $file_hash);
    $norm_hash_esc = mysqli_real_escape_string($conn, $normalized_hash);

    // Block if same "information" was already uploaded
    $exists = mysqli_query($conn, "SELECT batch_id, uploaded_at, original_filename
                                  FROM `$log_table`
                                  WHERE normalized_hash = '$norm_hash_esc'
                                  LIMIT 1");

    if ($exists && mysqli_num_rows($exists) > 0) {
        $row = mysqli_fetch_assoc($exists);
        echo json_encode([
            "status" => "info",
            "message" => "This same information has already been uploaded. Upload blocked.",
            "batch_id" => $row["batch_id"],
            "uploaded_at" => $row["uploaded_at"],
            "original_filename" => $row["original_filename"]
        ]);
        exit;
    }

    // Now do actual upload
    $handle = fopen($file, "r");
    if (!$handle) {
        echo json_encode(["status" => "error", "message" => "Could not open uploaded file."]);
        exit;
    }

    $batch_id = "B" . date("YmdHis") . "_" . bin2hex(random_bytes(4));

    $headerRow = fgetcsv($handle, 5000, ",");
    if (!$headerRow) {
        fclose($handle);
        echo json_encode(["status" => "error", "message" => "CSV appears to be empty."]);
        exit;
    }

    $hmap = [];
    foreach ($headerRow as $i => $h) $hmap[norm_header($h)] = $i;

    $inserted = 0;
    $invalid  = 0;
    $failed   = 0;
    $total_rows = 0;

    while (($data = fgetcsv($handle, 5000, ",")) !== false) {
        $total_rows++;

        $gl_code              = trim(get_field($data, $hmap, "GL CODE", 0));
        $gl_description       = trim(get_field($data, $hmap, "GL DESCRIPTION", 1));
        $enterd_brn           = trim(get_field($data, $hmap, "ENTERD_BRN", 2));
        $tran_db_cr_flg       = trim(get_field($data, $hmap, "TRAN_DB_CR_FLG", 3));
        $enterd_brn_name_raw  = trim(get_field($data, $hmap, "ENTERD_BRN_NAME", 4));
        $dateoftran_raw       = trim(get_field($data, $hmap, "DATEOFTRAN", 5));
        $profit_loss_transfer = trim(get_field($data, $hmap, "Profit / Loss Transfer", 6));
        $debits_raw           = trim(get_field($data, $hmap, "DEBITS", 7));
        $brn_code             = trim(get_field($data, $hmap, "BRN_CODE", 8));
        $branch_name          = trim(get_field($data, $hmap, "BRANCH NAME", 9));
        $t1                   = trim(get_field($data, $hmap, "T1", 10));
        $tran_narr_dtl3       = trim(get_field($data, $hmap, "TRAN_NARR_DTL3", 11));
        $tranbat_narr_dtl1    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL1", 12));
        $tranbat_narr_dtl2    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL2", 13));
        $tranbat_narr_dtl3    = trim(get_field($data, $hmap, "TRANBAT_NARR_DTL3", 14));

        if ($gl_description === "" || $enterd_brn === "" || $dateoftran_raw === "") {
            $invalid++;
            continue;
        }

        $dt = parse_date_flexible($dateoftran_raw);
        if (!$dt) { $invalid++; continue; }

        $dateoftran_db    = $dt->format('Y-m-d');
        $applicable_month = $dt->format('F Y');

        $enterd_brn_name = title_case_utf8($enterd_brn_name_raw);

        $debits_clean = str_replace([",", " "], "", $debits_raw);
        $debits = ($debits_clean === "" ? 0 : (float)$debits_clean);

        // escape
        $gl_code              = mysqli_real_escape_string($conn, $gl_code);
        $gl_description       = mysqli_real_escape_string($conn, $gl_description);
        $enterd_brn           = mysqli_real_escape_string($conn, $enterd_brn);
        $tran_db_cr_flg       = mysqli_real_escape_string($conn, $tran_db_cr_flg);
        $enterd_brn_name      = mysqli_real_escape_string($conn, $enterd_brn_name);
        $dateoftran_db        = mysqli_real_escape_string($conn, $dateoftran_db);
        $applicable_month     = mysqli_real_escape_string($conn, $applicable_month);
        $profit_loss_transfer = mysqli_real_escape_string($conn, $profit_loss_transfer);
        $brn_code             = mysqli_real_escape_string($conn, $brn_code);
        $branch_name          = mysqli_real_escape_string($conn, $branch_name);
        $t1                   = mysqli_real_escape_string($conn, $t1);
        $tran_narr_dtl3       = mysqli_real_escape_string($conn, $tran_narr_dtl3);
        $tranbat_narr_dtl1    = mysqli_real_escape_string($conn, $tranbat_narr_dtl1);
        $tranbat_narr_dtl2    = mysqli_real_escape_string($conn, $tranbat_narr_dtl2);
        $tranbat_narr_dtl3    = mysqli_real_escape_string($conn, $tranbat_narr_dtl3);

        $sql = "INSERT INTO `$table` (
                    applicable_month,
                    gl_code, gl_description, enterd_brn, tran_db_cr_flg, enterd_brn_name,
                    dateoftran, profit_loss_transfer, debits,
                    brn_code, branch_name, t1,
                    tran_narr_dtl3, tranbat_narr_dtl1, tranbat_narr_dtl2, tranbat_narr_dtl3
                ) VALUES (
                    '$applicable_month',
                    '$gl_code', '$gl_description', '$enterd_brn', '$tran_db_cr_flg', '$enterd_brn_name',
                    '$dateoftran_db', '$profit_loss_transfer', '$debits',
                    '$brn_code', '$branch_name', '$t1',
                    '$tran_narr_dtl3', '$tranbat_narr_dtl1', '$tranbat_narr_dtl2', '$tranbat_narr_dtl3'
                )";

        if (mysqli_query($conn, $sql)) $inserted++;
        else $failed++;
    }

    fclose($handle);

    // log the upload so next time it will be blocked
    $orig_name = mysqli_real_escape_string($conn, $_FILES['csv']['name'] ?? '');
    $file_size = (int)($_FILES['csv']['size'] ?? 0);
    $batch_id_esc = mysqli_real_escape_string($conn, $batch_id);

    mysqli_query($conn, "INSERT INTO `$log_table`
        (batch_id, normalized_hash, file_hash, original_filename, file_size, total_rows, inserted, invalid_skipped, failed_inserts)
        VALUES
        ('$batch_id_esc', '$norm_hash_esc', '$file_hash_esc', '$orig_name', $file_size, $total_rows, $inserted, $invalid, $failed)
    ");

    echo json_encode([
        "status" => "success",
        "message" => "Upload processed.",
        "batch_id" => $batch_id,
        "total_rows" => $total_rows,
        "inserted" => $inserted,
        "invalid" => $invalid,
        "failed" => $failed
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Upload Branch GL Actuals CSV</title>
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
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px;border:0}
</style>
</head>
<body>

<div id="globalLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5 class="text-primary mb-3">Upload Branch GL Actuals CSV</h5>

      <div id="uploadResult" style="display:none"></div>

      <form id="csvUploadForm"
            enctype="multipart/form-data"
            action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>"
            method="post" novalidate>
        <div class="mb-3">
          <label class="form-label" for="csv_file">Choose CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv" accept=".csv,text/csv" required />
          <div class="form-text">
            Header row ignored. DATEOFTRAN accepts <b>YYYY-MM-DD</b> and <b>dd/MM/YYYY</b>.
            Applicable month derived from DATEOFTRAN. ENTERD_BRN_NAME saved in Title Case.
            Upload will be blocked if the same information was uploaded before (even with a different filename).
          </div>
        </div>

        <button type="submit" class="btn btn-success">Upload &amp; Process</button>

        <div id="uploadProgress" class="mt-3" style="display:none">
          <div class="progress" style="height:10px">
            <div id="progressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
          </div>
          <div id="progressLabel" class="form-text mt-2">Preparing upload…</div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  const $form=$('#csvUploadForm'),$loader=$('#globalLoader'),$result=$('#uploadResult');
  const $wrap=$('#uploadProgress'),$bar=$('#progressBar'),$label=$('#progressLabel'),$file=$('#csv_file');

  function resetProgress(){ $wrap.hide();$bar.css('width','0%');$label.text('Preparing upload…'); }
  function showResult(html){ $result.html(html).show(); }
  function showError(msg){ $file.addClass('is-invalid').focus(); showResult("<div class='alert alert-danger mb-0'><b>❌ "+msg+"</b></div>"); }

  $file.on('change',()=>{ $file.removeClass('is-invalid');$result.hide().empty(); });

  $form.on('submit',function(e){
    e.preventDefault(); $result.hide().empty();
    const file=$file[0].files[0];
    if(!file){ showError('Please choose a CSV file.'); return; }

    const fd=new FormData(this);
    const $btn=$(this).find('button[type="submit"]');
    $btn.prop('disabled',true); $loader.css('display','flex'); $wrap.show(); $label.text('Uploading…');

    $.ajax({
      url:$form.attr('action'),
      type:'POST',
      data:fd,
      contentType:false,
      processData:false,
      dataType:'json',
      xhr:function(){
        const xhr=$.ajaxSettings.xhr();
        if(xhr.upload){
          xhr.upload.addEventListener('progress',function(e){
            if(e.lengthComputable){
              const p=Math.round((e.loaded/e.total)*100);
              $bar.css('width',p+'%'); $label.text('Uploading… '+p+'%');
            }
          });
        }
        return xhr;
      },
      success:function(json){
        if(json.status === 'info'){
          showResult(
            "<div class='alert alert-info'>" +
              "<b>ℹ️ "+json.message+"</b><hr class='my-2'>" +
              "<div><small><b>Previous Batch:</b> "+(json.batch_id||'')+
              " | <b>Uploaded:</b> "+(json.uploaded_at||'')+
              (json.original_filename ? " | <b>File:</b> "+json.original_filename : "")+
              "</small></div>" +
            "</div>"
          );
          return;
        }

        if(json.status !== 'success'){
          showResult("<div class='alert alert-danger'><b>❌ "+(json.message||'Upload failed.')+"</b></div>");
          return;
        }

        const total = Number(json.total_rows||0);
        const inserted = Number(json.inserted||0);
        const invalid = Number(json.invalid||0);
        const failed = Number(json.failed||0);
        const batchId = json.batch_id || "";

        let cls='alert-success', head='✅ Upload completed';
        if(failed>0 || invalid>0){ cls='alert-warning'; head='⚠️ Upload completed with warnings'; }

        showResult(
          "<div class='alert "+cls+"'>" +
            "<b>"+head+"</b><hr class='my-2'>" +
            "<ul class='mb-0'>" +
              "<li><b>Batch ID:</b> "+batchId+"</li>" +
              "<li>Total rows read (excluding header): <b>"+total+"</b></li>" +
              "<li>Inserted: <b>"+inserted+"</b></li>" +
              "<li>Invalid skipped: <b>"+invalid+"</b></li>" +
              "<li>Failed inserts: <b>"+failed+"</b></li>" +
            "</ul>" +
          "</div>"
        );
      },
      error:function(x){
        showError(x.responseText || 'Upload failed.');
      },
      complete:function(){
        $loader.hide(); $btn.prop('disabled',false); setTimeout(resetProgress,600);
      }
    });
  });
})();
</script>
</body>
</html>
