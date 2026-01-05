<?php
include 'connections/connection.php';

$message = "";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['submit'])) {
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
        $file = $_FILES['csvFile']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Extract fields
                $mobile_no = $conn->real_escape_string($data[0]);
                $remarks = $conn->real_escape_string($data[1]);
                $voice_data = $conn->real_escape_string($data[2]);
                $branch_operational_remarks = $conn->real_escape_string($data[3]);
                $user_name = $conn->real_escape_string($data[4]);

                // HRIS handling
                $raw_hris = $conn->real_escape_string($data[5]);
                if (is_numeric($raw_hris)) {
                    $hris_no = str_pad($raw_hris, 6, "0", STR_PAD_LEFT);
                } else {
                    $hris_no = $raw_hris;
                }

                // Company Contribution
                $company_contribution = floatval($data[6]);

                // Insert into database
                $sql = "INSERT INTO tbl_admin_mobile_connections 
                        (mobile_no, remarks, voice_data, branch_operational_remarks, name_of_employee, hris_no, company_contribution) 
                        VALUES 
                        ('$mobile_no', '$remarks', '$voice_data', '$branch_operational_remarks', '$user_name', '$hris_no', $company_contribution)";
                $conn->query($sql);
            }
            fclose($handle);

            $message = '<div class="alert alert-success" role="alert">CSV uploaded and data inserted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger" role="alert">Error opening the file.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger" role="alert">Error uploading the file.</div>';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Mobile Connections CSV</title>
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
        <div class="card shadow bg-white rounded p-4">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Upload Mobile Connections CSV</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($message)) echo $message; ?>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csvFile" class="form-label">Select CSV File</label>
                                    <input type="file" name="csvFile" id="csvFile" class="form-control" accept=".csv" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="submit" class="btn btn-primary">
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

        document.body.classList.toggle("no-scroll", sidebar.classList.contains("hidden"));
    }
</script>

</body>
</html>
