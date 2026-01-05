<?php
// stock-out.php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['hris'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$logged_user = $_SESSION['hris'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_code = $_POST['item_code'];
    $quantity_needed = intval($_POST['quantity']);
    $issued_date = $_POST['issued_date'];
    $branch_code = $_POST['branch_code'] ?? null;
    $branch_name = null;

    if ($branch_code) {
        $stmtBranch = $conn->prepare("SELECT branch_name FROM tbl_admin_branch_information WHERE branch_id = ?");
        $stmtBranch->bind_param("s", $branch_code);
        $stmtBranch->execute();
        $stmtBranch->bind_result($branch_name);
        $stmtBranch->fetch();
        $stmtBranch->close();
    }

    // Fetch the oldest available stock-in record with enough quantity
    $fifoQuery = $conn->prepare("SELECT id, unit_price, sscl_rate, vat_rate FROM tbl_admin_stationary_stock_in WHERE item_code = ? AND remaining_quantity > 0 ORDER BY received_date ASC, id ASC LIMIT 1");
    $fifoQuery->bind_param("s", $item_code);
    $fifoQuery->execute();
    $result = $fifoQuery->get_result();

    if ($row = $result->fetch_assoc()) {
        $stock_in_id = $row['id'];
        $unit_price = floatval($row['unit_price']);
        $sscl_rate = floatval($row['sscl_rate']);
        $vat_rate = floatval($row['vat_rate']);

        // Use stored per-unit tax values from stock-in
        $unit_sscl = floatval($row['sscl_amount']);
        $unit_vat  = floatval($row['vat_amount']);

        // Calculate totals based on quantity
        $sscl_total = $unit_sscl * $quantity_needed;
        $vat_total  = $unit_vat * $quantity_needed;
        $subtotal   = $unit_price * $quantity_needed;
        $total_cost = $subtotal + $sscl_total + $vat_total;
        $final_price_per_unit = $unit_price + $unit_sscl + $unit_vat;



        // Insert stock-out request (pending approval)
        $insertOut = $conn->prepare("INSERT INTO tbl_admin_stationary_stock_out (item_code, quantity, issued_date, total_cost, branch_code, branch_name, created_by, stock_in_id, unit_price, sscl_amount, vat_amount, unit_final_price, status, dual_control_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
        $insertOut->bind_param("sisdssssdddd",
            $item_code,
            $quantity_needed,
            $issued_date,
            $total_cost,
            $branch_code,
            $branch_name,
            $logged_user,
            $stock_in_id,
            $unit_price,
            $sscl_total,
            $vat_total,
            $final_price_per_unit
        );

        if ($insertOut->execute()) {
            $message = "Stock-out request submitted successfully for approval.";
        } else {
            $message = "Error saving request: " . $insertOut->error;
        }
    } else {
        $message = "No available stock-in record found for this item.";
    }
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
       

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
        top: 0 !important;
        right: 10px !important;
    }

    .select2-container {
        width: 100% !important;
    }
    </style>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Request Stock</h5>
            <div id="stock-balance-info" class="alert alert-secondary fw-bold fs-6 d-none">
                Available Balance: -
            </div>
            <div id="issue-error-msg" class="alert alert-danger fw-bold d-none"></div>
            <form id="stockOutForm">
                <input type="hidden" id="current_balance" name="current_balance" value="0">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Item</label>
                        <select name="item_code" id="item_code" class="form-select" required style="width:100%;"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch (Optional)</label>
                        <select name="branch_code" id="branch_code" class="form-select" style="width:100%;"></select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Quantity to Request</label>
                        <input type="number" name="quantity" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Request Date</label>
                        <input type="text" name="issued_date" id="issued_date" class="form-control" required readonly>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Request</button>
            </form>
        </div>
    </div>
