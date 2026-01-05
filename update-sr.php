<?php
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

$updated = 0;
$result = $conn->query("SELECT id FROM tbl_admin_tea_service WHERE sr_number IS NULL OR sr_number = ''");

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];

    $sr_number = generate_sr_number($conn, 'tbl_admin_tea_service', $id);

    $update = $conn->prepare("UPDATE tbl_admin_tea_service SET sr_number = ? WHERE id = ?");
    $update->bind_param("si", $sr_number, $id);
    $update->execute();
    $update->close();

    $updated++;
}

echo "SR Numbers updated for $updated records.";
?>

<!-- ALTER TABLE tbl_admin_tea_service ADD COLUMN sr_number VARCHAR(255) DEFAULT NULL; -->
