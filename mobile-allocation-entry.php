<?php
// mobile-allocation-entry.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<div class="content font-size">
  <div class="container-fluid mt-4">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Mobile Allocation — Entry (Preview → Confirm → Save Pending)</h5>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <!-- Always enabled -->
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Request Type</label>
          <select id="alloc_request_type" class="form-select">
            <option value="">-- Select --</option>
            <option value="NEW">New Allocation</option>
            <option value="TRANSFER">Transfer User</option>
            <option value="CLOSE">Close Allocation</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Mobile Number</label>
          <input type="text" id="alloc_mobile" class="form-control" placeholder="e.g. 764319546">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Effective From</label>
          <input type="date" id="alloc_eff_from" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <!-- NEW/TRANSFER fields -->
      <div class="row mb-3" id="alloc_to_hris_row" style="display:none;">
        <div class="col-md-4">
          <label class="form-label fw-bold">To HRIS</label>
          <input type="text" id="alloc_to_hris" class="form-control" placeholder="e.g. 006428">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Owner Name (optional)</label>
          <input type="text" id="alloc_owner_name" class="form-control" placeholder="Employee name">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Note (optional)</label>
          <input type="text" id="alloc_note" class="form-control" placeholder="Reason / comment">
        </div>
      </div>

      <!-- CLOSE fields -->
      <div class="row mb-3" id="alloc_close_row" style="display:none;">
        <div class="col-md-4">
          <label class="form-label fw-bold">Effective To</label>
          <input type="date" id="alloc_eff_to" class="form-control">
        </div>
        <div class="col-md-8">
          <label class="form-label fw-bold">Note (optional)</label>
          <input type="text" id="alloc_close_note" class="form-control" placeholder="Reason / comment">
        </div>
      </div>

      <!-- Existing record shows here -->
      <div id="alloc_existing_box" class="mb-3 d-none"></div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-secondary" id="alloc_btn_preview" type="button">Preview</button>
        <button class="btn btn-success" id="alloc_btn_save" type="button" disabled>Save as Pending</button>
      </div>

      <div class="form-check form-switch mt-3">
        <input class="form-check-input" type="checkbox" id="alloc_confirm_checked" disabled>
        <label class="form-check-label" for="alloc_confirm_checked">
          I checked the preview and confirm to save.
        </label>
      </div>

      <div id="alloc_preview_area" class="mt-4"></div>
      <div id="alloc_status_msg" class="mt-3"></div>

    </div>
  </div>
</div>

<script src="mobile-allocation.js?v=1"></script>
