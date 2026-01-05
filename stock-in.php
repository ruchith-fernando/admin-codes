<?php
session_start();
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/test-log.txt', date('Y-m-d H:i:s') . " - Loaded stock-in.php\n", FILE_APPEND);
file_put_contents(__DIR__ . '/test-log.txt', date('Y-m-d H:i:s') . " - POST triggered: " . print_r($_POST, true) . "\n", FILE_APPEND);

include 'connections/connection.php';
$message = ""; // ✅ Add this line
if (!isset($_SESSION['name'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "Session expired. Please login again.";
        exit;
    } else {
        header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

$logged_user = $_SESSION['hris'];


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    file_put_contents(__DIR__ . '/test-log.txt', date('Y-m-d H:i:s') . " - POST triggered: " . print_r($_POST, true) . "\n", FILE_APPEND);
    error_log("POST data: " . print_r($_POST, true));

    $item_code = $_POST['item_code'];
    $quantity = intval($_POST['quantity']);
    $input_price = floatval($_POST['unit_price']);
    $received_date = $_POST['received_date'];
    $tax_included = $_POST['tax_included'];

    $sscl = $vat = $sscl_amount = $vat_amount = 0;
    $base_unit_price = $input_price;

    $rateQuery = $conn->query("SELECT sscl_percentage, vat_percentage 
                               FROM tbl_admin_vat_sscl_rates 
                               ORDER BY effective_date DESC LIMIT 1");

    if ($rateQuery && $rateQuery->num_rows > 0) {
        $rate = $rateQuery->fetch_assoc();
        $sscl = floatval($rate['sscl_percentage']);
        $vat = floatval($rate['vat_percentage']);
    }

    if ($tax_included === 'yes') {
        $combined_tax_factor = 1 + ($sscl / 100);
        $price_after_sscl = $input_price / (1 + ($vat / 100));
        $base_unit_price = $price_after_sscl / $combined_tax_factor;
        $sscl_amount = $base_unit_price * ($sscl / 100);
        $vat_amount = ($base_unit_price + $sscl_amount) * ($vat / 100);
    } else {
        $sscl_amount = $base_unit_price * ($sscl / 100);
        $vat_amount = ($base_unit_price + $sscl_amount) * ($vat / 100);
    }

    error_log("Insert Data: " . print_r([
        'item_code' => $item_code,
        'quantity' => $quantity,
        'base_unit_price' => $base_unit_price,
        'received_date' => $received_date,
        'logged_user' => $logged_user,
        'sscl' => $sscl,
        'vat' => $vat,
        'sscl_amount' => $sscl_amount,
        'vat_amount' => $vat_amount,
        'tax_included' => $tax_included
    ], true));

    $stmt = $conn->prepare("INSERT INTO tbl_admin_stationary_stock_in 
        (item_code, quantity, unit_price, received_date, remaining_quantity, created_by, sscl_rate, vat_rate, sscl_amount, vat_amount, tax_included)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        error_log("❌ Prepare failed: " . $conn->error);
        echo "❌ Prepare failed: " . $conn->error;
        exit;
    }

    $stmt->bind_param("sisdissddds",
        $item_code,
        $quantity,
        $base_unit_price,
        $received_date,
        $quantity,
        $logged_user,
        $sscl,
        $vat,
        $sscl_amount,
        $vat_amount,
        $tax_included
    );

    if ($stmt->execute()) {
        error_log("✅ Stock In entry saved.");
        echo "✅ Stock In entry saved successfully.";
    } else {
        error_log("❌ Execute failed: " . $stmt->error);
        echo "❌ Failed to save entry: " . $stmt->error;
    }

    exit; // Stop rendering the HTML
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
        <div class="content font-size" id="contentArea">
            <div class="container-fluid">
                <div class="card shadow bg-white rounded p-4">
                    <h5 class="mb-4 text-primary">Add Stock In</h5>

                    <div id="stock-balance-info" class="alert alert-secondary fw-bold fs-6 d-none">
                        Available Balance: -
                    </div>

                    <form method="post" id="stockForm">
                        <?php if (!empty($message)) : ?>
            <div class="alert alert-danger"><?= $message ?></div>
        <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Item</label>
                        <select name="item_code" id="item_code" class="form-select" required></select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit Price (Base)</label>
                        <input type="text" name="unit_price" id="unit_price" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Received Date</label>
                        <input type="text" name="received_date" class="form-control" required id="received_date" autocomplete="off">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Does the Unit Price include taxes?</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tax_included" id="included" value="yes" checked>
                        <label class="form-check-label" for="included">Yes (Includes taxes)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tax_included" id="excluded" value="no">
                        <label class="form-check-label" for="excluded">No (Taxes will be added)</label>
                    </div>
                </div>

                <div id="tax-fields" class="row mb-3 d-none">
                    <div class="col-md-2">
                        <label class="form-label">SSCL (%)</label>
                        <input type="text" class="form-control" id="sscl" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT (%)</label>
                        <input type="text" class="form-control" id="vat" readonly>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div>
                            <label class="form-label">Final Price (Preview)</label>
                            <input type="text" id="final_price" class="form-control fw-bold text-success" readonly>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Entry</button>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
      </div>
      <div class="modal-body">
        Stock In entry saved successfully.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal" autofocus>OK</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    // Initialize Select2
    $('#item_code').select2({
        placeholder: 'Search item code or description',
        ajax: {
            url: 'fetch-items.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        },
        minimumInputLength: 1,
        width: '100%'
    });

    // Initialize datepicker
    $('#received_date').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', new Date());

    // Fetch balance on item select
    $('#item_code').on('select2:select', function (e) {
        var item_code = e.params.data.id;
        $.ajax({
            url: 'get-stock-balance.php',
            type: 'GET',
            data: { item_code: item_code },
            success: function (response) {
                var data = JSON.parse(response);
                let balance = data.balance ?? 0;
                $('#stock-balance-info')
                    .removeClass('d-none')
                    .removeClass('alert-danger alert-success')
                    .addClass(balance > 0 ? 'alert-success' : 'alert-danger')
                    .text('Available Balance: ' + balance);
            },
            error: function () {
                $('#stock-balance-info')
                    .removeClass('d-none')
                    .addClass('alert-danger')
                    .text('Available Balance: Error loading');
            }
        });
    });

    // Handle tax inclusion toggle
    $('input[name="tax_included"]').on('change', function () {
        if ($(this).val() === 'no') {
            $.getJSON('get-latest-tax-rates.php', function (data) {
                $('#sscl').val(data.sscl);
                $('#vat').val(data.vat);
                $('#tax-fields').removeClass('d-none');

                setTimeout(() => {
                    if ($('#unit_price').val()) {
                        calculateFinalPrice();
                    }
                }, 100);
            });
        } else {
            $('#tax-fields').addClass('d-none');
            $('#sscl, #vat, #final_price').val('');
        }
    });

    // Live preview final price
    $('#unit_price').on('input', function () {
        if ($('#excluded').is(':checked')) {
            calculateFinalPrice();
        }
    });

    function calculateFinalPrice() {
        let base = parseFloat($('#unit_price').val());
        let sscl = parseFloat($('#sscl').val());
        let vat = parseFloat($('#vat').val());

        if (isNaN(base) || isNaN(sscl) || isNaN(vat)) {
            $('#final_price').val('');
            return;
        }

        let ssclAmount = base * (sscl / 100);
        let vatAmount = (base + ssclAmount) * (vat / 100);
        let final = base + ssclAmount + vatAmount;
        $('#final_price').val(final.toFixed(2));
    }

    // ✅ AJAX Form Submit Handler
    $('#stockForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'stock-in.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.includes("✅")) {
                    const modal = new bootstrap.Modal(document.getElementById('successModal'));
                    modal.show();
                    $('#stockForm')[0].reset();
                    $('#item_code').val(null).trigger('change');
                    $('#stock-balance-info').addClass('d-none');
                    $('#tax-fields').addClass('d-none');
                } else {
                    alert("Error: " + response);
                }
            },
            error: function (xhr, status, error) {
                alert("❌ AJAX Error: " + xhr.responseText);
            }
        });
    });

    // ✅ Fix modal backdrop lock issue
    $('#successModal').on('hidden.bs.modal', function () {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css('padding-right', '');
    });

    // Optional: show modal if success message is returned (fallback)
    <?php if ($message === "Stock In entry saved successfully.") : ?>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    <?php endif; ?>
});
</script>




