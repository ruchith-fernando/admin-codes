<?php
require_once 'connections/connection.php';
session_start();

$errors = [];
$success = isset($_GET['success']);
$error = isset($_GET['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_cheque'])) {
    $branch_code = trim($_POST['branch_code']);
    $month = trim($_POST['month_applicable']);
    $cheque_number = trim($_POST['cheque_number']);
    $cheque_date = trim($_POST['cheque_date']);
    $cheque_amount = trim($_POST['cheque_amount']);
    $ar_cr_raw = trim($_POST['ar_cr']);

    if (!is_numeric($cheque_amount)) {
        $errors[] = "Cheque amount must be numeric.";
    }

    if ($ar_cr_raw === 'Ar.') {
        $ar_amount = trim($_POST['ar_amount'] ?? '');
        if ($ar_amount === '' || !is_numeric($ar_amount)) {
            $errors[] = "Amount is required and must be numeric when Ar. is selected.";
        } else {
            $ar_cr = "Ar. - " . number_format((float)$ar_amount, 2, '.', '');
        }
    } elseif ($ar_cr_raw === 'Cr.') {
        $ar_cr = "Cr. - 0.00";
    } else {
        $ar_cr = '';
    }

    if (!$branch_code || !$month || !$cheque_number || !$cheque_date || !$ar_cr || $cheque_amount === '') {
        $errors[] = "All fields are required.";
    } else {
        $check = $conn->prepare("SELECT * FROM tbl_admin_actual_electricity WHERE branch_code = ? AND month_applicable = ?");
        $check->bind_param("ss", $branch_code, $month);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "No electricity bill record found for this branch and month.";
        } else {
            $row = $result->fetch_assoc();
            if (!empty($row['cheque_number']) || !empty($row['cheque_date'])) {
                $errors[] = "Cheque details already entered for this branch and month.";
            } else {
                $stmt = $conn->prepare("UPDATE tbl_admin_actual_electricity SET cheque_number = ?, cheque_date = ?, cheque_amount = ?, ar_cr = ? WHERE branch_code = ? AND month_applicable = ?");
                $stmt->bind_param("ssdsss", $cheque_number, $cheque_date, $cheque_amount, $ar_cr, $branch_code, $month);
                $stmt->execute();
                header("Location: electricity-cheque-entry.php?success=1");
                exit;
            }
        }
    }

    $_SESSION['cheque_errors'] = $errors;
    header("Location: electricity-cheque-entry.php?error=1");
    exit;
}

$entry_errors = $_SESSION['cheque_errors'] ?? [];
unset($_SESSION['cheque_errors']);
?>
<style>
        
        input[readonly] {
            background-color: #e9ecef !important;
        }
    </style>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Electricity Cheque Entry</h5>

            <?php if ($success): ?>
            <div class="alert alert-success">Cheque details saved successfully!</div>
            <?php elseif ($error): ?>
            <div class="alert alert-warning">Some issues occurred. Please correct them below.</div>
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

            <form method="post">
                <input type="hidden" name="submit_cheque" value="1">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Branch Code</label>
                        <input type="text" name="branch_code" id="branchCodeInput" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Branch Name</label>
                        <input type="text" id="branchNameDisplay" class="form-control" readonly tabindex="-1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Month Applicable</label>
                        <input type="text" name="month_applicable" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Cheque Amount</label>
                        <input type="number" step="0.01" name="cheque_amount" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Cheque Number</label>
                        <input type="text" name="cheque_number" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Cheque Date</label>
                        <input type="text" name="cheque_date" class="form-control" id= "cheque_date" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label new-font-size mb-2">Ar./Cr.</label>
                        <select name="ar_cr" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Ar.">Ar.</option>
                            <option value="Cr.">Cr.</option>
                        </select>
                    </div>
                    <div class="col-md-3 " id="amountGroup" style="display:none;">
                        <label class="form-label new-font-size mb-2">Amount</label>
                        <input type="number" step="0.01" name="ar_amount" id="arAmountInput" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Cheque Details</button>
            </form>

            <hr class="my-4">
            <h5 class="mb-4 text-primary">Entered Cheque Records</h5>
            <input type="text" id="searchInput" class="form-control mb-2" placeholder="Search by Branch Code or Month">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Branch Code</th>
                            <th>Month</th>
                            <th>Cheque No</th>
                            <th>Cheque Date</th>
                            <th>Cheque Amount</th>
                            <th>Ar./Cr.</th>
                        </tr>
                    </thead>
                    <tbody id="recordsTable">
                        <?php
                        $res = $conn->query("SELECT branch_code, month_applicable, cheque_number, cheque_date, cheque_amount, ar_cr FROM tbl_admin_actual_electricity WHERE cheque_number IS NOT NULL");
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['branch_code']) ?></td>
                                <td><?= htmlspecialchars($r['month_applicable']) ?></td>
                                <td><?= htmlspecialchars($r['cheque_number']) ?></td>
                                <td><?= htmlspecialchars($r['cheque_date']) ?></td>
                                <td><?= number_format((float)$r['cheque_amount'], 2) ?></td>
                                <td><?= htmlspecialchars($r['ar_cr']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#searchInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#recordsTable tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    $('#branchCodeInput').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const code = $(this).val().trim();
            if (!code) return;
            $.get('get-branch-info.php', { code: code }, function(data) {
                if (data.branch_name) {
                    $('#branchNameDisplay').val(data.branch_name);
                } else {
                    $('#branchNameDisplay').val('Not Found');
                }
            }, 'json');
        }
    });

    $('select[name="ar_cr"]').on('change', function() {
        if ($(this).val() === 'Ar.') {
            $('#amountGroup').show();
            $('#arAmountInput').attr('required', true);
        } else {
            $('#amountGroup').hide();
            $('#arAmountInput').val('');
            $('#arAmountInput').removeAttr('required');
        }
    });
});
</script>
<script>
$(document).ready(function () {
    $('#cheque_date').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),           
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', new Date()); 
});
</script>