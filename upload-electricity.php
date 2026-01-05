<?php
include 'connections/connection.php';

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Open the CSV file
$file = fopen("electricity.csv", "r");
if ($file !== FALSE) {
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        // Fetch and sanitize fields
        $branch_id = intval($data[0]); 
        $branch_name = $conn->real_escape_string($data[1]); 
        $payment_month = $conn->real_escape_string($data[2]); 
        $units = isset($data[3]) ? intval($data[3]) : 0;  
        $amount = isset($data[4]) ? number_format((float)$data[4], 2, '.', '') : '0.00'; 

        // SQL query to insert into tbl_admin_electricity_cost
        $sql = "INSERT INTO tbl_admin_electricity_cost (branch_id, branch_name, payment_month, units, amount) 
                VALUES ('$branch_id', '$branch_name', '$payment_month', '$units', '$amount')";

        // Execute the query
        if (!$conn->query($sql)) {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
    fclose($file);
}

echo "Electricity cost data imported successfully!";
$conn->close();
?>
