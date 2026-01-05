<?php
// upload_temp_data.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<pre>DEBUG: Script started...</pre>";
include 'connections/connection.php';

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        $row = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $row++;
            if ($row == 1) continue; // skip header

            $dialog_number   = $conn->real_escape_string($data[0]);
            $user_name       = $conn->real_escape_string($data[1]);
            $nic_no          = $conn->real_escape_string($data[2]);
            $hris_no         = $conn->real_escape_string($data[3]);
            $month_applicable= $conn->real_escape_string($data[4]);

            $sql = "
                INSERT INTO tbl_admin_temp_data
                (dialog_number, user_name, nic_no, hris_no, month_applicable)
                VALUES
                ('$dialog_number', '$user_name', '$nic_no', '$hris_no', '$month_applicable')
            ";

            echo "<pre>Row $row SQL:\n$sql</pre>";

if (!$conn->query($sql)) {
    echo "<div style='color:red'>Row $row failed: " . $conn->error . "</div>";
} else {
    echo "<div style='color:green'>Row $row inserted.</div>";
}

        }
        fclose($handle);
        echo "<div class='alert alert-success'>CSV import finished.</div>";
    }
}
?>
