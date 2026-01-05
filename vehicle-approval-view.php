<?php
// vehicle-approval-view.php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

function send_json($arr) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($arr, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function e($val){ return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmt2($n){ return number_format((float)$n, 2); }

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$type = $_POST['type'] ?? '';

$map = [
    'maintenance' => 'tbl_admin_vehicle_maintenance',
    'service'     => 'tbl_admin_vehicle_service',
    'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = isset($map[$type]) ? $map[$type] : '';

if (!$id || !$table) {
    send_json(['html' => '<div class="alert alert-danger">Invalid request</div>']);
}

try {
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        send_json(['html' => '<div class="alert alert-danger">Record not found</div>']);
    }

    if ($type === 'maintenance') {
        $date = in_array($result['maintenance_type'], ['Battery', 'Tire']) ? $result['purchase_date'] : $result['repair_date'];
        $details = "
        <table class='table table-bordered'>
            <tr><th class='w-25'>SR Number</th><td>".e($result['sr_number'])."</td></tr>
            <tr><th>Vehicle Number</th><td>".e($result['vehicle_number'])."</td></tr>
            <tr><th>Maintenance Type</th><td>".e($result['maintenance_type'])."</td></tr>
            <tr><th>Mileage</th><td>".e($result['mileage'])."</td></tr>
            <tr><th>Date</th><td>".e($date)."</td></tr>
            <tr><th>Price</th><td>".fmt2($result['price'])."</td></tr>
            <tr><th>Driver</th><td>".e($result['driver_name'])."</td></tr>
        </table>";
    } elseif ($type === 'service') {
        $details = "
        <table class='table table-bordered'>
            <tr><th class='w-25'>SR Number</th><td>".e($result['sr_number'])."</td></tr>
            <tr><th>Vehicle Number</th><td>".e($result['vehicle_number'])."</td></tr>
            <tr><th>Service Date</th><td>".e($result['service_date'])."</td></tr>
            <tr><th>Previous Meter Reading</th><td>".e($result['meter_reading'])."</td></tr>
            <tr><th>Next Service Meter Reading</th><td>".e($result['next_service_meter'])."</td></tr>
            <tr><th>Amount</th><td>".fmt2($result['amount'])."</td></tr>
            <tr><th>Driver</th><td>".e($result['driver_name'])."</td></tr>
        </table>";
    } else { // license
        $details = "
        <table class='table table-bordered'>
            <tr><th class='w-25'>SR Number</th><td>".e($result['sr_number'])."</td></tr>
            <tr><th>Vehicle Number</th><td>".e($result['vehicle_number'])."</td></tr>
            <tr><th>Revenue License Date</th><td>".e($result['revenue_license_date'])."</td></tr>
            <tr><th>Amount</th><td>".fmt2($result['revenue_license_amount'])."</td></tr>
            <tr><th>Person Handled</th><td>".e($result['person_handled'])."</td></tr>
        </table>";
    }

    send_json(['html' => $details]);

} catch (Throwable $e) {
    $msg = e($e->getMessage());
    send_json(['html' => '<div class="alert alert-danger">Server error: '.$msg.'</div>']);
}
