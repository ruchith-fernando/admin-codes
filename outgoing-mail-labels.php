<?php
// pages/outgoing-mail-labels.php
require 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* Department from session; fallback to users table if needed */
$deptFromSession = trim($_SESSION['company_hierarchy'] ?? '');
if ($deptFromSession === '' && isset($_SESSION['hris'])) {
  $hris = $conn->real_escape_string($_SESSION['hris']);
  $rs = mysqli_query($conn, "SELECT company_hierarchy FROM tbl_admin_users WHERE hris='$hris' LIMIT 1");
  if ($rs && $row = mysqli_fetch_assoc($rs)) {
    $deptFromSession = trim($row['company_hierarchy'] ?? '');
    $_SESSION['company_hierarchy'] = $deptFromSession; // cache
  }
}
?>
<style>
  /* 2 labels per row */
  .label-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
  .label{border:1px dashed #bbb;border-radius:8px;padding:8px;text-align:center;overflow:hidden}
  .label h6{margin:4px 0 6px 0;font-weight:600;font-size:.95rem}
  .barcode svg{width:100%;height:42px;display:block} /* keep SVG inside the card */
  .code-text{margin-top:4px;font-size:.8rem;word-break:break-all}
  @media print{ .no-print{display:none!important} .label{border:0;padding:6px} .label-grid{gap:4px} }
</style>

</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Outgoing Mail – Generate Barcode Labels</h5>
        <div class="no-print">
          <button id="btnPrint" class="btn btn-outline-secondary btn-sm" onclick="window.print()" disabled>Print</button>
        </div>
      </div>

      <form id="labelForm" class="row g-3 no-print">
        <div class="col-md-6">
          <label class="form-label">Division / Department</label>
          <input type="text" class="form-control" value="<?=htmlspecialchars($deptFromSession ?: 'Not set')?>" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Quantity</label>
          <input type="number" min="1" max="500" value="10" class="form-control" name="qty" id="qty" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Generate Labels</button>
        </div>
      </form>

      <div id="msg" class="mt-3"></div>
      <div id="labelsWrap" class="mt-3"></div>
    </div>
  </div>
</div>

<script>
document.getElementById('labelForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const qty = Number(document.getElementById('qty').value || 0);
  document.getElementById('btnPrint').disabled = true;
  document.getElementById('msg').innerHTML = '<div class="alert alert-info no-print">Generating…</div>';

  // keep your working path
  const res = await fetch('ajax-generate-mail-labels.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ qty }) // dept & date decided by server
  });

  const out = await res.json().catch(()=>({ok:false}));
  if(out.ok){
    document.getElementById('labelsWrap').innerHTML = out.html;
    document.getElementById('msg').innerHTML =
      '<div class="alert alert-success no-print">Labels ready. Click <b>Print</b>.</div>';
    document.getElementById('btnPrint').disabled = false;
  }else{
    document.getElementById('labelsWrap').innerHTML = '';
    document.getElementById('msg').innerHTML =
      '<div class="alert alert-danger no-print">'+(out.message||'Error generating')+'</div>';
  }
});
</script>
