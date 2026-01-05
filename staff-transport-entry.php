<?php
// staff-transport-entry.php
require_once "connections/connection.php";
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit;
}
?>
<style>
  .select2-container--default .select2-selection--single {
      height: 38px !important;
      padding: 0 12px !important;
      font-size: 1rem;
      line-height: 38px !important;
      border: 1px solid #ced4da !important;
      border-radius: 0.375rem !important;
      background-color: #fff !important;
      display: flex;
      align-items: center;
  }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Staff Transport Entry - Kangaroo</h5>

      <form id="kangarooForm" enctype="multipart/form-data" class="form-section" autocomplete="off">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="voucher_no" class="form-label">Voucher Number</label>
            <input type="text" name="voucher_no" id="voucher_no" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label for="cab_number" class="form-label">Cab Number</label>
            <input type="text" name="cab_number" id="cab_number" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label for="date" class="form-label">Date</label>
            <!-- readonly + autocomplete off to stop browser's recent-values dropdown -->
            <input type="text" name="date" id="date" class="form-control datepicker" required autocomplete="off" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="start_location" class="form-label">Start Location</label>
            <select name="start_location" id="start_location" class="form-control location-select" required></select>
          </div>
          <div class="col-md-6">
            <label for="end_location" class="form-label">End Location</label>
            <select name="end_location" id="end_location" class="form-control location-select" required></select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label for="total_km" class="form-label">Total KM Traveled</label>
            <input type="number" name="total_km" id="total_km" class="form-control" step="0.1" required>
          </div>
          <div class="col-md-3">
            <label for="additional_charges" class="form-label">Additional Charges (LKR)</label>
            <!-- text (not number) so we can show commas while typing -->
            <input type="text" name="additional_charges" id="additional_charges" class="form-control" inputmode="decimal" placeholder="e.g. 12,500.00">
          </div>
          <div class="col-md-3">
            <label for="total" class="form-label">Total Amount (LKR)</label>
            <input type="text" name="total" id="total" class="form-control" inputmode="decimal" placeholder="e.g. 45,000.00" required>
          </div>
          <div class="col-md-3">
            <label for="department" class="form-label">Department</label>
            <select name="department" id="department" class="form-control department-select" required></select>
          </div>
        </div>

        <div class="mb-3">
          <label for="passengers" class="form-label">Passenger Name(s)</label>
          <textarea name="passengers" id="passengers" class="form-control" rows="2" placeholder="Comma-separated names"></textarea>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="vehicle_no" class="form-label">Vehicle Number</label>
            <input type="text" name="vehicle_no" id="vehicle_no" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label for="chit" class="form-label">Upload Chit / Invoice</label>
            <input
              type="file"
              name="chit"
              id="chit"
              class="form-control"
              accept=".pdf,.jpg,.jpeg,.png"
              required
            >
            <div class="form-text text-danger">
              Max file size: <strong>5 MB</strong>. Allowed types: <strong>PDF, JPG, JPEG, PNG</strong>.
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" id="submitBtn">
          <span class="btn-text">Submit Entry</span>
          <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>

        <div id="tripHistory" class="mt-4" style="display:none;">
          <h6>Previous Trips for Same Route</h6>
          <table class="table table-bordered table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Voucher No</th>
                <th>Total KM</th>
                <th>Additional Charges (LKR)</th>
                <th>Total Amount (LKR)</th>
                <th>Entered On</th>
                <th>By (HRIS)</th>
              </tr>
            </thead>
            <tbody id="tripHistoryBody"></tbody>
          </table>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="responseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" id="responseModalContent">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="responseModalTitle">Response</h5>
      </div>
      <div class="modal-body" id="responseModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="location.reload();">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
  // ===== Currency input helpers (thousand separators) =====
  function formatCurrencyInput(el) {
    const raw = (el.value || '').replace(/,/g, '').replace(/[^\d.]/g, '');
    if (raw === '') { el.value = ''; return; }
    const firstDot = raw.indexOf('.');
    let cleaned = firstDot === -1 ? raw : raw.slice(0, firstDot + 1) + raw.slice(firstDot + 1).replace(/\./g, '');
    const parts = cleaned.split('.');
    const int = parts[0];
    const dec = parts[1] ? parts[1].slice(0, 2) : '';
    const withSep = Number(int).toLocaleString('en-US');
    el.value = dec ? `${withSep}.${dec}` : withSep;
  }
  function unformatCurrency(v){ return (v || '').replace(/,/g,''); }

  // Attach formatting to both amount fields
  ['#additional_charges', '#total'].forEach(sel => {
    $(document).on('input', sel, function(){ formatCurrencyInput(this); });
    $(document).on('blur', sel, function(){
      if (this.value.endsWith('.')) this.value = this.value.slice(0, -1);
      if (this.value) formatCurrencyInput(this);
    });
  });

  // ===== Simple formatters for history table =====
  function fmtAmount(n){
    if (n === null || n === undefined || n === '') return '';
    const s = String(n).replace(/,/g,'');
    const num = Number(s);
    if (isNaN(num)) return '';
    const hasDec = /\.\d/.test(s);
    return num.toLocaleString('en-US', { minimumFractionDigits: hasDec ? 2 : 0, maximumFractionDigits: 2 });
  }
  function fmtKm(n){
    const num = Number(n);
    if (isNaN(num)) return '';
    return String(Math.round(num)); // KM: whole number, no separators
  }
  function fmtDateTime(dt) {
    // Expecting MySQL DATETIME or date string; show as YYYY-MM-DD HH:MM
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dt; // fallback
    const pad = (x)=>String(x).padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  $(document).ready(function () {
    // Bootstrap datepicker - past dates only; readonly input stops browser suggestions/history dropdown
    $('.datepicker').datepicker({
      format: 'yyyy-mm-dd',
      autoclose: true,
      endDate: '0d',
      todayHighlight: true,
      clearBtn: true
    }).on('show', function(){
      this.blur(); // avoid text cursor; helps on mobile
    });

    $('.location-select').select2({
      tags: true,
      placeholder: "Select or type a location",
      width: '100%',
      ajax: {
        url: 'fetch-locations.php',
        dataType: 'json',
        delay: 250,
        processResults: data => ({ results: data }),
        cache: true
      },
      createTag: params => ({ id: params.term, text: params.term, newOption: true }),
      insertTag: (data, tag) => data.push(tag)
    });

    $('.department-select').select2({
      tags: true,
      placeholder: "Select or type a department",
      width: '100%',
      ajax: {
        url: 'fetch-departments.php',
        dataType: 'json',
        delay: 250,
        processResults: data => ({ results: data }),
        cache: true
      },
      createTag: params => ({ id: params.term, text: params.term, newOption: true }),
      insertTag: (data, tag) => data.push(tag)
    });

    // Prevent double-submit + show loader; also strip commas before sending
    $('#kangarooForm').on('submit', function (e) {
      e.preventDefault();

      // normalize formatted amounts
      $('#additional_charges').val(unformatCurrency($('#additional_charges').val()));
      $('#total').val(unformatCurrency($('#total').val()));

      const $btn = $('#submitBtn');
      $btn.prop('disabled', true);
      $btn.find('.spinner-border').removeClass('d-none');
      $btn.find('.btn-text').text('Submitting…');

      var formData = new FormData(this);
      $.ajax({
        url: 'ajax-submit-kangaroo-entry.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        success: function (res) {
          $('#responseModalTitle').text((res.status || 'success').toString().toUpperCase());
          $('#responseModalBody').text(res.message || 'Submitted successfully.');
          $('#responseModalContent').removeClass().addClass('modal-content border-' + (res.status || 'success'));
          $('#responseModalContent .modal-header').removeClass().addClass('modal-header bg-' + (res.status || 'success') + ' text-white');
            new bootstrap.Modal(document.getElementById('responseModal'), {
            backdrop: 'static',
            keyboard: false
            }).show();

        },
        error: function () {
          $('#responseModalTitle').text("Error");
          $('#responseModalBody').text("Unexpected error occurred.");
          $('#responseModalContent').removeClass().addClass('modal-content border-danger');
          $('#responseModalContent .modal-header').removeClass().addClass('modal-header bg-danger text-white');
            new bootstrap.Modal(document.getElementById('responseModal'), {
            backdrop: 'static',
            keyboard: false
            }).show();

        },
        complete: function () {
          // re-enable button after response
          $btn.prop('disabled', false);
          $btn.find('.spinner-border').addClass('d-none');
          $btn.find('.btn-text').text('Submit Entry');
        }
      });
    });
  });

  function fetchPreviousTrips() {
    const startData = $('#start_location').select2('data');
    const endData = $('#end_location').select2('data');

    const start = startData.length ? startData[0].text.trim() : '';
    const end   = endData.length ? endData[0].text.trim() : '';

    if (start && end) {
      $.ajax({
        url: 'fetch-previous-trips.php',
        type: 'GET',
        dataType: 'json',
        data: { start: start, end: end },
        success: function (data) {
          if (Array.isArray(data) && data.length > 0) {
            let rows = '';
            data.forEach(trip => {
              rows += `<tr>
                <td>${trip.date || ''}</td>
                <td>${trip.voucher_no || ''}</td>
                <td>${fmtKm(trip.total_km)}</td>
                <td>${fmtAmount(trip.additional_charges)}</td>
                <td>${fmtAmount(trip.total)}</td>
                <td>${fmtDateTime(trip.created_at)}</td>
                <td>${trip.created_by_hris || ''}</td>
              </tr>`;
            });
            $('#tripHistoryBody').html(rows);
            $('#tripHistory').show();
          } else {
            $('#tripHistoryBody').html('<tr><td colspan="7">No matching trips found.</td></tr>');
            $('#tripHistory').show();
          }
        },
        error: function (xhr) { console.error("AJAX Error:", xhr.responseText); }
      });
    } else {
      $('#tripHistory').hide();
    }
  }

  $('#start_location, #end_location').on('select2:select', fetchPreviousTrips);
</script>
