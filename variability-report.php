<?php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], ['manager', 'super-admin'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Variability Report – Stationary Grand Total</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<div class="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Stationary Spending Variability by Branch</h5>

            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Branch Code</th>
                        <th>Mean (Avg)</th>
                        <th>Standard Deviation</th>
                        <th>Variance</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $query = "
                    SELECT 
                        branch_code,
                        AVG(grand_total) AS mean,
                        STDDEV(grand_total) AS std_dev,
                        VARIANCE(grand_total) AS variance
                    FROM tbl_admin_actual_stationary
                    WHERE grand_total > 0
                    GROUP BY branch_code
                    ORDER BY variance DESC
                ";

                $result = $conn->query($query);

                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['branch_code']}</td>
                        <td>" . number_format($row['mean'], 2) . "</td>
                        <td>" . number_format($row['std_dev'], 2) . "</td>
                        <td>" . number_format($row['variance'], 2) . "</td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
