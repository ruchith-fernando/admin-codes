<?php
include 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== false) {
        $rowCount = 0;
        $insertCount = 0;
        $errorCount = 0;

        // Skip the first row (header)
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $rowCount++;

            $mobile_no = trim($data[0]);
            $contribution = floatval(trim($data[1]));
            $hris_no = trim($data[2]);

            // Only proceed if HRIS is numeric
            if (ctype_digit($hris_no)) {
                $effective_from = '2025-05-01';

                $stmt = $conn->prepare("INSERT INTO tbl_admin_hris_contributions (hris_no, mobile_no, contribution_amount, effective_from) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssds", $hris_no, $mobile_no, $contribution, $effective_from);

                if ($stmt->execute()) {
                    $insertCount++;
                } else {
                    $errorCount++;
                }

                $stmt->close();
            } else {
                $errorCount++;
            }
        }

        fclose($handle);
        echo "<div style='padding:20px;font-family:sans-serif;'>";
        echo "<h3>Upload Summary</h3>";
        echo "Total Rows Processed: $rowCount<br>";
        echo "Inserted Successfully: $insertCount<br>";
        echo "Skipped/Errors: $errorCount<br>";
        echo "</div>";
    } else {
        echo "Failed to open file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Contribution CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="mb-4">Upload Company Contribution CSV</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select CSV File</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload CSV</button>
        </form>
    </div>
</div>

</body>
</html>
