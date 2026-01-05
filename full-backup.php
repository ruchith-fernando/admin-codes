<?php
// full-backup.php
  // $allowed_roles = ['super-admin'];
  // require_once 'includes/check-permission.php';
?>

<!-- Layout styles (mirroring your working upload layout) -->
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
  .center{display:flex;justify-content:center}
</style>

<!-- Global loader (same visual as your upload page) -->
<div id="globalLoader">
  <div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>
</div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="text-primary mb-4">System Backup</h5>

      <div id="systemBackupContent">
        <div class="text-center text-muted">
          <div class="spinner-border text-primary" role="status"></div>
          <div>Loading system backup module...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function loadSystemBackupModule() {
  $.ajax({
    url: "ajax-full-backup.php",
    method: "GET",
    beforeSend: function() {
      // show the global loader to match your other page behavior
      $('#globalLoader').css('display','flex');
    },
    success: function (data) {
      $("#systemBackupContent").html(data);
    },
    error: function () {
      $("#systemBackupContent").html('<div class="alert alert-danger fw-bold">❌ Failed to load backup module.</div>');
    },
    complete: function() {
      // always hide loader
      $('#globalLoader').hide();
    }
  });
}

$(document).ready(function () {
  loadSystemBackupModule();
});
</script>
