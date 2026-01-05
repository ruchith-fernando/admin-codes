<?php
// ajax-get-maintenance-form.php
file_put_contents("debug-log.txt", json_encode($_POST));
require_once 'connections/connection.php';

if (!isset($_POST['id'])) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$id = intval($_POST['id']);

$query = "SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning">Record not found.</div>';
    exit;
}

$row = $result->fetch_assoc();
$type = strtolower($row['maintenance_type']);

switch ($type) {
    case 'battery':
        include 'fragments-form-battery.php';
        break;
    case 'tire':
        include 'fragments-form-tire.php';
        break;
    case 'ac':
        include 'fragments-form-ac.php';
        break;
    case 'other':
        include 'fragments-form-other.php';
        break;
    default:
        echo '<div class="alert alert-danger">Unknown maintenance type.</div>';
        break;
}?>
