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

            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {

                $row++;



                // Skip empty or header row

                if ($row == 1 || count(array_filter($data)) == 0) continue;



                // Clean BOM and encoding

                $data[0] = preg_replace('/[\x{FEFF}\x{200B}\x{2060}]/u', '', $data[0]);

                $data = array_map(function($value) {

                    return mysqli_real_escape_string($GLOBALS['conn'], trim(mb_convert_encoding($value, 'UTF-8', 'auto')));

                }, $data);



                // Assign values

                $branch_number               = $data[0] ?? '';

                $branch_name                = $data[1] ?? '';

                $lease_agreement_number     = $data[2] ?? '';

                $contract_period            = $data[3] ?? '';

                $start_date                 = $data[4] ?? '';

                $end_date                   = $data[5] ?? '';

                $total_rent                 = $data[6] ?? '';

                $increase_of_rent           = $data[7] ?? '';

                $advance_payment_key_money = $data[8] ?? '';

                $monthly_rental_notes       = $data[9] ?? '';

                $floor_area                 = $data[10] ?? '';

                $repairs_by_cdb            = $data[11] ?? '';

                $deviations_within_contract= $data[12] ?? '';



                $sql = "INSERT INTO tbl_admin_branch_contracts (

                    branch_number, branch_name, lease_agreement_number, contract_period, start_date, end_date,

                    total_rent, increase_of_rent, advance_payment_key_money, monthly_rental_notes,

                    floor_area, repairs_by_cdb, deviations_within_contract

                ) VALUES (

                    '$branch_number', '$branch_name', '$lease_agreement_number', '$contract_period', '$start_date', '$end_date',

                    '$total_rent', '$increase_of_rent', '$advance_payment_key_money', '$monthly_rental_notes',

                    '$floor_area', '$repairs_by_cdb', '$deviations_within_contract'

                )";



                if (!mysqli_query($conn, $sql)) {

                    echo "<div class='alert alert-danger'>Row $row: " . mysqli_error($conn) . "</div>";

                }

            }

            fclose($handle);

            $message = "<div class='alert alert-success'>✅ File uploaded and branch contract data imported successfully.</div>";

        } else {

            $message = "<div class='alert alert-danger'>❌ Error opening the uploaded file.</div>";

        }

    } else {

        $error_message = file_upload_error_message($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE);

        $message = "<div class='alert alert-danger'>❌ File upload error: $error_message</div>";

    }

}

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Upload Branch Contract CSV</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

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

                            <h5 class="mb-4 text-primary">Upload Branch Contract CSV</h5>

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

                                        Upload

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

