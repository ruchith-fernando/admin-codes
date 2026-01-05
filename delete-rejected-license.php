<?php
require_once 'connections/connection.php';

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $sql = "UPDATE tbl_admin_vehicle_licensing_insurance
            SET status = 'Deleted'
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'invalid';
}
?>
