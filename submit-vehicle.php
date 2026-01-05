<?php
ob_start();
session_start();

require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

header('Content-Type: application/json');

$logFile = __DIR__ . '/submit-vehicle.log';
function debugLog($message) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
}

debugLog("====== NEW REQUEST ======");

$response = ['status' => 'error', 'message' => 'Unknown error'];

$userRole = $_SESSION['user_role'] ?? 'standard_user';
$hris = $_SESSION['hris'] ?? 'unknown_user';
debugLog("User Role: $userRole | HRIS: $hris");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog("POST data: " . json_encode($_POST));

    $vehicle_type         = trim($_POST['vehicle_type'] ?? '');
    $vehicle_number       = trim($_POST['vehicle_number'] ?? '');
    $chassis_number       = trim($_POST['chassis_number'] ?? '');
    $make_model           = trim($_POST['make_model'] ?? '');
    $engine_capacity      = trim($_POST['engine_capacity'] ?? '');
    $year_of_manufacture  = trim($_POST['year_of_manufacture'] ?? '');
    $purchase_date        = trim($_POST['purchase_date'] ?? '');
    $purchase_value       = str_replace(',', '', trim($_POST['purchase_value'] ?? ''));
    $vehicle_category     = trim($_POST['vehicle_category'] ?? '');
    $fuel_type            = trim($_POST['fuel_type'] ?? '');
    $original_mileage     = str_replace(',', '', trim($_POST['original_mileage'] ?? ''));
    $assigned_user_hris   = trim($_POST['assigned_user_hris'] ?? '');

    $assigned_user = '';
    if ($assigned_user_hris) {
        $stmtUser = $conn->prepare("SELECT display_name FROM tbl_admin_employee_details WHERE hris = ? LIMIT 1");
        $stmtUser->bind_param("s", $assigned_user_hris);
        $stmtUser->execute();
        $result = $stmtUser->get_result();
        if ($row = $result->fetch_assoc()) {
            $assigned_user = $row['display_name'];
        }
        $stmtUser->close();
    }

    if (
        $vehicle_type !== '' &&
        $vehicle_number !== '' &&
        $chassis_number !== '' &&
        $make_model !== '' &&
        $engine_capacity !== '' &&
        $year_of_manufacture !== '' &&
        $purchase_date !== '' &&
        $purchase_value !== '' &&
        $vehicle_category !== '' &&
        $fuel_type !== '' &&
        $original_mileage !== ''
    ) {
        $status      = ($userRole === 'super_admin') ? 'Approved' : 'Pending';
        $approved_by = ($userRole === 'super_admin') ? $hris : null;
        $approved_at = ($userRole === 'super_admin') ? date('Y-m-d H:i:s') : null;


        // Check for duplicates again server-side
        $dupStmt = $conn->prepare("SELECT id FROM tbl_admin_vehicle WHERE vehicle_number = ? LIMIT 1");
        $dupStmt->bind_param("s", $vehicle_number);
        $dupStmt->execute();
        $dupStmt->store_result();

        if ($dupStmt->num_rows > 0) {
            $response['message'] = "Vehicle number already exists.";
            debugLog("Duplicate entry for vehicle number: $vehicle_number");
            echo json_encode($response);
            exit;
        }
        $dupStmt->close();


        $stmt = $conn->prepare("INSERT INTO tbl_admin_vehicle 
            (fuel_type, vehicle_type, vehicle_number, chassis_number, make_model, engine_capacity, year_of_manufacture, purchase_date, purchase_value, assigned_user, assigned_user_hris, vehicle_category, original_mileage, status, created_by, approved_by, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $error = $conn->error;
            debugLog("MySQL Prepare failed: $error");
            $response['message'] = 'Server error (prepare failed).';
        } else {
            $stmt->bind_param(
                "sssssssssssssssss",
                $fuel_type,
                $vehicle_type,
                $vehicle_number,
                $chassis_number,
                $make_model,
                $engine_capacity,
                $year_of_manufacture,
                $purchase_date,
                $purchase_value,
                $assigned_user,
                $assigned_user_hris,
                $vehicle_category,
                $original_mileage,
                $status,
                $hris,
                $approved_by,
                $approved_at
            );

            if ($stmt->execute()) {
                $inserted_id = $stmt->insert_id;
                $sr_number = generate_sr_number($conn, 'tbl_admin_vehicle', $inserted_id);

                $response = [
                    'status' => 'success',
                    'message' => ($userRole === 'super_admin')
                        ? 'Vehicle added and approved.'
                        : 'Vehicle submitted. Awaiting approval from super admin.',
                    'sr_number' => $sr_number
                ];

                debugLog("Insert success. ID: $inserted_id, SR: $sr_number");
            } else {
                $errorMsg = 'Database error: ' . $stmt->error;
                debugLog($errorMsg);
                $response['message'] = $errorMsg;
            }

            $stmt->close();
        }
    } else {
        $response['message'] = 'Please fill all required fields.';
        debugLog("Validation failed - missing required fields.");
    }
} else {
    debugLog("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $response['message'] = 'Invalid request method.';
}

debugLog("Response: " . json_encode($response));

ob_end_clean();
echo json_encode($response);
exit;
