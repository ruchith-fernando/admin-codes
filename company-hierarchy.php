<?php
include 'connections/connection.php'; // assumes $conn is the variable

if (isset($_POST['submit'])) {
    // Check if file was uploaded
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');

        // Skip the header row
        fgetcsv($file);

        while (($row = fgetcsv($file)) !== FALSE) {
            $company_hierarchy = mysqli_real_escape_string($conn, $row[0]);
            $department_route = mysqli_real_escape_string($conn, $row[1]);

            // Insert into the database
            $sql = "INSERT INTO tbl_admin_company_hierarchy (company_hierarchy, department_route) 
                    VALUES ('$company_hierarchy', '$department_route')";

            if (!mysqli_query($conn, $sql)) {
                echo "Error inserting row: " . mysqli_error($conn) . "<br>";
            }
        }

        fclose($file);
        echo "CSV data imported successfully.";
    } else {
        echo "No file uploaded.";
    }
}
?>

<!-- HTML form to upload the CSV -->
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <input type="submit" name="submit" value="Upload CSV">
</form>
