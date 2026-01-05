<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tea Service Budget vs Actual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .chart-container { width: 100%; height: 500px; }
    </style>
</head>
<body class="bg-light">
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
        <div class="card shadow bg-white rounded p-4">
            <h3 class="mb-4">Tea Service Budget vs Actual</h3>

            <div class="mb-3 d-flex align-items-center">
                <label for="chartType" class="form-label me-2 mb-0">Select Chart Type:</label>
                <select id="chartType" class="form-select w-auto">
                    <option value="bar" selected>Bar</option>
                    <option value="line">Line</option>
                </select>
            </div>

            <div class="chart-container">
                <canvas id="teaBudgetChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
include 'connections/connection.php';

$query = "
SELECT 
    b.month_year,
    b.budget_amount,
    IFNULL(SUM(a.total_price), 0) AS actual_amount
FROM 
    tbl_admin_budget_tea_service b
LEFT JOIN 
    tbl_admin_tea_service a ON b.month_year = a.month_year
GROUP BY 
    b.month_year, b.budget_amount
ORDER BY 
    STR_TO_DATE(b.month_year, '%M %Y');
";

$result = mysqli_query($conn, $query);

$labels = [];
$budgets = [];
$actuals = [];

while($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['month_year'];
    $budgets[] = (float)$row['budget_amount'];
    // Show gap in chart for 0 actuals
    $actuals[] = $row['actual_amount'] > 0 ? (float)$row['actual_amount'] : null;
}
?>

<script>
let chartInstance = null;

const chartLabels = <?= json_encode($labels) ?>;
const chartBudgets = <?= json_encode($budgets) ?>;
const chartActuals = <?= json_encode($actuals) ?>;

function renderChart(type) {
    const ctx = document.getElementById('teaBudgetChart').getContext('2d');

    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(ctx, {
        type: type,
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Budget (LKR)',
                    data: chartBudgets,
                    borderWidth: 2,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: type === 'line' ? false : true,
                    tension: type === 'line' ? 0.4 : 0,
                    pointRadius: type === 'line' ? 5 : 0
                },
                {
                    label: 'Actual (LKR)',
                    data: chartActuals,
                    borderWidth: 2,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: type === 'line' ? false : true,
                    tension: type === 'line' ? 0.4 : 0,
                    pointRadius: type === 'line' ? 5 : 0
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Budget vs Actual'
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            },
            elements: {
                point: {
                    hoverRadius: 6
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (LKR)'
                    }
                }
            }
        }
    });
}

// Initial render
renderChart('bar');

// Dropdown change
document.getElementById('chartType').addEventListener('change', function() {
    renderChart(this.value);
});
</script>
</body>
</html>
