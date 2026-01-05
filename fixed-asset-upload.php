<?php
include 'connections/connection.php';

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Open the CSV file
$file = fopen("fixed_assets.csv", "r");
if ($file !== FALSE) {
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        // Fetch and sanitize fields
        $file_ref = $conn->real_escape_string($data[0]);
        $registration_date = date('Y-m-d', strtotime($data[1])); // Convert to MySQL DATE format
        $veh_no = $conn->real_escape_string($data[2]);
        $vehicle_type = $conn->real_escape_string($data[3]);
        $make = $conn->real_escape_string($data[4]);
        $model = $conn->real_escape_string($data[5]);
        $yom = intval($data[6]); // Year
        $cr_available = $conn->real_escape_string($data[7]);
        $book_owner = $conn->real_escape_string($data[8]);
        $division = $conn->real_escape_string($data[9]);
        $asset_condition = $conn->real_escape_string($data[10]);
        $assigned_user = $conn->real_escape_string($data[11]);
        $hris = $conn->real_escape_string($data[12]);
        $nic = $conn->real_escape_string($data[13]);
        $tp_no = $conn->real_escape_string($data[14]);
        $agreement = $conn->real_escape_string($data[15]);
        $new_comments = $conn->real_escape_string($data[16]);

        // SQL query to insert into tbl_admin_fixed_assets
        $sql = "INSERT INTO tbl_admin_fixed_assets 
                (file_ref, registration_date, veh_no, vehicle_type, make, model, yom, cr_available, book_owner, division, 
                 asset_condition, assigned_user, hris, nic, tp_no, agreement, new_comments) 
                VALUES 
                ('$file_ref', '$registration_date', '$veh_no', '$vehicle_type', '$make', '$model', '$yom', '$cr_available', 
                 '$book_owner', '$division', '$asset_condition', '$assigned_user', '$hris', '$nic', '$tp_no', '$agreement', '$new_comments')";

        // Execute the query
        if (!$conn->query($sql)) {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
    fclose($file);
}

echo "Fixed asset data imported successfully!";
$conn->close();
?>
