<?php
// water-rate-profiles.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

if (empty($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

/*
 * Load water types for dropdown (need water_type_code for JS logic).
 */
$waterTypes = [];
$sqlTypes = "
    SELECT water_type_id, water_type_name, water_type_code
    FROM tbl_admin_water_types
    WHERE is_active = 1
    ORDER BY water_type_name
";
if ($res = $conn->query($sqlTypes)) {
    $waterTypes = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Water Rate Profiles</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
</head>
<body>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">Water Rate Profiles</h5>

      <div id="wrp_alert" class="mb-3"></div>

      <!-- ADD / EDIT FORM (top) -->
      <form id="wrp_form">

        <input type="hidden" name="rate_profile_id" id="wrp_rate_profile_id" value="">

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Water Type <span class="text-danger">*</span></label>
            <select name="water_type_id" id="wrp_water_type_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($waterTypes as $wt): ?>
                <option value="<?= (int)$wt['water_type_id'] ?>"
                        data-code="<?= htmlspecialchars($wt['water_type_code']) ?>">
                    <?= htmlspecialchars($wt['water_type_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Vendor <span class="text-danger">*</span></label>
            <select name="vendor_id" id="wrp_vendor_id" class="form-select" required disabled>
              <option value="">-- Select water type first --</option>
            </select>
          </div>

          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="is_active" id="wrp_is_active" value="1" checked>
              <label class="form-check-label">Active</label>
            </div>
          </div>
        </div>

        <!-- RATE FIELDS: ALWAYS IN THE HTML, JS will show/hide by type  -->
        <div class="row mb-3">

          <!-- Bottle rate (Bottle Water only) -->
          <div class="col-md-3" id="grp_bottle_rate">
            <label class="form-label">Bottle Rate</label>
            <input type="number" step="0.01" name="bottle_rate" id="bottle_rate" class="form-control">
          </div>

          <!-- Cooler rental (Water Machine only) -->
          <div class="col-md-3" id="grp_cooler_rate">
            <label class="form-label">Cooler Rental Rate</label>
            <input type="number" step="0.01" name="cooler_rental_rate" id="cooler_rental_rate" class="form-control">
          </div>

          <!-- SSCL -->
          <div class="col-md-2" id="grp_sscl">
            <label class="form-label">SSCL %</label>
            <input type="number" step="0.01" name="sscl_percentage" id="sscl_percentage" class="form-control">
          </div>

          <!-- VAT -->
          <div class="col-md-2" id="grp_vat">
            <label class="form-label">VAT %</label>
            <input type="number" step="0.01" name="vat_percentage" id="vat_percentage" class="form-control">
          </div>

          <!-- Effective From (Bottle + Tap Line / other, NOT Machine) -->
          <div class="col-md-2" id="grp_effective_from">
            <label class="form-label">Effective From</label>
            <input type="date" name="effective_from" id="effective_from" class="form-control">
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary" id="wrp_save_btn">Save Rate Profile</button>
          <button type="button" class="btn btn-secondary ms-2" id="wrp_reset_btn">Clear</button>
        </div>
      </form>

      <hr>

      <!-- TABLE OF ALL PROFILES -->
      <h6 class="mb-3">Existing Rate Profiles</h6>
      <div id="wrp_table_wrapper" class="table-responsive">
        <!-- Filled by AJAX -->
      </div>

    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="wrp_edit_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Rate Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="wrp_modal_form">
        <div class="modal-body">

          <input type="hidden" name="rate_profile_id" id="wrp_modal_rate_profile_id">

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">Water Type <span class="text-danger">*</span></label>
              <select name="water_type_id" id="wrp_modal_water_type_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($waterTypes as $wt): ?>
                  <option value="<?= (int)$wt['water_type_id'] ?>"
                          data-code="<?= htmlspecialchars($wt['water_type_code']) ?>">
                    <?= htmlspecialchars($wt['water_type_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" id="wrp_modal_vendor_id" class="form-select" required disabled>
                <option value="">-- Select water type first --</option>
              </select>
            </div>

            <div class="col-md-4">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="wrp_modal_is_active" value="1">
                <label class="form-check-label">Active</label>
              </div>
            </div>
          </div>

          <div class="row mb-3">

            <div class="col-md-3" id="grpM_bottle_rate">
              <label class="form-label">Bottle Rate</label>
              <input type="number" step="0.01" name="bottle_rate" id="wrp_modal_bottle_rate" class="form-control">
            </div>

            <div class="col-md-3" id="grpM_cooler_rate">
              <label class="form-label">Cooler Rental Rate</label>
              <input type="number" step="0.01" name="cooler_rental_rate" id="wrp_modal_cooler_rental_rate" class="form-control">
            </div>

            <div class="col-md-2" id="grpM_sscl">
              <label class="form-label">SSCL %</label>
              <input type="number" step="0.01" name="sscl_percentage" id="wrp_modal_sscl_percentage" class="form-control">
            </div>

            <div class="col-md-2" id="grpM_vat">
              <label class="form-label">VAT %</label>
              <input type="number" step="0.01" name="vat_percentage" id="wrp_modal_vat_percentage" class="form-control">
            </div>

            <div class="col-md-2" id="grpM_effective_from">
              <label class="form-label">Effective From</label>
              <input type="date" name="effective_from" id="wrp_modal_effective_from" class="form-control">
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="assets/jquery.min.js"></script>
<script src="assets/bootstrap.bundle.min.js"></script>
<script src="water-rate-profiles.js?v=5"></script>

</body>
</html>