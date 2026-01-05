<?php
// get-vehicle-fuel-type.php
require_once 'connections/connection.php';

$vehicle_id = $_GET['vehicle_id'] ?? 0;
$fuel_type = '';

if ($vehicle_id > 0) {
    $stmt = $conn->prepare("SELECT fuel_type FROM tbl_admin_vehicle WHERE id = ?");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $stmt->bind_result($fuel_type);
    $stmt->fetch();
    $stmt->close();
}

echo strtolower(trim($fuel_type)); // return "fuel", "hybrid", or "electric"
?>
