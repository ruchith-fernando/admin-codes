<!-- upload-employee-date.php -->
<div id="globalLoader">
  <div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>
</div>

<style>
  /* Global loader (from your CDMA layout) */
  #globalLoader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  }

  .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
  .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
  .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
  .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
  .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
  @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}

  /* Clean card + form styles (mirrors your second layout) */
  body{background:#f6f8fb}
  .content.font-size{padding:20px}
  .container-fluid{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
  .card h5{margin:0 0 16px;color:#0d6efd}
  .mb-3{margin-bottom:1rem}
  .form-label{display:block;margin-bottom:.5rem}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
  .btn-success{background:#198754;color:#fff}
  .btn-success:disabled{opacity:.6;cursor:not-allowed}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;background:#fafafa;display:none}
  .hint{font-size:.9rem;color:#555}
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Upload Employee CSV</h5>

      <!-- Inline result block (replaces modal) -->
      <div id="uploadResult" class="result-block"></div>

      <form id="csvUploadForm" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label for="csvFile" class="form-label">Select CSV File</label>
          <input type="file" name="csvFile" id="csvFile" class="form-control" accept=".csv" required>
          <div class="mt-2 hint">
            Upload the latest snapshot of all employees.
          </div>
        </div>

        <button type="submit" class="btn btn-success" id="uploadBtn">
          <i class="bi bi-upload"></i> Upload &amp; Process
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const $form = $('#csvUploadForm');
    const $result = $('#uploadResult');
    const $loader = $('#globalLoader');
    const $btn = $('#uploadBtn');

    $form.on('submit', function (e) {
      e.preventDefault();

      // Basic guard
      const file = $('#csvFile').prop('files')[0];
      if (!file) {
        $result
          .html("<div class='alert alert-danger fw-bold'>❌ Please choose a CSV file.</div>")
          .slideDown();
        return;
      }

      const formData = new FormData(this);

      // UI states
      $loader.fadeIn(120);
      $btn.prop('disabled', true);
      $result.slideUp().html(''); // clear previous

      $.ajax({
        // Keep your updated endpoint; change here if you use a different one
        url: 'ajax-upload-employee-full-compare.php', // ✅ uses your updated endpoint
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false
      })
      .done(function (response) {
        $result.html(response).slideDown();
        $form[0].reset();
        // Scroll to results for visibility
        $('html, body').animate({ scrollTop: $result.offset().top - 80 }, 250);
      })
      .fail(function (xhr) {
        const msg = xhr?.responseText
          ? xhr.responseText
          : "<div class='alert alert-danger fw-bold'>❌ Upload failed. Please try again.</div>";
        $result.html(msg).slideDown();
      })
      .always(function () {
        $loader.fadeOut(120);
        $btn.prop('disabled', false);
      });
    });
  })();
</script>
