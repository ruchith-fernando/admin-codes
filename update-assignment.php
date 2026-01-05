<!-- update-assignment.php -->
<?php
include 'connections/connection.php';
session_start();

// Replace with actual logged-in user identifier
$changed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_ref = $conn->real_escape_string($_POST['file_ref']);
    $new_assigned_user = $conn->real_escape_string($_POST['assigned_user']);
    $new_hris = $conn->real_escape_string($_POST['hris']);
    $new_nic = $conn->real_escape_string($_POST['nic']);
    $new_tp_no = $conn->real_escape_string($_POST['tp_no']);
    $new_division = $conn->real_escape_string($_POST['division']);
    $reason = $conn->real_escape_string($_POST['reason']);
    $change_method = $conn->real_escape_string($_POST['change_method']);

    // Get old data
    $sql_old = "SELECT assigned_user, hris, nic, tp_no, division FROM tbl_admin_fixed_assets WHERE file_ref = '$file_ref'";
    $result_old = $conn->query($sql_old);

    if ($result_old && $result_old->num_rows === 1) {
        $old = $result_old->fetch_assoc();

        // Log the change
        $log_sql = "INSERT INTO tbl_vehicle_assignment_log (
                        file_ref, 
                        old_assigned_user, old_hris, old_nic, old_tp_no, old_division,
                        new_assigned_user, new_hris, new_nic, new_tp_no, new_division,
                        reason, change_method, changed_by
                    ) VALUES (
                        '$file_ref',
                        '{$old['assigned_user']}', '{$old['hris']}', '{$old['nic']}', '{$old['tp_no']}', '{$old['division']}',
                        '$new_assigned_user', '$new_hris', '$new_nic', '$new_tp_no', '$new_division',
                        '$reason', '$change_method', '$changed_by'
                    )";

        // Update main table
        $update_sql = "UPDATE tbl_admin_fixed_assets SET 
                        assigned_user = '$new_assigned_user',
                        hris = '$new_hris',
                        nic = '$new_nic',
                        tp_no = '$new_tp_no',
                        division = '$new_division'
                       WHERE file_ref = '$file_ref'";

        if ($conn->query($update_sql) && $conn->query($log_sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'update_or_log_failed']);
        }
    } else {
        echo json_encode(['status' => 'no_record_found']);
    }
} else {
    echo json_encode(['status' => 'invalid_method']);
}
?>
