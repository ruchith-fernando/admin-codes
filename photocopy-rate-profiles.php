<?php
// photocopy-rate-profiles.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* Load vendors (active) */
$vendors = [];
$vq = mysqli_query($conn, "
    SELECT vendor_id, vendor_name
    FROM tbl_admin_vendors
    WHERE is_active = 1
    ORDER BY vendor_name
");
while ($r = $vq ? mysqli_fetch_assoc($vq) : null) {
    if (!$r) break;
    $vendors[] = $r;
}
?>

<style>
.pc-wrap { width:100%; overflow-x:auto; }
.pc-table { width:100%; min-width: 1100px; }
.pc-table th, .pc-table td { white-space: nowrap; vertical-align: middle; }
.pc-table td.wrap { white-space: normal; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">Photocopy — Rate Profiles (Master)</h5>

      <div id="pc_rate_msg" class="mb-3"></div>

      <div class="row g-3 mb-3">

        <input type="hidden" id="pc_rate_profile_id" value="">

        <div class="col-md-4">
          <label class="form-label fw-bold">Vendor</label>
          <select id="pc_vendor_id" class="form-select">
            <option value="">-- Select Vendor --</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= (int)$v['vendor_id'] ?>">
                <?= htmlspecialchars($v['vendor_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Rate profiles are created per vendor.</small>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Model Match (Optional)</label>
          <input type="text" id="pc_model_match" class="form-control" placeholder="e.g. ATM-MXM6050 (leave blank = vendor default)">
          <small class="text-muted">Blank means “default rate for this vendor”.</small>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Copy Rate (Rs.)</label>
          <input type="number" step="0.01" min="0" id="pc_copy_rate" class="form-control" placeholder="2.70">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">SSCL %</label>
          <input type="number" step="0.01" min="0" id="pc_sscl" class="form-control" placeholder="2.50">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">VAT %</label>
          <input type="number" step="0.01" min="0" id="pc_vat" class="form-control" placeholder="18.00">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Effective From (Optional)</label>
          <input type="date" id="pc_eff_from" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Effective To (Optional)</label>
          <input type="date" id="pc_eff_to" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Active?</label>
          <select id="pc_is_active" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-9 d-flex align-items-end gap-2">
          <button type="button" id="pc_rate_save" class="btn btn-success">Save Profile</button>
          <button type="button" id="pc_rate_reset" class="btn btn-outline-secondary">Reset</button>
        </div>
      </div>

      <hr>

      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Filter by Vendor</label>
          <select id="pc_vendor_filter" class="form-select">
            <option value="">-- All Vendors --</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= (int)$v['vendor_id'] ?>">
                <?= htmlspecialchars($v['vendor_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="pc_rate_profiles_table" class="pc-wrap"></div>

    </div>
  </div>
</div>

<script src="photocopy-rate-profiles.js?v=1"></script>
