<?php

// upload-security-budget.php

require_once 'connections/connection.php';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Upload Security Budget CSV</title>

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

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">

            <h5 class="mb-4 text-primary">Upload Security Budget CSV</h5>



            <?php

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

                $file = $_FILES['csv_file']['tmp_name'];

                $handle = fopen($file, 'r');

                $header = fgetcsv($handle);

                $successCount = 0;

                $errorMessages = [];



                while (($row = fgetcsv($handle)) !== false) {

                    list($branch_code, $branch, $no_of_shifts, $rate, $month) = $row;



                    if (!$branch_code || !$branch || !$rate || !$month) {

                        $errorMessages[] = "Incomplete data: " . implode(',', $row);

                        continue;

                    }



                    $stmt = $conn->prepare("INSERT INTO tbl_admin_budget_security (branch_code, branch, no_of_shifts, rate, month_applicable) VALUES (?, ?, ?, ?, ?)");

                    $stmt->bind_param("ssids", $branch_code, $branch, $no_of_shifts, $rate, $month);



                    if ($stmt->execute()) {

                        $successCount++;

                    } else {

                        $errorMessages[] = "Error inserting row: " . implode(',', $row);

                    }

                }



                fclose($handle);



                echo "<div class='alert alert-success'>Successfully uploaded $successCount rows.</div>";

                if (!empty($errorMessages)) {

                    echo "<div class='alert alert-warning'><strong>Some rows failed:</strong><ul>";

                    foreach ($errorMessages as $msg) {

                        echo "<li>" . htmlspecialchars($msg) . "</li>";

                    }

                    echo "</ul></div>";

                }

            }

            ?>



            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">

                    <label for="csv_file" class="new-font-size mb-2">Select CSV File</label>

                    <input type="file" class="form-control" name="csv_file" id="csv_file" accept=".csv" required>

                </div>

                <button type="submit" class="btn btn-primary">Upload</button>

            </form>



            <div class="mt-3">

                <h6>CSV Format:</h6>

                <pre>Branch Code,Branch,No of Shifts,Rate,Month</pre>

                <small class="text-muted">Example: BR001,Colombo 01,3,2500,May 2025</small>

            </div>

        </div>

    </div>

</div>

<script>

function toggleMenu() {

    document.getElementById("sidebar").classList.toggle("active");

}

</script>

</body>

</html>

