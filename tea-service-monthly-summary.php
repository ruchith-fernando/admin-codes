<?php
require_once 'connections/connection.php';

// Get available months
$months_result = $conn->query("SELECT DISTINCT month_year FROM tbl_admin_tea_service ORDER BY STR_TO_DATE(month_year, '%M %Y')");
$months = [];
while ($row = $months_result->fetch_assoc()) {
    $months[] = $row['month_year'];
}

$selectedMonth = $_GET['month'] ?? null;
$report = [];

if ($selectedMonth) {
    // Get monthly total and cumulative
    $data = $conn->query("
        SELECT 
            month_year,
            SUM(total_price) AS total_amount
        FROM tbl_admin_tea_service
        GROUP BY month_year
        ORDER BY STR_TO_DATE(month_year, '%M %Y')
    ");

    $cumulative = 0;
    while ($row = $data->fetch_assoc()) {
        $cumulative += $row['total_amount'];
        $row['cumulative_total'] = $cumulative;
        $report[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tea Service Monthly Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        body, td, th { font-size: 0.85rem; }
        .wide-table { min-width: 1000px; }
    </style>
</head>
<body class="bg-light">
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Tea Service Monthly Summary</h3>
            </div>
            <form method="get" class="mb-3">
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label for="month" class="col-form-label">Select Month:</label>
                    </div>
                    <div class="col-auto">
                        <select name="month" id="month" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Choose a month --</option>
                            <?php foreach ($months as $month): ?>
                                <option value="<?= htmlspecialchars($month) ?>" <?= ($month === $selectedMonth) ? 'selected' : '' ?>><?= htmlspecialchars($month) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <?php if ($selectedMonth && !empty($report)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center wide-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Month</th>
                            <th>Total Price (Rs)</th>
                            <th>Cumulative Total (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $count = 1;
                    foreach ($report as $row):
                        if ($row['month_year'] !== $selectedMonth) continue;
                    ?>
                        <tr>
                            <td><?= $count++ ?></td>
                            <td><?= htmlspecialchars($row['month_year']) ?></td>
                            <td><?= number_format($row['total_amount'], 2) ?></td>
                            <td><?= number_format($row['cumulative_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($selectedMonth): ?>
                <div class="alert alert-warning">No data found for <?= htmlspecialchars($selectedMonth) ?>.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
