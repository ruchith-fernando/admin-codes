<?php
// photocopy-machine-assignments.php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Load vendors for dropdown
$vendors = [];
$vq = mysqli_query($conn, "SELECT vendor_id, vendor_name FROM tbl_admin_vendors WHERE is_active=1 AND vendor_type = 'PHOTOCOPY'
ORDER BY vendor_name");
if ($vq) {
  while ($r = mysqli_fetch_assoc($vq)) $vendors[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Photocopy — Machine Assignments</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
    .wrap{max-width:1200px;margin:0 auto;padding:18px}
    .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:18px}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .col{flex:1;min-width:220px}
    label{display:block;font-weight:600;margin:0 0 6px}
    input,select{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
    input[readonly]{background:#f3f4f6}
    .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
    .btn-primary{background:#0d6efd;color:#fff}
    .btn-danger{background:#dc3545;color:#fff}
    .btn-secondary{background:#6c757d;color:#fff}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .table{width:100%;border-collapse:collapse;margin-top:12px}
    .table th,.table td{border:1px solid #e5e7eb;padding:8px;vertical-align:top}
    .table th{background:#f8fafc;text-align:left;white-space:nowrap}
    .muted{color:#6b7280;font-size:.9rem}
    .alert{padding:.65rem 1rem;border-radius:8px;margin:10px 0}
    .alert-success{background:#e8f5e9;color:#1b5e20}
    .alert-danger{background:#ffebee;color:#b71c1c}
    .alert-info{background:#e7f1ff;color:#0b3d91}
    .pill{display:inline-block;background:#eef2ff;border:1px solid #dbeafe;color:#1e3a8a;padding:2px 8px;border-radius:999px;font-size:.85rem}
    .actions button{margin-right:6px}

    .select2-container .select2-selection--single{
  height: 40px;
  border:1px solid #ced4da;
  border-radius:8px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered{
  line-height: 38px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow{
  height: 38px;
}

  </style>
</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Photocopy — Machine Assignments</h5>
    <div class="muted">Use this screen to install/move/remove machines. Only one “current” assignment per machine (we enforce in save logic).</div>

    <div id="pc_msg"></div>

    <div class="row" style="margin-top:14px">
      <div class="col">
        <label>Serial No (Type and Tab)</label>
        <input type="text" id="pc_serial" placeholder="e.g. 85023275">
        <div class="muted" id="pc_machine_hint"></div>
        <input type="hidden" id="pc_machine_id" value="">
      </div>

      <div class="col">
        <label>Model</label>
        <input type="text" id="pc_model" readonly>
      </div>

      <div class="col">
        <label>Branch Name</label>

        <!-- Select2 dropdown -->
        <select id="pc_branch_select" style="width:100%"></select>

        <!-- actual stored value -->
        <input type="hidden" id="pc_branch_code" value="">

        <div class="muted" id="pc_branch_name"></div>
      </div>


      <div class="col">
        <label>Billing Vendor (optional)</label>
        <select id="pc_vendor_id">
          <option value="">— Auto (use machine vendor) —</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int)$v['vendor_id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted" id="pc_vendor_hint"></div>
      </div>

      <div class="col">
        <label>Installed Date</label>
        <input type="date" id="pc_installed_at" value="<?= date('Y-m-d') ?>">
      </div>

      <div class="col">
        <label>Remarks</label>
        <input type="text" id="pc_remarks" placeholder="optional">
      </div>
    </div>

    <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin:0">
        <input type="checkbox" id="pc_confirm" style="width:auto">
        I confirm the branch/vendor is correct.
      </label>

      <button class="btn btn-primary" id="pc_save_btn" disabled>Save / Move</button>
      <button class="btn btn-secondary" id="pc_reset_btn">Reset</button>

      <span class="pill" id="pc_current_badge" style="display:none"></span>
    </div>

    <hr style="margin:16px 0;border:none;border-top:1px solid #e5e7eb">

    <h4 style="margin:0 0 8px">Current Assignments</h4>
    <div class="muted">Removed machines won’t show here. Use Remove button to close an assignment.</div>

    <div id="pc_current_table" style="margin-top:10px"></div>
  </div>
</div>
<script src="photocopy-machine-assignments.js?v=2"></script>
</body>
</html>
