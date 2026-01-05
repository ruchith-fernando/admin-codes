<?php
include 'connections/connection.php';

// Fetch last end balance
$balanceResult = $conn->query("SELECT end_balance FROM tbl_admin_actual_postage_stamps ORDER BY id DESC LIMIT 1");
$latestBalance = $balanceResult->fetch_assoc();
$current_end_balance = $latestBalance ? $latestBalance['end_balance'] : 0;
?>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Add Postage Cheque</h5>

            <form id="chequeForm">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>Cheque Date</label>
                        <input type="text" name="cheque_date" id="cheque_date" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="col-md-4">
                        <label>Cheque Number</label>
                        <input type="text" name="cheque_number" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Cheque Amount</label>
                        <input type="text" name="cheque_amount" class="form-control number" required>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label>Remarks (Optional)</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Enter remarks here..."></textarea>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label>Current End Balance</label>
                        <input type="text" id="endBalanceField" class="form-control" value="<?= number_format($current_end_balance, 2) ?>" readonly tabindex="-1">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Submit</button>
            </form>

            <hr class="my-4">

            <h5 class="mb-4 text-primary">Recent Cheque Entries</h5>
            <table class="table table-bordered mt-3">
                <thead class="table-light">
                    <tr>
                        <th>Cheque Date</th>
                        <th>Cheque No</th>
                        <th>Amount</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="recentCheques">
                    <!-- AJAX content loads here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
      </div>
      <div class="modal-body">Cheque entry recorded successfully.</div>
      <div class="modal-footer">
        <button class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="errorModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Error</h5>
      </div>
      <div class="modal-body" id="errorModalMessage"></div>
      <div class="modal-footer">
        <button class="btn btn-danger" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="duplicateModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Duplicate Entry</h5>
      </div>
      <div class="modal-body">A cheque with the same date, number, and amount already exists.</div>
      <div class="modal-footer">
        <button class="btn btn-warning" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    // Init datepicker
    $('#cheque_date').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', new Date());

    // Format number input
    $(document).on('input', '.number', function () {
        let val = $(this).val().replace(/,/g, '');
        if (!isNaN(val) && val.trim() !== '') {
            $(this).val(parseFloat(val).toLocaleString('en-LK', {minimumFractionDigits: 0}));
        }
    });

    // Submit form via AJAX
    $('#chequeForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.post('ajax-postage-cheque-submit.php', formData, function (res) {
            let result;
            try {
                result = typeof res === 'string' ? JSON.parse(res) : res;
            } catch (e) {
                $('#errorModalMessage').text("Invalid server response.");
                new bootstrap.Modal(document.getElementById("errorModal")).show();
                return;
            }

            if (result.status === 'success') {
                new bootstrap.Modal(document.getElementById("successModal")).show();
                setTimeout(() => {
                    $('#contentArea').load('postage-cheque-entry.php'); // reload only the section
                }, 2000);
            } else if (result.status === 'duplicate') {
                new bootstrap.Modal(document.getElementById("duplicateModal")).show();
            } else {
                $('#errorModalMessage').text(result.message || "Something went wrong.");
                new bootstrap.Modal(document.getElementById("errorModal")).show();
            }
        }).fail(function (xhr, status, error) {
            $('#errorModalMessage').text("Server error: " + error);
            new bootstrap.Modal(document.getElementById("errorModal")).show();
        });
    });

    // Load recent cheques with pagination
    function loadRecentCheques(page = 1) {
        $('#recentCheques').load('ajax-postage-cheque-recent.php?page=' + page);
    }

    // Handle pagination clicks
    $(document).on('click', '.recent-page', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadRecentCheques(page);
    });

    // Initial load
    loadRecentCheques();
});
</script>
