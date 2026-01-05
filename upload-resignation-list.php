<!-- upload-resignation-list.php -->
<?php
session_start();
include 'connections/connection.php';

$message = '';

// Helper: show PHP file upload error codes
function file_upload_error_message($error_code) {
    $errors = array(
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
    );
    return $errors[$error_code] ?? 'Unknown upload error.';
}

// Handle file upload
if (isset($_POST['submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;

                // Skip empty rows
                if (count(array_filter($data)) == 0) continue;

                // Clean BOM
                if ($row == 1) {
                    $data[0] = preg_replace('/[\x{FEFF}\x{200B}\x{2060}]/u', '', $data[0]);
                    $data[0] = trim(mb_convert_encoding($data[0], 'UTF-8', 'UTF-8'));
                    continue; // skip header
                }

                // Assign and sanitize values
                $HRIS       = mysqli_real_escape_string($conn, trim($data[0]));
                $Title      = mysqli_real_escape_string($conn, trim($data[1]));
                $Name       = mysqli_real_escape_string($conn, trim($data[2]));
                $Designation= mysqli_real_escape_string($conn, trim($data[3]));
                $Department = mysqli_real_escape_string($conn, trim($data[4]));
                $Branch     = mysqli_real_escape_string($conn, trim($data[5]));
                $DOJ        = mysqli_real_escape_string($conn, trim($data[6]));
                $NIC        = mysqli_real_escape_string($conn, trim($data[7]));
                $Category   = mysqli_real_escape_string($conn, trim($data[8]));
                $Employment = mysqli_real_escape_string($conn, trim($data[9]));
                $Effective  = mysqli_real_escape_string($conn, trim($data[10]));
                $Type       = mysqli_real_escape_string($conn, trim($data[11]));
                $Reason     = mysqli_real_escape_string($conn, trim($data[12]));
                $email_received = mysqli_real_escape_string($conn, trim($data[13]));

                $sql = "INSERT INTO tbl_admin_employee_resignations (
                    HRIS, Title, Name, Designation, Department, Branch, DOJ, NIC,
                    Category, Employment_Type, Resignation_Effective_Date, Resignation_Type, Reason, email_received_date
                ) VALUES (
                    '$HRIS', '$Title', '$Name', '$Designation', '$Department', '$Branch', '$DOJ', '$NIC',
                    '$Category', '$Employment', '$Effective', '$Type', '$Reason', '$email_received'
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div class='alert alert-danger'>Row $row: " . mysqli_error($conn) . "</div>";
                }
            }
            fclose($handle);
            $message = "<div class='alert alert-success'>File uploaded and resignation data imported successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error opening the uploaded file.</div>";
        }
    } else {
        $error_message = file_upload_error_message($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        $message = "<div class='alert alert-danger'>File upload error: $error_message</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Resignation List</title>
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
                            <h5 class="mb-0">Upload Resignation CSV - HR List</h5>
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
