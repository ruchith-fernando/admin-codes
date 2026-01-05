<?php
include 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

function logTransfer($message) {
    file_put_contents('logs/vehicle-actions.log', "[" . date("Y-m-d H:i:s") . "] TRANSFER: $message\n", FILE_APPEND);
}

$file_ref     = $conn->real_escape_string($_POST['file_ref']);
$new_user     = $conn->real_escape_string($_POST['new_assigned_user']);
$new_hris     = $conn->real_escape_string($_POST['new_hris']);
$new_nic      = $conn->real_escape_string($_POST['new_nic']);
$new_tp       = $conn->real_escape_string($_POST['new_tp_no']);
$new_div      = $conn->real_escape_string($_POST['new_division']);
$reason       = $conn->real_escape_string($_POST['reason']);
$method       = $conn->real_escape_string($_POST['change_method']);
$changed_by   = $_SESSION['username'] ?? 'System';

logTransfer("Initiating transfer for file_ref=$file_ref by $changed_by");

$get_old = "SELECT assigned_user, hris, nic, tp_no, division 
            FROM tbl_admin_fixed_assets 
            WHERE file_ref = '$file_ref'";
$old_result = $conn->query($get_old);

if ($old_result && $old_result->num_rows > 0) {
    $old = $old_result->fetch_assoc();

    $log_sql = "INSERT INTO tbl_vehicle_assignment_log (
                    file_ref, old_assigned_user, old_hris, old_nic, old_tp_no, old_division,
                    new_assigned_user, new_hris, new_nic, new_tp_no, new_division,
                    reason, change_method, changed_by
                ) VALUES (
                    '$file_ref', '{$old['assigned_user']}', '{$old['hris']}', '{$old['nic']}', '{$old['tp_no']}', '{$old['division']}',
                    '$new_user', '$new_hris', '$new_nic', '$new_tp', '$new_div',
                    '$reason', '$method', '$changed_by'
                )";

    $update_sql = "UPDATE tbl_admin_fixed_assets SET 
                    assigned_user = '$new_user',
                    hris = '$new_hris',
                    nic = '$new_nic',
                    tp_no = '$new_tp',
                    division = '$new_div'
                   WHERE file_ref = '$file_ref'";

    if ($conn->query($log_sql) && $conn->query($update_sql)) {
        logTransfer("SUCCESS for file_ref=$file_ref");
        echo json_encode(['status' => 'success']);
    } else {
        $err = $conn->error;
        logTransfer("ERROR saving data: $err");
        echo json_encode(['status' => 'error', 'message' => $err]);
    }
} else {
    logTransfer("ERROR: Asset not found for $file_ref");
    echo json_encode(['status' => 'error', 'message' => 'Asset not found.']);
}
?>
