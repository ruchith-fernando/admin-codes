<?php
require_once 'connections/connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$inserted = 0;
$skipped = 0;
$message = '';
$skipped_rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        $row = 0;

        while (($data = fgetcsv($handle, 0, ",", '"')) !== FALSE) {
            // Skip header row
            if ($row === 0 && str_contains(strtolower(implode(',', $data)), 'branch')) {
                $row++;
                continue;
            }

            // Correct indexes for your actual CSV
            $branch_code = trim($data[0] ?? '');
            $branch = trim($data[1] ?? '');
            $units_raw = trim($data[2] ?? '');
            $amount_raw = trim($data[3] ?? '');
            $month = trim($data[4] ?? '');

            // Clean commas in numbers (e.g., 20,225.14 → 20225.14)
            $units = str_replace(",", "", $units_raw);
            $amount = str_replace(",", "", $amount_raw);

            $reason = '';

            // Required fields validation
            if ($branch_code === '') {
                $reason = 'Missing branch code';
            } elseif ($branch === '') {
                $reason = 'Missing branch';
            } elseif ($month === '') {
                $reason = 'Missing month';
            }

            if ($reason === '') {
                try {
                    $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_electricity 
                        (branch_code, branch, actual_units, total_amount, month_applicable) 
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $branch_code, $branch, $units, $amount, $month);
                    $stmt->execute();
                    $inserted++;
                } catch (mysqli_sql_exception $e) {
                    $skipped++;
                    $skipped_rows[] = [
                        'branch_code' => $branch_code,
                        'branch' => $branch,
                        'units' => $units_raw,
                        'amount' => $amount_raw,
                        'month' => $month,
                        'reason' => 'MySQL error: ' . $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
                $skipped_rows[] = [
                    'branch_code' => $branch_code,
                    'branch' => $branch,
                    'units' => $units_raw,
                    'amount' => $amount_raw,
                    'month' => $month,
                    'reason' => $reason
                ];
            }

            $row++;
        }

        fclose($handle);
        $message = "<div class='alert alert-success'>Upload complete. Inserted: $inserted, Skipped: $skipped.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to open the uploaded file.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Actual Electricity Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .branch-cell { min-width: 200px; text-align: left; }
    </style>
</head>
<body class="bg-light">
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h3 class="mb-4">Upload Actual Electricity CSV</h3>

            <?= $message ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csv_file" class="form-label">Select CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload File</button>
            </form>

            <div class="mt-4">
                <h6>Expected Fields from Excel:</h6>
                <ul>
                    <li><strong>Branch Code</strong> (Column A)</li>
                    <li><strong>Branch</strong> (Column B)</li>
                    <li><strong>Units</strong> (Column AN)</li>
                    <li><strong>Amount</strong> (Column AO)</li>
                    <li><strong>Month</strong> (Column AP)</li>
                </ul>
            </div>

            <?php if (!empty($skipped_rows)): ?>
                <div class="mt-5">
                    <h5>Skipped Records (<?= count($skipped_rows) ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Branch Code</th>
                                    <th>Branch</th>
                                    <th>Units</th>
                                    <th>Amount</th>
                                    <th>Month</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skipped_rows as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($row['branch_code']) ?></td>
                                        <td><?= htmlspecialchars($row['branch']) ?></td>
                                        <td><?= htmlspecialchars($row['units']) ?></td>
                                        <td><?= htmlspecialchars($row['amount']) ?></td>
                                        <td><?= htmlspecialchars($row['month']) ?></td>
                                        <td class="text-danger"><?= htmlspecialchars($row['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
