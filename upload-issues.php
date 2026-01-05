<?php session_start(); ?>
<!-- upload-issues.php -->
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
  .card h5{margin:0 0 16px;color:#0d6efd}
  .mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
  .form-control.is-invalid{border-color:#dc3545}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
  .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
  .mt-2{margin-top:.5rem}.mt-4{margin-top:1.5rem}
  .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
  .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
  .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:8px 0;background:#fafafa}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
  .columns-checklist{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:6px;margin-top:8px}
  .columns-checklist label{cursor:pointer}
</style>
</head>
<body>

<div id="globalLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Upload Employee Mobile Issues CSV</h5>

      <!-- Result area -->
      <div id="uploadResult" class="result-block" style="display:none"></div>

      <?php
      include 'connections/connection.php';
      $lastRow = null;
      $res = $conn->query("SELECT * FROM tbl_admin_mobile_issues ORDER BY id DESC LIMIT 1");
      if ($res && $res->num_rows > 0) {
          $lastRow = $res->fetch_assoc();
      }
      ?>

      <form id="csvUploadForm" enctype="multipart/form-data" action="process-issues.php" method="post" novalidate>
        <div class="mb-3">
          <label class="form-label" for="csv_file">Choose CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required />

          <div class="mt-2" style="font-size:.9rem;color:#555">
            CSV must include the following 9 columns in this exact order (tick as you confirm):<br>

            <div class="columns-checklist result-block">
              <?php
              $requiredCols = [
                "Mobile No.",
                "Remarks",
                "Voice/Data",
                "Remarks on Branch Operational lines",
                "name of employee",
                "HRIS No. - This has to be 000000",
                "company hierarchy (Division)",
                "connection status",
                "nic-no",
                "company contribution"
              ];
              foreach ($requiredCols as $col): ?>
                <div>
                  <input type="checkbox" id="chk_<?= md5($col) ?>" name="chk_cols[]" value="<?= $col ?>">
                  <label for="chk_<?= md5($col) ?>"><code><?= $col ?></code></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($lastRow): ?>
            <div class="result-block" style="margin-top:10px;">
              <b>📌 Last Record in Database:</b><br>
              <div><b>Mobile No:</b> <?= htmlspecialchars($lastRow['mobile_no']) ?></div>
              <div><b>Name:</b> <?= htmlspecialchars($lastRow['name_of_employee']) ?></div>
              <div><b>HRIS No:</b> <?= htmlspecialchars($lastRow['hris_no']) ?></div>
              <div><b>Designation:</b> <?= htmlspecialchars($lastRow['designation']) ?></div>
              <div><b>Status:</b> <?= htmlspecialchars($lastRow['status']) ?></div>
              <div><b>Connection Status:</b> <?= htmlspecialchars($lastRow['connection_status']) ?></div>
            </div>
          <?php else: ?>
            <div class="alert alert-warning mt-2">⚠️ No records found in the table yet.</div>
          <?php endif; ?>

        </div>
        <button type="submit" class="btn btn-success">Upload &amp; Import</button>

        <div id="uploadProgress" class="progress-wrap">
          <div id="progressBar" class="progress-bar"></div>
          <div id="progressLabel" class="progress-label">Preparing upload…</div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const $form   = $('#csvUploadForm');
  const $loader = $('#globalLoader');
  const $result = $('#uploadResult');
  const $wrap   = $('#uploadProgress');
  const $bar    = $('#progressBar');
  const $label  = $('#progressLabel');
  const $file   = $('#csv_file');

  function resetProgress(){ $wrap.hide(); $bar.css('width','0%'); $label.text(''); }
  function showResult(html){ $result.html(html).show(); }
  function showError(msg){
    $file.addClass('is-invalid').focus();
    showResult("<div class='alert alert-danger'><b>❌ " + msg + "</b></div>");
  }
  $file.on('change', function(){ $file.removeClass('is-invalid'); $result.hide().empty(); });

  $form.on('submit', function(e){
    e.preventDefault();
    $result.hide().empty();

    const file = $file[0].files[0];
    if(!file){ showError('Please choose a CSV file.'); return; }

    const isCsv = /\.csv$/i.test(file.name);
    if(!isCsv){ showError('Only CSV files are allowed.'); return; }

    const fd  = new FormData(this);
    const $btn = $(this).find('button[type="submit"]');

    $btn.prop('disabled', true);
    $loader.css('display','flex');
    $wrap.show();
    $label.text('Uploading…');

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: fd,
      contentType: false,
      processData: false,
      cache: false,
      xhr: function(){
        const xhr = $.ajaxSettings.xhr();
        if (xhr.upload) {
          xhr.upload.addEventListener('progress', function(e){
            if (e.lengthComputable){
              const p = Math.round((e.loaded/e.total)*100);
              $bar.css('width', p+'%');
              $label.text('Uploading… '+p+'%');
            }
          });
        }
        return xhr;
      },
      success: function(html){
        $label.text('Processing on server…');
        setTimeout(function(){
          showResult(html || "<div class='alert alert-success'><b>✅ Imported.</b></div>");
        }, 120);
        $form.trigger('reset');
      },
      error: function(x){
        const txt = x.responseText || '';
        if (/<div[^>]*class=['"][^"']*alert[^"']*['"]/.test(txt)) {
          showResult(txt);
        } else {
          showError(txt || 'Upload failed.');
        }
      },
      complete: function(){
        $loader.hide();
        $btn.prop('disabled', false);
        setTimeout(resetProgress, 600);
      }
    });
  });
})();
</script>
