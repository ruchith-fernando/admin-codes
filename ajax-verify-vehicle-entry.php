<?php
// ajax-verify-vehicle-entry.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connections/connection.php';

$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? 0;

file_put_contents('debug-log.txt', "TYPE: $type\nID: $id\nPOST:\n" . print_r($_POST, true), FILE_APPEND);

if (!$type || !$id) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if ($type === 'maintenance') {
    $query = "SELECT m.*, v.vehicle_number FROM tbl_admin_vehicle_maintenance m
              JOIN tbl_admin_vehicle v ON m.vehicle_id = v.id
              WHERE m.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo '<div class="alert alert-warning">Maintenance record not found.</div>';
        exit;
    }

    $row = $result->fetch_assoc();
    $maintenance_type = $row['maintenance_type'];

    echo '<form id="verifyMaintenanceForm">';
    echo "<input type='hidden' name='id' value='{$row['id']}'>";
    echo "<input type='hidden' name='type' value='maintenance'>";

    if ($maintenance_type === 'Tire') {
        include 'fragments-form-tire.php';
    } elseif ($maintenance_type === 'Battery') {
        include 'fragments-form-battery.php';
    } elseif ($maintenance_type === 'AC') {
        include 'fragments-form-ac.php';
    } elseif ($maintenance_type === 'Other') {
        include 'fragments-form-other.php';
    } else {
        echo "<div class='alert alert-warning'>Unsupported maintenance type: $maintenance_type</div>";
    }
    echo '</form>';

} elseif ($type === 'service') {
    $query = "SELECT s.*, v.vehicle_number FROM tbl_admin_vehicle_service s
              JOIN tbl_admin_vehicle v ON s.vehicle_id = v.id
              WHERE s.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo '<div class="alert alert-warning">Service record not found.</div>';
        exit;
    }

    $row = $result->fetch_assoc();
    include 'fragments-form-service.php';

} elseif ($type === 'license') {
    $query = "SELECT l.*, v.vehicle_number FROM tbl_admin_vehicle_licensing_insurance l
              JOIN tbl_admin_vehicle v ON l.vehicle_number = v.vehicle_number
              WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo '<div class="alert alert-warning">License/Insurance record not found.</div>';
        exit;
    }

    $row = $result->fetch_assoc();
    include 'fragments-form-license.php';

} else {
    echo '<div class="alert alert-danger">Unsupported type.</div>';
    exit;
}
?>
