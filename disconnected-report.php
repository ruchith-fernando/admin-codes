<?php
// disconnected-report.php
include 'connections/connection.php';

$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';

$where = "WHERE t1.connection_status = 'disconnected'";

if ($from && $to) {
    $where .= " AND DATE(t1.disconnection_date) BETWEEN '$from' AND '$to'";
} elseif ($from) {
    $where .= " AND DATE(t1.disconnection_date) = '$from'";
}

$sql = "
    SELECT t1.*, t2.Name, t2.NIC, t2.Designation, t2.Branch, t2.Resignation_Effective_Date 
    FROM tbl_admin_mobile_issues t1
    LEFT JOIN tbl_admin_employee_resignations t2 ON t2.HRIS = t1.hris_no
    $where
    ORDER BY t1.disconnection_date DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disconnected Mobile Numbers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        textarea.copy-area {
            font-family: monospace;
            white-space: pre;
        }
    </style>
</head>
<body class="bg-light">
    <div class="sidebar" id="sidebar">
        <?php include 'side-menu.php'; ?>
    </div>
    <div class="content font-size" id="contentArea">
        <div class="container">
            <div class="card shadow bg-white rounded p-4">
                <h2 class="mb-4">Disconnected Mobile Numbers Report</h2>
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="from" class="form-label">From Date</label>
                        <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="to" class="form-label">To Date</label>
                        <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="export-disconnected-excel.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-success">Download Excel</a>
                    </div>
                </form>

                <div class="table-responsive font-size mt-4">
                    <table class="table table-bordered table-striped align-middle text-start">
                        <thead class="table-primary">
                            <tr>
                                <th>HRIS</th>
                                <th>Name</th>
                                <th>Company Hierarchy</th>
                                <th>Designation</th>
                                <th>Mobile Number</th>
                                <th>Voice / Data</th>
                                <th>Date Joined</th>
                                <th>Date Resigned</th>
                                <th>Connection Status</th>
                                <th>Disconnection Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $copyRows = [];
                            while ($row = $result->fetch_assoc()): 
                                $copyRows[] = $row;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['hris_no']) ?></td>
                                    <td><?= htmlspecialchars($row['display_name']) ?></td>
                                    <td><?= htmlspecialchars($row['company_hierarchy']) ?></td>
                                    <td><?= htmlspecialchars($row['designation']) ?></td>
                                    <td><?= htmlspecialchars($row['mobile_no']) ?></td>
                                    <td><?= htmlspecialchars($row['voice_data']) ?></td>
                                    <td><?= htmlspecialchars($row['date_joined']) ?></td>
                                    <td><?= htmlspecialchars($row['Resignation_Effective_Date']) ?></td>
                                    <td><?= htmlspecialchars($row['connection_status']) ?></td>
                                    <td><?= htmlspecialchars($row['disconnection_date']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
