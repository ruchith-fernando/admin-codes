<?php
include 'connections/connection.php';

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
    die("No file uploaded.");
}

$file = $_FILES['csv_file']['tmp_name'];

if (($handle = fopen($file, "r")) === false) {
    die("Error opening file.");
}

$row = 0;
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
    $row++;
    if ($row == 1) continue; // skip header

    $dialog_number    = $conn->real_escape_string($data[0] ?? '');
    $user_name        = $conn->real_escape_string($data[1] ?? '');
    $nic_no           = $conn->real_escape_string($data[2] ?? '');
    $hris_no          = $conn->real_escape_string($data[3] ?? '');
    $month_applicable = $conn->real_escape_string($data[4] ?? '');

    $sql = "INSERT INTO tbl_admin_temp_data
            (dialog_number, user_name, nic_no, hris_no, month_applicable)
            VALUES
            ('$dialog_number', '$user_name', '$nic_no', '$hris_no', '$month_applicable')";

    if (!$conn->query($sql)) {
        echo "Row $row failed: " . $conn->error . "<br>";
    } else {
        echo "Row $row inserted.<br>";
    }
}
fclose($handle);

echo "<b>CSV upload finished.</b>";
