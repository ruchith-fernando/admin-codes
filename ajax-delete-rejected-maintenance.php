<?php
session_start();
include("connections/connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $deleted_by = $_SESSION['hris'];

    $sql = "UPDATE tbl_admin_vehicle_maintenance 
        SET status = 'Deleted',
            deleted_by = ?,
            deleted_at = NOW()
        WHERE id = ? AND entered_by = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $deleted_by, $id, $deleted_by);


    if ($stmt->execute()) {
        echo "Record successfully deleted.";
    } else {
        echo "Error deleting record.";
    }
} else {
    echo "Invalid request.";
}
?>
