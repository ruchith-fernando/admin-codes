<?php
require_once 'connections/connection.php';

// Get available months
$months_result = $conn->query("SELECT DISTINCT month_applicable AS month FROM tbl_admin_actual_electricity ORDER BY STR_TO_DATE(month_applicable, '%M %Y')");
$months = [];
while ($row = $months_result->fetch_assoc()) {
    $months[] = $row['month'];
}

// Default month
$selectedMonth = $_GET['month'] ?? date('F Y');
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Electricity Budget vs Actual Report - Branch Breakdown</h5>

            <div class="row g-2 align-items-center mb-4">
                <div class="col-auto">
                    <label for="month" class="col-form-label new-font-size mb-2">Select Month:</label>
                </div>
                <div class="col-auto">
                    <select name="month" id="month" class="form-select">
                        <option value="">-- Choose a month --</option>
                        <?php foreach ($months as $month): ?>
                            <option value="<?= htmlspecialchars($month) ?>" <?= ($month === $selectedMonth) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($month) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="budgetVsActualReportContent">
                <?php include 'ajax-electricity-budget-vs-actual.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('month').addEventListener('change', function () {
    const selectedMonth = this.value;
    fetch('ajax-electricity-budget-vs-actual.php?month=' + encodeURIComponent(selectedMonth))
        .then(response => response.text())
        .then(html => {
            document.getElementById('budgetVsActualReportContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('budgetVsActualReportContent').innerHTML =
                '<div class="alert alert-danger">Failed to load report.</div>';
        });
});
</script>
