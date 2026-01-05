<?php
// vehicle-approval-fetch.php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

// Always return clean JSON (no BOM / no stray output)
function send_json($arr) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($arr, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$type  = $_POST['type'] ?? '';
$map   = [
    'maintenance' => 'tbl_admin_vehicle_maintenance',
    'service'     => 'tbl_admin_vehicle_service',
    'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = isset($map[$type]) ? $map[$type] : '';

if (!$table) {
    send_json([
        'pending'  => '<div class="alert alert-danger">Invalid type</div>',
        'rejected' => '<div class="alert alert-danger">Invalid type</div>'
    ]);
}

// === Helpers ===
function e($val) {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}
function jsstr($val) {
    return json_encode((string)($val ?? ''));
}
function formatAmount($val) {
    return number_format((float)$val, 2);
}

$pending  = '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light">';
$rejected = '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light">';

try {
    $conn->set_charset('utf8mb4');

    if ($type === 'maintenance') {
        $pending .= '<tr><th>SR</th><th>Vehicle</th><th>Type</th><th>Date</th><th>Milage</th><th>Price</th><th>Driver</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Pending' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $date = in_array($row['maintenance_type'] ?? '', ['Battery','Tire']) ? ($row['purchase_date'] ?? '') : ($row['repair_date'] ?? '');
            $sr_js = jsstr($row['sr_number'] ?? '');
            $pending .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['maintenance_type']) . "</td>
                <td>" . e($date) . "</td>
                <td>" . e($row['mileage']) . "</td>
                <td>" . formatAmount($row['price']) . "</td>
                <td>" . e($row['driver_name']) . "</td>
                <td><button class='btn btn-primary btn-sm' onclick=\"viewApproval(" . (int)$row['id'] . ",'maintenance'," . $sr_js . ")\">View & Approve</button></td>
            </tr>";
        }
        $pending .= '</tbody></table></div>';

        $rejected .= '<tr><th>SR</th><th>Vehicle</th><th>Type</th><th>Date</th><th>Milage</th><th>Price</th><th>Driver</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Rejected' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $date = in_array($row['maintenance_type'] ?? '', ['Battery','Tire']) ? ($row['purchase_date'] ?? '') : ($row['repair_date'] ?? '');
            $sr_js  = jsstr($row['sr_number']);
            $rejected .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['maintenance_type']) . "</td>
                <td>" . e($date) . "</td>
                <td>" . e($row['mileage']) . "</td>
                <td>" . formatAmount($row['price']) . "</td>
                <td>" . e($row['driver_name']) . "</td>
                <td>" . e($row['rejected_by']) . "</td>
                <td>" . e($row['rejected_at']) . "</td>
                <td>" . e($row['rejection_reason']) . "</td>
                <td><button class='btn btn-danger btn-sm' onclick=\"deleteApproval(" . (int)$row['id'] . ", 'maintenance', " . $sr_js . ")\">Delete</button></td>
            </tr>";
        }
        $rejected .= '</tbody></table></div>';
    }

    elseif ($type === 'service') {
        $pending .= '<tr><th>SR</th><th>Vehicle</th><th>Date</th><th>Previous Meter Reading</th><th>Next Service Meter Reading</th><th>Amount</th><th>Driver</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Pending' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $sr_js = jsstr($row['sr_number'] ?? '');
            $pending .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['service_date']) . "</td>
                <td>" . e($row['meter_reading']) . "</td>
                <td>" . e($row['next_service_meter']) . "</td>
                <td>" . formatAmount($row['amount']) . "</td>
                <td>" . e($row['driver_name']) . "</td>
                <td><button class='btn btn-primary btn-sm' onclick=\"viewApproval(" . (int)$row['id'] . ",'service'," . $sr_js . ")\">View & Approve</button></td>
            </tr>";
        }
        $pending .= '</tbody></table></div>';

        $rejected .= '<tr><th>SR</th><th>Vehicle</th><th>Date</th><th>Previous Meter Reading</th><th>Next Service Meter Reading</th><th>Amount</th><th>Driver</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Rejected' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $sr_js  = jsstr($row['sr_number']);
            $rejected .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['service_date']) . "</td>
                <td>" . e($row['meter_reading']) . "</td>
                <td>" . e($row['next_service_meter']) . "</td>
                <td>" . formatAmount($row['amount']) . "</td>
                <td>" . e($row['driver_name']) . "</td>
                <td>" . e($row['rejected_by']) . "</td>
                <td>" . e($row['rejected_at']) . "</td>
                <td>" . e($row['rejection_reason']) . "</td>
                <td><button class='btn btn-danger btn-sm' onclick=\"deleteApproval(" . (int)$row['id'] . ", 'service', " . $sr_js . ")\">Delete</button></td>
            </tr>";
        }
        $rejected .= '</tbody></table></div>';
    }

    elseif ($type === 'license') {
        $pending .= '<tr><th>SR</th><th>Vehicle</th><th>License Date</th><th>Amount</th><th>Handled By</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Pending' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $sr_js = jsstr($row['sr_number'] ?? '');
            $pending .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['revenue_license_date']) . "</td>
                <td>" . formatAmount($row['revenue_license_amount']) . "</td>
                <td>" . e($row['person_handled']) . "</td>
                <td><button class='btn btn-primary btn-sm' onclick=\"viewApproval(" . (int)$row['id'] . ",'license'," . $sr_js . ")\">View & Approve</button></td>
            </tr>";
        }
        $pending .= '</tbody></table></div>';

        $rejected .= '<tr><th>SR</th><th>Vehicle</th><th>License Date</th><th>Amount</th><th>Handled By</th><th>Rejected By</th><th>Rejected At</th><th>Reason</th><th>Action</th></tr></thead><tbody>';
        $q = $conn->prepare("SELECT * FROM $table WHERE status='Rejected' ORDER BY id DESC");
        $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $sr_js  = jsstr($row['sr_number']);
            $rejected .= "<tr>
                <td>" . e($row['sr_number']) . "</td>
                <td>" . e($row['vehicle_number']) . "</td>
                <td>" . e($row['revenue_license_date']) . "</td>
                <td>" . formatAmount($row['revenue_license_amount']) . "</td>
                <td>" . e($row['person_handled']) . "</td>
                <td>" . e($row['rejected_by']) . "</td>
                <td>" . e($row['rejected_at']) . "</td>
                <td>" . e($row['rejection_reason']) . "</td>
                <td><button class='btn btn-danger btn-sm' onclick=\"deleteApproval(" . (int)$row['id'] . ", 'license', " . $sr_js . ")\">Delete</button></td>
            </tr>";
        }
        $rejected .= '</tbody></table></div>';
    }

    send_json(['pending' => $pending, 'rejected' => $rejected]);

} catch (Throwable $e) {
    $msg = e($e->getMessage());
    send_json([
        'pending'  => '<div class="alert alert-danger">Error: '.$msg.'</div>',
        'rejected' => '<div class="alert alert-danger">Error: '.$msg.'</div>'
    ]);
}
