<?php session_start(); ?>
<!-- upload-electricity-csv.php -->
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
  .mt-2{margin-top:.5rem}.mt-4{margin-top:1.5rem}
  .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
  .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
  .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:8px 0;background:#fafafa}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
  .checklist-block{border:1px dashed #ced4da;background:#fcfcff;border-radius:10px;padding:12px;margin:12px 0 16px}
  .checklist-title{font-weight:700;margin-bottom:8px;color:#0d6efd}
  .check-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 10px;border:1px solid #e9ecef;border-radius:8px;background:#fff;margin-bottom:8px}
  .check-label{font-size:.95rem;color:#333}
  .toggle-group{display:inline-flex;border:1px solid #ced4da;border-radius:8px;overflow:hidden}
  .toggle-group input{display:none}
  .toggle-group label{padding:.4rem .9rem;cursor:pointer;user-select:none}
  .toggle-group label:not(:first-of-type){border-left:1px solid #ced4da}
  .toggle-group input:checked + label{background:#0d6efd;color:#fff}
  .tiny-hint{font-size:.85rem}
</style>
</head>
<body>

<div id="globalLoader">
  <div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>
</div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Upload Electricity CSV</h5>

      <div id="uploadResult" class="result-block" style="display:none"></div>

      <form id="billUploadForm" enctype="multipart/form-data" action="process-electricity-csv.php" method="post" novalidate>

        <!-- Pre-upload Checklist -->
        <div class="checklist-block">
          <div class="checklist-title">Pre-upload Checklist</div>

          <div class="check-item">
            <div class="check-label">1. Verified the CSV headers/order</div>
            <div class="toggle-group">
              <input type="radio" name="emp_list" id="emp_no" value="no" checked>
              <label for="emp_no">No</label>
              <input type="radio" name="emp_list" id="emp_yes" value="yes">
              <label for="emp_yes">Yes</label>
            </div>
          </div>

          <div class="check-item">
            <div class="check-label">2. Confirmed data month(s) are correct</div>
            <div class="toggle-group">
              <input type="radio" name="conn_list" id="conn_no" value="no" checked>
              <label for="conn_no">No</label>
              <input type="radio" name="conn_list" id="conn_yes" value="yes">
              <label for="conn_yes">Yes</label>
            </div>
          </div>

          <div id="checklistMsg" class="tiny-hint" style="color:#b71c1c">
            Please mark both as “Yes” to enable the upload field.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="csv_file">Choose CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv,application/vnd.ms-excel" required disabled />
          <div class="mt-2" style="font-size:.9rem;color:#555">
            Expected headers (case/spacing flexible): <b>branch code</b>, <b>Branch</b>, <b>Acount No</b>,
            <b>Bill From Date</b>, <b>To bill</b>, <b>Amount</b>, <b>no of date</b>, <b>Unit</b>, <b>Paid Amount</b>, <b>Applicable Month 'Month Year'</b>

          </div>
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

<!-- jQuery required for this page's AJAX -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  const $form   = $('#billUploadForm');
  const $loader = $('#globalLoader');
  const $result = $('#uploadResult');
  const $wrap   = $('#uploadProgress');
  const $bar    = $('#progressBar');
  const $label  = $('#progressLabel');
  const $file   = $('#csv_file');

  // Checklist gating
  const $empToggles  = $('input[name="emp_list"]');
  const $connToggles = $('input[name="conn_list"]');
  const $msg         = $('#checklistMsg');

  function bothYes(){
    const emp  = $('input[name="emp_list"]:checked').val() === 'yes';
    const conn = $('input[name="conn_list"]:checked').val() === 'yes';
    return emp && conn;
  }
  function updateChecklistState(){
    if (bothYes()){
      $file.prop('disabled', false);
      $msg.css('color','#1b5e20').text('✅ All set. You can upload the CSV now.');
    } else {
      $file.val('').prop('disabled', true).removeClass('is-invalid');
      $msg.css('color','#b71c1c').text('Please mark both as “Yes” to enable the upload field.');
    }
  }
  $empToggles.on('change', updateChecklistState);
  $connToggles.on('change', updateChecklistState);
  updateChecklistState();

  function resetProgress(){ $wrap.hide(); $bar.css('width','0%'); $label.text(''); }
  function showResult(html){ $result.html(html).show(); }
  function showError(msg){
    $file.addClass('is-invalid').focus();
    showResult("<div class='alert alert-danger'><b>❌ " + msg + "</b></div>");
  }

  $file.on('change', function(){ $file.removeClass('is-invalid'); $result.hide().empty(); });

  $form.on('submit', function(e){
    e.preventDefault();

    if (!bothYes()){
      showError('Please confirm both checklist items are set to “Yes” before uploading.');
      return;
    }

    $result.hide().empty();

    const file = $file[0].files[0];
    if(!file){ showError('Please choose a CSV file.'); return; }

    const isCsv = /\.csv$/i.test(file.name) || /csv|excel/i.test(file.type);
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
          showResult(html || "<div class='alert alert-success'><b>✅ Uploaded.</b></div>");
        }, 120);
        $form.trigger('reset');
        updateChecklistState();
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