</div>
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
      </div>
      <div class="modal-body" id="successMsg">
        Stock-out request submitted successfully for approval.
        </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal" autofocus>OK</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#item_code').select2({
        placeholder: 'Search item code or description',
        ajax: {
            url: 'fetch-items.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 1,
        width: '100%'
    });

    $('#branch_code').select2({
        placeholder: 'Search branch code or name',
        ajax: {
            url: 'fetch-branches.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 1,
        width: '100%'
    });

    // $('#issued_date').datepicker({
    //     format: 'yyyy-mm-dd',
    //     endDate: new Date(),
    //     autoclose: true,
    //     todayHighlight: true
    // }).datepicker('setDate', new Date());

    // ✅ Show stock balance on item selection
    $('#item_code').on('select2:select', function (e) {
        var item_code = e.params.data.id;

        $.ajax({
            url: 'get-stock-balance.php',
            type: 'GET',
            data: { item_code: item_code },
            success: function(response) {
                var data = JSON.parse(response);
                let balance = data.balance ?? 0;

                $('#current_balance').val(balance); // ✅ Store balance

                $('#stock-balance-info')
                    .removeClass('d-none alert-danger alert-success alert-secondary')
                    .addClass(balance > 0 ? 'alert-success' : 'alert-danger')
                    .text('Available Balance: ' + balance);
            },
            error: function() {
                $('#stock-balance-info')
                    .removeClass('d-none alert-success alert-secondary')
                    .addClass('alert-danger')
                    .text('Available Balance: Error loading');
            }
        });
    });

    // ✅ Validate stock before form submission
    $('form').on('submit', function(e) {
        let current_balance = parseFloat($('#current_balance').val());
        let requested_qty = parseFloat($('input[name="quantity"]').val());
        let errorDiv = $('#issue-error-msg');

        // Hide old error
        errorDiv.addClass('d-none').text('');

        if (isNaN(current_balance) || isNaN(requested_qty)) {
            e.preventDefault();
            errorDiv.removeClass('d-none').text("Invalid quantity or stock balance.");
            return;
        }

        if (current_balance === 0) {
            e.preventDefault();
            errorDiv.removeClass('d-none').text("Cannot issue stock. Available stock is 0.");
            return;
        }

        if (requested_qty > current_balance) {
            e.preventDefault();
            errorDiv.removeClass('d-none').text("Cannot issue " + requested_qty + " units. Only " + current_balance + " in stock.");
            return;
        }
    });
});
// ✅ AJAX Submit for stock out
$('#stockOutForm').on('submit', function(e) {
    e.preventDefault();

    // Remove commas just in case you added 1000-separators later
    $('.thousand-separator').each(function () {
        this.value = this.value.replace(/,/g, '');
    });

    // Reuse validation
    let current_balance = parseFloat($('#current_balance').val());
    let requested_qty = parseFloat($('input[name="quantity"]').val());
    let errorDiv = $('#issue-error-msg');

    errorDiv.addClass('d-none').text('');

    if (isNaN(current_balance) || isNaN(requested_qty)) {
        errorDiv.removeClass('d-none').text("Invalid quantity or stock balance.");
        return;
    }

    if (current_balance === 0) {
        errorDiv.removeClass('d-none').text("Cannot issue stock. Available stock is 0.");
        return;
    }

    if (requested_qty > current_balance) {
        errorDiv.removeClass('d-none').text("Cannot issue " + requested_qty + " units. Only " + current_balance + " in stock.");
        return;
    }

    // Proceed with AJAX
    $.ajax({
        url: 'stock-out.php',
        type: 'POST',
        data: $('#stockOutForm').serialize(),
        success: function(response) {
            if (response.includes("successfully")) {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                $('#stockOutForm')[0].reset();
                $('#item_code').val(null).trigger('change');
                $('#branch_code').val(null).trigger('change');
                $('#stock-balance-info').addClass('d-none');
                successModal.show();
            } else {
                $('#issue-error-msg').removeClass('d-none').text(response);
            }
        },
        error: function(xhr) {
            $('#issue-error-msg').removeClass('d-none').text("AJAX Error: " + xhr.responseText);
        }
    });
});

<?php if (strpos($message, "Stock-out request submitted successfully") !== false): ?>
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
<?php endif; ?>
</script>
<script>

$(document).ready(function () {
    // Set today's date in YYYY-MM-DD format
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const formattedDate = `${yyyy}-${mm}-${dd}`;

    $('#issued_date').val(formattedDate);
});

</script>
