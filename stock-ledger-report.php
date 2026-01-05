<?php
// stock-ledger-report.php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$logged_user = $_SESSION['name'];
$month = $_GET['month'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$item_code = $_GET['item_code'] ?? '';
$type_filter = $_GET['type'] ?? '';
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
.select2-container { width: 100% !important; }
</style>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Stock Ledger Report - Printing & Stationary</h5>
            <form id="ledgerFilterForm" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="text" name="from" id="from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="text" name="to" id="to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-6">
                    <select name="item_code" id="item_code" class="form-select"></select>
                </div>
                <div class="col-md-6 d-flex align-items-end justify-content-between">
                    <div class="d-flex flex-grow-1 me-3">
                        <select name="type" class="form-select me-2">
                            <option value="">All</option>
                            <option value="IN" <?= ($type_filter === 'IN') ? 'selected' : '' ?>>IN</option>
                            <option value="OUT" <?= ($type_filter === 'OUT') ? 'selected' : '' ?>>OUT</option>
                        </select>
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                    </div>
                    <a id="exportExcelBtn" class="btn btn-success" target="_blank">Export to Excel</a>
                </div>
            </form>
            <div id="ledgerTableContent"></div>
        </div>
    </div>
</div>

<script>
function loadLedgerTable(query = '') {
    $('#ledgerTableContent').html('<div class="text-center py-5">Loading...</div>');
    $.get('stock-ledger-content.php', query, function(data) {
        $('#ledgerTableContent').html(data);
    });
    $('#exportExcelBtn').attr('href', 'export-stock-ledger-excel.php?' + query);
}

$(document).ready(function () {
    loadLedgerTable($('#ledgerFilterForm').serialize());

    $('#ledgerFilterForm').on('submit', function (e) {
        e.preventDefault();
        loadLedgerTable($(this).serialize());
            window.history.replaceState({}, document.title, "main.php");

    });

    $(document).on('click', '.pagination a.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page') || 1;
        const currentQuery = $('#ledgerFilterForm').serialize();
        loadLedgerTable(currentQuery + '&page=' + page);
            window.history.replaceState({}, document.title, "main.php");

    });

    $('#item_code').select2({
        placeholder: 'Search Item Code',
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

    $('#from, #to').datepicker({
        format: 'yyyy-mm-dd',
        endDate: new Date(),
        autoclose: true,
        todayHighlight: true
    });
});
</script>