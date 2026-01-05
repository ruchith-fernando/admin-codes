<div class="content font-size">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm">
            <h5 class="mb-4 text-primary">Secure Document Print Logs</h5>

            <div class="row mb-3">
                <div class="col-md-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Document #, HRIS, or User">
                </div>
                <div class="col-md-3">
                    <input type="text" id="fromDate" class="form-control datepicker" placeholder="From Date" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <input type="text" id="toDate" class="form-control datepicker" placeholder="To Date" autocomplete="off">
                </div>
                <div class="col-md-3 d-flex">
                    <button id="exportBtn" class="btn btn-success me-2">Export to Excel</button>
                </div>
            </div>


            <div id="printLogsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary"></div>
                    <div>Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function loadPrintLogs(page = 1) {
    const search = $('#searchInput').val().trim();
    const fromDate = $('#fromDate').val();
    const toDate = $('#toDate').val();

    $('#printLogsContent').load('ajax-secure-print-logs-body.php', {
        page: page,
        search: search,
        from_date: fromDate,
        to_date: toDate
    });
}

$(document).ready(function () {
    // Initialize Bootstrap datepickers
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        endDate: '0d'
    });

    // Initial load
    loadPrintLogs();

    // Handle pagination click
    $(document).on('click', '.pagination-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadPrintLogs(page);
    });

    // Trigger reload on filter input change
    $('#searchInput').on('keyup', function () {
        loadPrintLogs(1);
    });

    $('#fromDate, #toDate').on('change', function () {
        // Delay added to avoid rapid double-loading from datepicker
        setTimeout(() => loadPrintLogs(1), 200);
    });

    // Export filtered results to Excel
    $('#exportBtn').on('click', function () {
        const search = encodeURIComponent($('#searchInput').val().trim());
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        const url = `export-secure-print-logs-excel.php?search=${search}&from_date=${fromDate}&to_date=${toDate}`;
        window.location.href = url;
    });
});
</script>


