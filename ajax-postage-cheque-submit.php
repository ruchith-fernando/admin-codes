<?php
// ajax-postage-cheque-submit.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include 'connections/connection.php';

$response = ['status' => 'error', 'message' => '', 'end_balance' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cheque_date = $_POST['cheque_date'] ?? '';
    $cheque_number = trim($_POST['cheque_number'] ?? '');
    $cheque_amount_raw = $_POST['cheque_amount'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    // Sanitize and validate amount
    $cheque_amount = str_replace(',', '', $cheque_amount_raw);
    if (!is_numeric($cheque_amount) || $cheque_amount <= 0) {
        $response['message'] = "Please enter a valid numeric cheque amount.";
        echo json_encode($response);
        exit;
    }

    if (empty($cheque_date) || empty($cheque_number)) {
        $response['message'] = "Cheque date and cheque number are required.";
        echo json_encode($response);
        exit;
    }

    // Check for duplicates
    $check = $conn->prepare("SELECT id FROM tbl_admin_postage_cheques WHERE cheque_date = ? AND cheque_number = ? AND cheque_amount = ?");
    $check->bind_param("ssd", $cheque_date, $cheque_number, $cheque_amount);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $response['status'] = 'duplicate';
        $response['message'] = 'Duplicate entry found.';
        echo json_encode($response);
        exit;
    }

    // Insert new cheque
    $insert = $conn->prepare("INSERT INTO tbl_admin_postage_cheques (cheque_date, cheque_number, cheque_amount, remarks) VALUES (?, ?, ?, ?)");
    $insert->bind_param("ssds", $cheque_date, $cheque_number, $cheque_amount, $remarks);

    if ($insert->execute()) {
        // Update end balance in latest postage stamp record
        $latestStmt = $conn->query("SELECT id, end_balance FROM tbl_admin_actual_postage_stamps ORDER BY id DESC LIMIT 1");
        if ($latestRow = $latestStmt->fetch_assoc()) {
            $new_balance = $latestRow['end_balance'] + floatval($cheque_amount);
            $update = $conn->prepare("UPDATE tbl_admin_actual_postage_stamps SET end_balance = ? WHERE id = ?");
            $update->bind_param("di", $new_balance, $latestRow['id']);
            $update->execute();

            $response['end_balance'] = $new_balance;
        }

        $response['status'] = 'success';
        $response['message'] = 'Cheque recorded successfully.';
    } else {
        $response['message'] = "Database insert failed: " . $conn->error;
    }
}

echo json_encode($response);
exit;
