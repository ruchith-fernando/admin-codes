<?php
include 'connections/connection.php'; // adjust path if needed

$prefix = date('ym'); // current year and month (e.g., 2507)
$query = "SELECT MAX(sr_number) AS max_sr FROM tbl_admin_sr_number WHERE sr_number LIKE '$prefix%'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$last_sr = isset($row['max_sr']) ? intval(substr($row['max_sr'], 4)) : 0;

// Get all records without an SR number
$sql = "SELECT id FROM tbl_admin_mobile_bill_data WHERE sr_number IS NULL ORDER BY id ASC";
$res = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($res)) {
    $record_id = $row['id'];
    $last_sr++;
    $new_sr = $prefix . str_pad($last_sr, 6, '0', STR_PAD_LEFT);

    // Update the record
    $update = "UPDATE tbl_admin_mobile_bill_data SET sr_number = '$new_sr' WHERE id = $record_id";
    $insert = "INSERT INTO tbl_admin_sr_number (sr_number, table_name, record_id)
               VALUES ('$new_sr', 'tbl_admin_mobile_bill_data', $record_id)";

    if (mysqli_query($conn, $update) && mysqli_query($conn, $insert)) {
        echo "SR $new_sr assigned to ID $record_id<br>";
    } else {
        echo "Error on ID $record_id: " . mysqli_error($conn) . "<br>";
    }
}
?>
