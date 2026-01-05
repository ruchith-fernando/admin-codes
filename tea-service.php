<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/* FY months: April -> March */
$current_year  = date('Y');
$current_month = date('n');
$fy_start_year = ($current_month < 4) ? $current_year - 1 : $current_year;
$fy_end_year   = $fy_start_year + 1;

$start = strtotime("{$fy_start_year}-04-01");
$end   = strtotime("{$fy_end_year}-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = date("F Y", $start);
    $start = strtotime("+1 month", $start);
}

/* Floors */
$floors = mysqli_query($conn, "SELECT id, floor_no, floor_name FROM tbl_admin_floors WHERE is_active=1 ORDER BY floor_no");

/* Items */
$items = mysqli_query($conn, "SELECT id, item_name FROM tbl_admin_tea_items WHERE is_active=1 ORDER BY sort_order, item_name");
?>
<div class="content font-size">
  <div class="container-fluid mt-4">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Tea Service — Entry (Preview → Confirm → Save Pending)</h5>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <!-- Month + Floor ALWAYS enabled -->
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Month</label>
          <select id="tea_month" class="form-select">
            <option value="">-- Select Month --</option>
            <?php foreach($fixed_months as $m): ?>
              <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Floor</label>
          <select id="tea_floor_id" class="form-select">
            <option value="">-- Select Floor --</option>
            <?php while($f = mysqli_fetch_assoc($floors)): ?>
              <option value="<?= (int)$f['id'] ?>" data-floor-no="<?= (int)$f['floor_no'] ?>">
                <?= esc($f['floor_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <!-- Month invoice summary (all floors) -->
      <div id="tea_month_summary_box" class="mb-3 d-none"></div>

      <!-- Existing record shows here -->
      <div id="tea_existing_box" class="mb-3 d-none"></div>

      <!-- Entry section -->
      <div id="tea_entry_section">

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label fw-bold">SR Number / Invoice Number / Ref Number</label>
            <input type="text" id="tea_sr_number" class="form-control" placeholder="Optional">
          </div>

          <!-- ✅ OT entry (Only for Over Time floor) -->
          <div class="col-md-4 d-none" id="tea_ot_box">
            <label class="form-label fw-bold">OT Amount (No Tax)</label>
            <input type="number" step="0.01" min="0" id="tea_ot_amount" class="form-control" value="0">
          </div>
        </div>

        <!-- ✅ Items table wrapper has an ID so we can hide it completely for OT -->
        <div class="table-responsive" id="tea_items_box">
          <table class="table table-bordered align-middle" id="tea_units_table">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th style="width:180px;">Units</th>
              </tr>
            </thead>
            <tbody>
            <?php while($it = mysqli_fetch_assoc($items)): ?>
              <tr>
                <td><?= esc($it['item_name']) ?></td>
                <td>
                  <input type="number" min="0"
                         class="form-control tea_units"
                         data-item-id="<?= (int)$it['id'] ?>"
                         value="0">
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-secondary" id="tea_btn_preview" type="button">Preview</button>
          <button class="btn btn-success" id="tea_btn_save" type="button" disabled>Save as Pending</button>
        </div>

        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" id="tea_confirm_checked" disabled>
          <label class="form-check-label" for="tea_confirm_checked">
            I have checked the preview amounts and confirm to save.
          </label>
        </div>

        <div id="tea_preview_area" class="mt-4"></div>

      </div><!-- /tea_entry_section -->

      <div id="tea_status_msg" class="mt-3"></div>

    </div>
  </div>
</div>

<!-- ✅ Bump version to kill cache -->
<script src="tea-service.js?v=6"></script>
