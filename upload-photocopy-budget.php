<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Safe include for connection.php whether this file is in /pages or root.
 */
$baseDir = realpath(__DIR__ . '/..'); // if file is /pages/..., this points to /public_html
if ($baseDir && file_exists($baseDir . '/connections/connection.php')) {
    require_once $baseDir . '/connections/connection.php';
} else {
    // fallback (if file is not inside /pages)
    require_once __DIR__ . '/connections/connection.php';
}

/**
 * POST handler (AJAX)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    header("Content-Type: application/json; charset=utf-8");

    if (!isset($conn) || !$conn) {
        echo json_encode(["status" => "error", "message" => "Database connection not available."]);
        exit;
    }

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "CSV upload failed."]);
        exit;
    }

    $file = $_FILES['csv']['tmp_name'];
    $handle = fopen($file, "r");
    if (!$handle) {
        echo json_encode(["status" => "error", "message" => "Could not open uploaded file."]);
        exit;
    }

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $errors   = [];
    $line     = 0;

    // Prepared statements (safer + faster)
    $stmtBranchCheck = $conn->prepare("SELECT branch_code FROM tbl_admin_branches WHERE branch_code = ? LIMIT 1");
    $stmtBranchIns   = $conn->prepare("INSERT INTO tbl_admin_branches (branch_code, branch_name, is_active) VALUES (?, ?, 1)");
    $stmtBranchUpd   = $conn->prepare("UPDATE tbl_admin_branches SET branch_name = ?, is_active = 1 WHERE branch_code = ?");

    // Budget upsert by (branch_code, budget_year)
    $stmtBudgetCheck = $conn->prepare("SELECT id FROM tbl_admin_budget_photocopy WHERE branch_code = ? AND budget_year = ? LIMIT 1");
    $stmtBudgetUpd   = $conn->prepare("
        UPDATE tbl_admin_budget_photocopy
           SET branch_name = ?, amount = ?, uploaded_at = NOW()
         WHERE branch_code = ? AND budget_year = ?
    ");
    $stmtBudgetIns   = $conn->prepare("
        INSERT INTO tbl_admin_budget_photocopy (branch_code, branch_name, budget_year, amount, uploaded_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    // Optional: run as transaction for consistency
    $conn->begin_transaction();

    try {
        while (($data = fgetcsv($handle, 5000, ",")) !== false) {
            $line++;
            if ($line === 1) {
                // header row
                continue;
            }

            // Detect CSV format:
            // NEW: 4 cols => [branch_code, branch_name, budget_year, amount]
            // OLD: 6 cols => [serial, branch_code, branch_name, budget_year, amount, rate] (serial/rate ignored)
            $cols = count($data);

            if ($cols >= 6) {
                $branch_code = trim($data[1] ?? "");
                $branch_name = trim($data[2] ?? "");
                $budget_year = trim($data[3] ?? "");
                $amount      = trim($data[4] ?? "");
            } else {
                $branch_code = trim($data[0] ?? "");
                $branch_name = trim($data[1] ?? "");
                $budget_year = trim($data[2] ?? "");
                $amount      = trim($data[3] ?? "");
            }

            // Validate
            if ($branch_code === "" || $budget_year === "" || $amount === "") {
                $skipped++;
                continue;
            }

            $amountVal = (float)str_replace([",", " "], "", $amount);
            if ($amountVal <= 0) {
                $skipped++;
                continue;
            }

            // 1) Ensure branch exists in tbl_admin_branches (store new branches here)
            $stmtBranchCheck->bind_param("s", $branch_code);
            $stmtBranchCheck->execute();
            $resB = $stmtBranchCheck->get_result();

            if ($resB && $resB->num_rows > 0) {
                // Update name if provided (keeps master clean)
                if ($branch_name !== "") {
                    $stmtBranchUpd->bind_param("ss", $branch_name, $branch_code);
                    $stmtBranchUpd->execute();
                }
            } else {
                // Insert new branch
                if ($branch_name === "") $branch_name = $branch_code; // fallback
                $stmtBranchIns->bind_param("ss", $branch_code, $branch_name);
                $stmtBranchIns->execute();
            }

            // 2) Upsert budget row by (branch_code, budget_year)
            $stmtBudgetCheck->bind_param("ss", $branch_code, $budget_year);
            $stmtBudgetCheck->execute();
            $res = $stmtBudgetCheck->get_result();

            if ($res && $res->num_rows > 0) {
                // Update
                $stmtBudgetUpd->bind_param("sdss", $branch_name, $amountVal, $branch_code, $budget_year);
                $stmtBudgetUpd->execute();
                $updated++;
            } else {
                // Insert
                $stmtBudgetIns->bind_param("sssd", $branch_code, $branch_name, $budget_year, $amountVal);
                $stmtBudgetIns->execute();
                $inserted++;
            }
        }

        fclose($handle);
        $conn->commit();

        echo json_encode([
            "status"   => "success",
            "inserted" => $inserted,
            "updated"  => $updated,
            "skipped"  => $skipped,
            "errors"   => $errors
        ]);
        exit;

    } catch (Throwable $e) {
        fclose($handle);
        $conn->rollback();

        echo json_encode([
            "status"  => "error",
            "message" => "Upload failed: " . $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Upload Photocopy Budget CSV</title>
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
  .form-control.is-invalid{border-color:#dc3545}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
  .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
  .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
  .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
  .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:8px 0;background:#fafafa}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
</style>
</head>
<body>

<div id="globalLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Upload Photocopy Budget CSV</h5>
      <div id="uploadResult" class="result-block" style="display:none"></div>

      <form id="csvUploadForm" enctype="multipart/form-data" action="upload-photocopy-budget.php" method="post" novalidate>
        <div class="mb-3">
          <label class="form-label" for="csv_file">Choose CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv" accept=".csv,text/csv" required />
          <div class="mt-2" style="font-size:.9rem;color:#555">
            Accepted columns:<br>
            ✅ (Recommended) Branch Code, Branch Name, Budget Year, Amount<br>
            ✅ (Legacy) Serial, Branch Code, Branch Name, Budget Year, Amount, Rate (Serial/Rate ignored)
          </div>
        </div>

        <button type="submit" class="btn btn-success">Upload &amp; Process</button>

        <div id="uploadProgress" class="progress-wrap">
          <div id="progressBar" class="progress-bar"></div>
          <div id="progressLabel" class="progress-label">Preparing upload…</div>
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

  function resetProgress(){ $wrap.hide();$bar.css('width','0%');$label.text(''); }
  function showResult(html){ $result.html(html).show(); }
  function showError(msg){ $file.addClass('is-invalid').focus(); showResult("<div class='alert alert-danger'><b>❌ "+msg+"</b></div>"); }
  $file.on('change',()=>{ $file.removeClass('is-invalid');$result.hide().empty(); });

  $form.on('submit',function(e){
    e.preventDefault(); $result.hide().empty();
    const file=$file[0].files[0]; if(!file){ showError('Please choose a CSV file.'); return; }
    const fd=new FormData(this); const $btn=$(this).find('button[type="submit"]');
    $btn.prop('disabled',true); $loader.css('display','flex'); $wrap.show(); $label.text('Uploading…');

    $.ajax({
      url:$form.attr('action'),
      type:'POST',
      data:fd,
      contentType:false,
      processData:false,
      dataType:'json', // ✅ IMPORTANT: get JSON directly
      xhr:function(){
        const xhr=$.ajaxSettings.xhr();
        if(xhr.upload){
          xhr.upload.addEventListener('progress',function(e){
            if(e.lengthComputable){
              const p=Math.round((e.loaded/e.total)*100);
              $bar.css('width',p+'%');
              $label.text('Uploading… '+p+'%');
            }
          });
        }
        return xhr;
      },
      success:function(json){
        if(json.status==='success'){
          showResult(
            "<div class='alert alert-success'><b>✅ Success.</b> Inserted: "+json.inserted+
            ", Updated: "+json.updated+
            ", Skipped: "+(json.skipped||0)+
            "</div>"
          );
        } else {
          showResult("<div class='alert alert-danger'><b>❌ "+(json.message||'Failed')+"</b></div>");
        }
      },
      error:function(x){
        showError(x.responseText||'Upload failed.');
      },
      complete:function(){
        $loader.hide();
        $btn.prop('disabled',false);
        setTimeout(resetProgress,600);
      }
    });
  });
})();
</script>
</body>
</html>
