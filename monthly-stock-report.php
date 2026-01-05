<?php
// monthly-stock-report.php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['name'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Stock Summary for <?= date("F Y", strtotime($month)) ?></h5>

            <form id="monthFilterForm" class="d-flex align-items-center mb-4">
                <label class="form-label me-2 mb-0">Select Month:</label>
                <input type="month" id="monthInput" name="month" value="<?= $month ?>" class="form-control form-control-sm w-auto me-2">
                <button type="submit" class="btn btn-secondary btn-sm me-2">View</button>
                <a id="downloadExcel" href="#" class="btn btn-success">Download Excel</a>
            </form>

            <h6>Remaining Stock Value</h6>
            <input type="text" id="searchStock" class="form-control form-control-sm search-box" placeholder="Search...">
            <div id="remainingStockTableArea"></div>

            <h6 class="mt-5">Total Spent This Month</h6>
            <input type="text" id="searchSpent" class="form-control form-control-sm search-box" placeholder="Search...">
            <div id="spentTableArea"></div>
        </div>
    </div>
</div>

<script>
let currentMonth = "<?= $month ?>";

function loadRemainingStock(page = 1, query = '') {
    $.get("load-remaining-stock.php", { page, query, month: currentMonth }, function (data) {
        $('#remainingStockTableArea').html(data);
    });
}

function loadStockSpent(page = 1, query = '') {
    $.get("load-stock-spent.php", { page, query, month: currentMonth }, function (data) {
        $('#spentTableArea').html(data);
    });
}

function updateExcelLink() {
    const query = encodeURIComponent($('#searchStock').val());
    $('#downloadExcel').attr('href', `export-stock-summary-excel.php?month=${currentMonth}&query=${query}`);
}

loadRemainingStock();
loadStockSpent();
updateExcelLink();

$('#searchStock').on('keyup', function () {
    loadRemainingStock(1, $(this).val());
});
$('#searchSpent').on('keyup', function () {
    loadStockSpent(1, $(this).val());
});

$(document).on('click', '.paginate-stock', function (e) {
    e.preventDefault();
    let page = $(this).data('page');
    let query = $('#searchStock').val();
    loadRemainingStock(page, query);
});

$(document).on('click', '.paginate-spent', function (e) {
    e.preventDefault();
    let page = $(this).data('page');
    let query = $('#searchSpent').val();
    loadStockSpent(page, query);
});

$('#monthFilterForm').on('submit', function (e) {
    e.preventDefault();
    currentMonth = $(this).find('input[name="month"]').val();
    loadRemainingStock();
    loadStockSpent();
    updateExcelLink();
    window.history.replaceState({}, document.title, "main.php");
});
</script>
