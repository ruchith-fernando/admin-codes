<?php require_once 'connections/connection.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Report - Postage & Stamp</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Department, Serial No, or Postal Serial No...">
                </div>
            </div>

            <div class="row g-2 align-items-end mb-4">
                <div class="col-md-3">
                    <label for="fromDate" class="form-label new-font-size">From Date:</label>
                    <input type="text" id="fromDate" class="form-control datepicker" placeholder="Select From Date">
                </div>
                <div class="col-md-3">
                    <label for="toDate" class="form-label new-font-size">To Date:</label>
                    <input type="text" id="toDate" class="form-control datepicker" placeholder="Select To Date">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary" id="filterBtn">Filter</button>
                </div>
                <div class="col-md-3 text-end">
                    <a id="downloadExcelBtn" class="btn btn-success" target="_blank">Download Excel</a>
                </div>
            </div>

            <div id="overviewReportContent">
                <?php include 'ajax-postage-report-filter.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
$('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
});

function loadFilteredResults() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const search = document.getElementById('searchInput').value;
    const target = document.getElementById('overviewReportContent');

    if (!fromDate || !toDate) {
        alert("Please select both From and To dates.");
        return;
    }

    const formData = new URLSearchParams();
    formData.append('from', fromDate);
    formData.append('to', toDate);
    formData.append('search', search);

    fetch('ajax-postage-report-filter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.text())
    .then(data => {
        target.innerHTML = data;
    })
    .catch(() => {
        target.innerHTML = '<div class="alert alert-danger">Failed to load data.</div>';
    });

    // Update Excel export link
    document.getElementById('downloadExcelBtn').href =
        `export-postage-report-excel.php?from=${fromDate}&to=${toDate}&search=${encodeURIComponent(search)}`;
}

document.getElementById('filterBtn').addEventListener('click', loadFilteredResults);
document.getElementById('searchInput').addEventListener('keyup', loadFilteredResults);
</script>
