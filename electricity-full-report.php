<?php
require_once 'connections/connection.php';

// Get distinct months for dropdown
$months_result = $conn->query("SELECT DISTINCT month_applicable AS month FROM tbl_admin_actual_electricity ORDER BY STR_TO_DATE(month_applicable, '%M %Y') DESC");
$months = [];
while ($row = $months_result->fetch_assoc()) {
    $months[] = $row['month'];
}

$selectedMonth = $_GET['month'] ?? date('F Y');
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="text-primary">Electricity Cheque Report</h5>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label for="month" class="form-label new-font-size mb-2">Select Month:</label>
                    <select name="month" id="month" class="form-select">
                        <option disabled <?= empty($_GET['month']) ? 'selected' : '' ?>>-- Select Month --</option>
                        <?php foreach ($months as $month): ?>
                            <option value="<?= htmlspecialchars($month) ?>" <?= ($month === $selectedMonth) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($month) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <form method="post" action="download-electricity-report.php" class="col-auto">
                    <input type="hidden" name="month" id="excelMonthInput" value="<?= htmlspecialchars($selectedMonth) ?>">
                    <button type="submit" class="btn btn-success">Download Excel</button>
                </form>
            </div>

            <div id="electricityReportContent">
                <?php include 'ajax-electricity-report.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('month').addEventListener('change', function () {
    const selectedMonth = this.value;
    document.getElementById('excelMonthInput').value = selectedMonth;

    fetch('ajax-electricity-report.php?month=' + encodeURIComponent(selectedMonth))
        .then(response => response.text())
        .then(data => {
            document.getElementById('electricityReportContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('electricityReportContent').innerHTML =
                '<div class="alert alert-danger">Failed to load report. Please try again.</div>';
        });
});
</script>
