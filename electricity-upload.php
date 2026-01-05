<?php
include 'connections/connection.php';

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if (isset($_POST['uploadCsv'])) {
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
        $fileTmpPath = $_FILES['csvFile']['tmp_name'];
        $handle = fopen($fileTmpPath, "r");

        if ($handle !== FALSE) {
            $rowCount = 0;
            $insertedRows = 0;
            $errorRows = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowCount++;

                if ($rowCount == 1) {
                    // Skip header row
                    continue;
                }

                // Fetch and sanitize fields
                $branch_id = intval($data[0]);
                $branch_name = $conn->real_escape_string($data[1]);
                $payment_month = $conn->real_escape_string($data[2]);
                $units = isset($data[3]) ? intval($data[3]) : 0;
                $amount = isset($data[4]) ? number_format((float)$data[4], 2, '.', '') : '0.00';

                // SQL query to insert
                $sql = "INSERT INTO tbl_admin_electricity_cost (branch_id, branch_name, payment_month, units, amount) 
                        VALUES ('$branch_id', '$branch_name', '$payment_month', '$units', '$amount')";

                if ($conn->query($sql)) {
                    $insertedRows++;
                } else {
                    $errorRows++;
                }
            }
            fclose($handle);

            $message = "CSV upload completed. <br>Inserted: <strong>$insertedRows</strong> rows. <br>Failed: <strong>$errorRows</strong> rows.";
        } else {
            $message = "Failed to open uploaded CSV file.";
        }
    } else {
        $message = "No file uploaded or file error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Electricity CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
        <div class="card p-4 shadow-sm mt-2">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Upload Electricity Consumption CSV</h5>
                        </div>
                        <div class="card-body">

                            <?php if (!empty($message)) echo $message; ?>

                            <form method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csvFile" class="form-label">Select CSV File</label>
                                    <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="uploadCsv" class="btn btn-primary">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script>
    function toggleMenu() {
        var sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("hidden");

        // Optionally toggle body overflow to prevent scrolling when sidebar is visible
        document.body.classList.toggle("no-scroll", sidebar.classList.contains("hidden"));
    }
</script>

</body>
</html>
