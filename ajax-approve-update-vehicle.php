<?php
// ajax-approve-update-vehicle.php
session_start();
require_once 'connections/connection.php';
require_once 'includes/userlog.php';   // ✅ Add userlog

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.'];

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {

        $id = intval($_POST['id']);
        $approved_by = $_SESSION['hris'] ?? null;
        $approved_name = $_SESSION['name'] ?? 'Unknown';

        if (!$approved_by) {
            echo json_encode(['status' => 'error', 'message' => 'HRIS not found in session.']);
            exit;
        }

        // Fetch vehicle details
        $stmt = $conn->prepare("SELECT sr_number, vehicle_number, created_by FROM tbl_admin_vehicle WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $vehicle = $stmt->get_result()->fetch_assoc();

        if (!$vehicle) {
            echo json_encode(['status' => 'error', 'message' => 'Vehicle not found.']);
            exit;
        }

        $sr_number = $vehicle['sr_number'];
        $vehicle_number = $vehicle['vehicle_number'];
        $created_by = $vehicle['created_by'];

        // ❌ Prevent approving own record
        if ($created_by === $approved_by) {

            // ⛔ Log blocked self-approval
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
                $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $msg = sprintf(
                    "⛔ Blocked self-approval | HRIS: %s | User: %s | Vehicle ID: %s | SR: %s | IP: %s | Browser: %s",
                    $approved_by,
                    $approved_name,
                    $id,
                    $sr_number,
                    $ip,
                    substr($browser, 0, 120)
                );
                userlog($msg);
            } catch (Throwable $e) {}

            echo json_encode(['status' => 'error', 'message' => 'You cannot approve your own record.']);
            exit;
        }

        // Update approval
        $stmt = $conn->prepare("
            UPDATE tbl_admin_vehicle 
            SET status = 'Approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $approved_by, $id);

        if ($stmt->execute()) {

            // ✅ Write approval log
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
                $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

                $msg = sprintf(
                    "✅ Vehicle approved | HRIS: %s | User: %s | Vehicle ID: %s | SR: %s | Number: %s | IP: %s | Browser: %s",
                    $approved_by,
                    $approved_name,
                    $id,
                    $sr_number,
                    $vehicle_number,
                    $ip,
                    substr($browser, 0, 120)
                );
                userlog($msg);
            } catch (Throwable $e) {}

            echo json_encode([
                'status' => 'success',
                'message' => 'Vehicle approved successfully.',
                'sr_number' => $sr_number,
                'vehicle_number' => $vehicle_number
            ]);
            exit;

        } else {

            // ❌ Log DB error
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
                $msg = sprintf(
                    "❌ Approval DB error | HRIS: %s | User: %s | Vehicle ID: %s | Error: %s | IP: %s",
                    $approved_by,
                    $approved_name,
                    $id,
                    $stmt->error,
                    $ip
                );
                userlog($msg);
            } catch (Throwable $e) {}

            $response['message'] = 'Database error: ' . $stmt->error;
        }
    }

} catch (Throwable $e) {

    // ❌ Log unexpected error
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        userlog("❌ Exception during approval | HRIS: " . ($_SESSION['hris'] ?? 'N/A') . " | Error: " . $e->getMessage() . " | IP: $ip");
    } catch (Throwable $ignore) {}

    $response['message'] = "Unexpected error occurred.";
}

echo json_encode($response);
exit;
?>
