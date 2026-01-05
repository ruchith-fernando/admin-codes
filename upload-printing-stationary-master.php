<?php
include 'connections/connection.php';

$successCount = 0;
$errorMessages = [];

function toSentenceCase($string) {
    $string = strtolower($string);
    return ucfirst($string);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $row++;
            if (count($data) >= 2) {
                $code = trim($data[0]);
                $desc = trim($data[1]);

                // Convert to sentence case
                $desc = strtoupper($desc);

                $stmt = $conn->prepare("INSERT INTO tbl_admin_print_stationary_master (item_code, item_description) VALUES (?, ?)");
                $stmt->bind_param("ss", $code, $desc);

                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    $errorMessages[] = "Row $row: " . $stmt->error;
                }
            } else {
                $errorMessages[] = "Row $row: Invalid format.";
            }
        }
        fclose($handle);
    } else {
        $errorMessages[] = "Unable to open the CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Printing & Stationary Master</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h4 class="mb-3 text-primary">Upload Printing & Stationary Master CSV</h4>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Choose CSV File</label>
                <input type="file" name="csv_file" class="form-control" required accept=".csv">
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>

        <?php if (!empty($successCount)): ?>
            <div class="alert alert-success mt-4">
                <?= $successCount ?> record(s) uploaded successfully.
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessages)): ?>
            <div class="alert alert-danger mt-4">
                <ul>
                    <?php foreach ($errorMessages as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
