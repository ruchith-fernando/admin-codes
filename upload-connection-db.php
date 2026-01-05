<?php
session_start();
include 'connections/connection.php';

$message = '';

// Helper for file upload error
function file_upload_error_message($error_code) {
    $errors = array(
        UPLOAD_ERR_INI_SIZE   => 'The file exceeds the upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by PHP extension.'
    );
    return $errors[$error_code] ?? 'Unknown error.';
}

// Handle upload
if (isset($_POST['submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $filename = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($filename, "r")) !== FALSE) {
            $row = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;

                // Skip empty rows
                if (count(array_filter($data)) == 0) continue;

                // Skip header row
                if ($row == 1) continue;

                // Assign values
                $mobile_no = mysqli_real_escape_string($conn, trim($data[0]));
                $remarks = mysqli_real_escape_string($conn, trim($data[1]));
                $voice_data = mysqli_real_escape_string($conn, trim($data[2]));
                $branch_operational_remarks = mysqli_real_escape_string($conn, trim($data[3]));
                $name_of_employee = mysqli_real_escape_string($conn, trim($data[4]));
                $hris_no = mysqli_real_escape_string($conn, trim($data[5]));
                $company_contribution = mysqli_real_escape_string($conn, trim($data[6]));
                $epf_no = mysqli_real_escape_string($conn, trim($data[7]));
                $company_hierarchy = mysqli_real_escape_string($conn, trim($data[8]));
                $title = mysqli_real_escape_string($conn, trim($data[9]));
                $designation = mysqli_real_escape_string($conn, trim($data[10]));
                $display_name = mysqli_real_escape_string($conn, trim($data[11]));
                $location = mysqli_real_escape_string($conn, trim($data[12]));
                $nic_no = mysqli_real_escape_string($conn, trim($data[13]));
                $category = mysqli_real_escape_string($conn, trim($data[14]));
                $employment_categories = mysqli_real_escape_string($conn, trim($data[15]));
                $date_joined = mysqli_real_escape_string($conn, trim($data[16]));
                $date_resigned = mysqli_real_escape_string($conn, trim($data[17]));
                $category_ops_sales = mysqli_real_escape_string($conn, trim($data[18]));
                $status = mysqli_real_escape_string($conn, trim($data[19]));

                $sql = "INSERT INTO tbl_admin_mobile_issues (
                    mobile_no, remarks, voice_data, branch_operational_remarks,
                    name_of_employee, hris_no, company_contribution, epf_no,
                    company_hierarchy, title, designation, display_name,
                    location, nic_no, category, employment_categories,
                    date_joined, date_resigned, category_ops_sales, status
                ) VALUES (
                    '$mobile_no', '$remarks', '$voice_data', '$branch_operational_remarks',
                    '$name_of_employee', '$hris_no', '$company_contribution', '$epf_no',
                    '$company_hierarchy', '$title', '$designation', '$display_name',
                    '$location', '$nic_no', '$category', '$employment_categories',
                    '$date_joined', '$date_resigned', '$category_ops_sales', '$status'
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div class='alert alert-danger'>Row $row: " . mysqli_error($conn) . "</div>";
                }
            }
            fclose($handle);
            $message = "<div class='alert alert-success'>CSV uploaded and data saved successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Unable to open uploaded file.</div>";
        }
    } else {
        $error = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $message = "<div class='alert alert-danger'>File upload failed: " . file_upload_error_message($error) . "</div>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Mobile Bill CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
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
                            <h5 class="mb-0">Upload Dialog Bill CSV</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($message)) echo $message; ?>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Select CSV File</label>
                                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
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
</body>
</html>
