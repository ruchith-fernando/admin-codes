<?php
include("connections/connection.php"); // adjust path if needed

if (isset($_POST['submit'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($file); // Skip header row

        $inserted = 0;
        $failed = 0;

        while (($data = fgetcsv($file)) !== FALSE) {
            // Read values even if they are empty
            $serial_no   = isset($data[0]) ? trim($data[0]) : null;
            $branch_code = isset($data[1]) ? trim($data[1]) : null;
            $branch_name = isset($data[2]) ? trim($data[2]) : null;
            $rate        = isset($data[3]) ? trim($data[3]) : null;

            // Insert all rows, even with null/empty fields
            $stmt = $conn->prepare("INSERT INTO tbl_admin_branch_photocopy (serial_number, branch_code, branch_name, rate) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $serial_no, $branch_code, $branch_name, $rate);
            if ($stmt->execute()) {
                $inserted++;
            } else {
                $failed++;
            }
        }
        fclose($file);
        echo "<div class='alert alert-success'>Import complete: $inserted inserted, $failed failed.</div>";
    } else {
        echo "<div class='alert alert-danger'>File upload error.</div>";
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <label>Select CSV File:</label>
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" name="submit">Upload</button>
</form>
