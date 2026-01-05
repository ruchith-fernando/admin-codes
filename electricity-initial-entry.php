<!-- electricity-inital-entry.php -->
<?php
require_once 'connections/connection.php';
session_start();

$errors = [];
$success = isset($_GET['success']);
$error = isset($_GET['error']);

$entry_errors = $_SESSION['entry_errors'] ?? [];
unset($_SESSION['entry_errors']);
?>
<style>
    input[readonly] {
        background-color: #e9ecef !important;
    }
    .small-text { font-size: 0.9rem; }
</style>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Initial Electricity Bill Entry</h5>

            <div id="form-response"></div>

            <?php if ($success): ?>
                <div class="alert alert-success">Records saved successfully!</div>
            <?php elseif ($error): ?>
                <div class="alert alert-warning">Some entries had issues. Please correct them below.</div>
            <?php endif; ?>

            <?php if (!empty($entry_errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($entry_errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Month selector -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label new-font-size mb-2">Month Applicable</label>
                    <select name="month_applicable" id="month_applicable" class="form-control" required>
                        <option value="">-- Select Month --</option>
                        <?php
                        $start = strtotime("April 2025");
                        $end = strtotime("March 2026");
                        while ($start <= $end) {
                            $value = date("F Y", $start);
                            echo "<option value=\"$value\">$value</option>";
                            $start = strtotime("+1 month", $start);
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Existing records for selected month -->
            <div id="existing-records" class="mb-4" style="display:none;">
                <div class="alert alert-info small-text mb-3">
                    Showing existing records for <strong id="selected-month-label"></strong>. You can add missing branches below.
                </div>
                <div id="existing-records-body"></div>
            </div>

            <!-- Entry form -->
            <form id="initial-electricity-form">
                <input type="hidden" name="submit_initial" value="1">

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Branch Code</th>
                                <th>Branch Name</th>
                                <th>Units</th>
                                <th>Total Amount</th>
                                <th>Account No</th>
                                <th>Paid By</th>
                                <th>Bill From</th>
                                <th>Bill To</th>
                                <th>No. of Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 5; $i++): ?>
                            <tr>
                                <td><input type="text" name="branch_code[]" class="form-control branch-code"></td>
                                <td><input type="text" name="branch[]" class="form-control" readonly tabindex="-1"></td>
                                <td><input type="text" name="actual_units[]" class="form-control"></td>
                                <td><input type="text" name="actual_amount[]" class="form-control"></td>
                                <td><input type="text" name="account_no[]" class="form-control" readonly tabindex="-1"></td>
                                <td><input type="text" name="bank_paid_to[]" class="form-control" readonly tabindex="-1"></td>
                                <td><input type="text" name="bill_from_date[]" class="form-control datepicker" autocomplete="off"></td>
                                <td><input type="text" name="bill_to_date[]" class="form-control datepicker" autocomplete="off"></td>
                                <td><input type="text" name="number_of_days[]" class="form-control" readonly tabindex="-1"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Save Bill Entries</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Branch Not Found -->
<div class="modal fade" id="branchNotFoundModal" tabindex="-1" aria-labelledby="branchNotFoundLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="branchNotFoundLabel">Branch Not Found</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        No branch information was found for the entered Branch Code.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Duplicate for Selected Month -->
<div class="modal fade" id="branchDuplicateModal" tabindex="-1" aria-labelledby="branchDuplicateLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="branchDuplicateLabel">Duplicate Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        This Branch Code already has an entry for the selected month. Please review the existing records above.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-warning" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    // Helper: reset entry rows when month changes
    function resetEntryRows() {
        $('#initial-electricity-form').find('input[type="text"]').val('');
        $('#initial-electricity-form').find('input[readonly]').val('');
    }

    // Month change → fetch existing records
    $('#month_applicable').on('change', function () {
        const month = $(this).val();
        if (!month) {
            $('#existing-records').hide();
            $('#existing-records-body').empty();
            resetEntryRows();
            return;
        }

        $('#selected-month-label').text(month);
        $('#existing-records').show().find('#existing-records-body').html('<div class="alert alert-secondary">Loading existing records…</div>');

        // Load existing records for month
        $.get('fetch-electricity-existing.php', { month: month }, function (html) {
            $('#existing-records-body').html(html);
        }).fail(function () {
            $('#existing-records-body').html('<div class="alert alert-danger">Failed to load existing records.</div>');
        });

        // Clear any partially typed rows to avoid wrong-month entries
        resetEntryRows();
    });

    // Auto-fill branch info on Enter in Branch Code
    $('input[name="branch_code[]"]').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const input = $(this);
            const code = input.val().trim();
            const row = input.closest('tr');
            const month = $('#month_applicable').val();

            if (!code) return;

            if (!month) {
                alert("Please select a Month first.");
                return;
            }

            $.get('get-branch-info.php', { code: code, month: month }, function (data) {
                // If record exists for selected month, show modal and clear row
                if (data.exists) {
                    row.find('input[name="branch[]"], input[name="account_no[]"], input[name="bank_paid_to[]"]').val('');
                    const dupModal = new bootstrap.Modal(document.getElementById('branchDuplicateModal'));
                    dupModal.show();
                    return;
                }

                if (data.branch_name) {
                    row.find('input[name="branch[]"]').val(data.branch_name);
                    row.find('input[name="account_no[]"]').val(data.account_no);
                    row.find('input[name="bank_paid_to[]"]').val(data.bank_paid_to);
                } else {
                    row.find('input[name="branch[]"], input[name="account_no[]"], input[name="bank_paid_to[]"]').val('');
                    const notFoundModal = new bootstrap.Modal(document.getElementById('branchNotFoundModal'));
                    notFoundModal.show();
                }
            }, 'json').fail(function () {
                const notFoundModal = new bootstrap.Modal(document.getElementById('branchNotFoundModal'));
                notFoundModal.show();
            });
        }
    });

    // Bootstrap Datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });

    // Calculate number of days when dates chosen
    $('.datepicker').on('changeDate', function () {
        const row = $(this).closest('tr');
        const fromVal = row.find('input[name="bill_from_date[]"]').val();
        const toVal = row.find('input[name="bill_to_date[]"]').val();
        const daysInput = row.find('input[name="number_of_days[]"]');

        if (fromVal && toVal) {
            const from = new Date(fromVal);
            const to = new Date(toVal);
            if (!isNaN(from) && !isNaN(to) && to >= from) {
                const diffTime = Math.abs(to - from);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                daysInput.val(diffDays);
            } else {
                daysInput.val('');
            }
        }
    });

    // AJAX form submission
    $('#initial-electricity-form').on('submit', function (e) {
        e.preventDefault();
        const month = $('#month_applicable').val();
        if (!month) {
            alert('Please select Month Applicable first.');
            return;
        }
        const formData = $(this).serialize() + '&month_applicable=' + encodeURIComponent(month);

        $.post('submit-electricity-entry.php', formData, function (response) {
            $('#form-response').html(response);

            // After save, refresh existing table for up-to-date view
            if (month) {
                $.get('fetch-electricity-existing.php', { month: month }, function (html) {
                    $('#existing-records-body').html(html);
                });
            }
        }).fail(function () {
            $('#form-response').html('<div class="alert alert-danger">Something went wrong while saving the data.</div>');
        });
    });
});
</script>
