<?php
include 'connections/connection.php';

// Connect to DB
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// CSV Upload and Insert
if (isset($_POST['upload'])) {
    if ($_FILES['csv_file']['error'] === 0) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        fgetcsv($file); // Skip the header row

        while (($row = fgetcsv($file)) !== FALSE) {
            // Assuming CSV columns: issue_date, emie_number, mobile_number, name, location_and_designation, hris
            $issue_date = $conn->real_escape_string($row[0]);
            $emie_number = $conn->real_escape_string($row[1]);
            $mobile_number = $conn->real_escape_string($row[2]);
            $name = $conn->real_escape_string($row[3]);
            $location_and_designation = $conn->real_escape_string($row[4]);
            $hris = $conn->real_escape_string($row[5]);

            $sql = "INSERT INTO tbl_admin_phone_issues (issue_date, emie_number, mobile_number, name, location_and_designation, hris)
                    VALUES ('$issue_date', '$emie_number', '$mobile_number', '$name', '$location_and_designation', '$hris')";
            $conn->query($sql);
        }

        fclose($file);
        echo "Upload and insert successful!";
    } else {
        echo "Error uploading file.";
    }
}
?>

<!-- HTML Form -->
<form method="post" enctype="multipart/form-data">
    <label>Select CSV File:</label>
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" name="upload">Upload</button>
</form>
