<?php
include 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

function logToFile($message) {
    $logFile = 'logs/vehicle-actions.log';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileRef = $conn->real_escape_string($_POST['file_ref']);
    $sentTo = $conn->real_escape_string($_POST['contract_sent_to']);
    $sentWhere = $conn->real_escape_string($_POST['contract_sent_where']);
    $sentDate = $conn->real_escape_string($_POST['contract_sent_date']);

    logToFile("Received POST to mark-contract-sent.php for file_ref=$fileRef");

    $sql = "UPDATE tbl_admin_fixed_assets 
            SET contract_sent_to = '$sentTo',
                contract_sent_where = '$sentWhere',
                contract_sent_date = '$sentDate'
            WHERE file_ref = '$fileRef'";

    if ($conn->query($sql)) {
        logToFile("SUCCESS: Updated contract sent fields for $fileRef");
        echo json_encode(['status' => 'success']);
    } else {
        logToFile("ERROR: DB error for $fileRef - " . $conn->error);
        echo json_encode(['status' => 'db_error', 'message' => $conn->error]);
    }
} else {
    logToFile("ERROR: Invalid request method in mark-contract-sent.php");
    echo json_encode(['status' => 'invalid_request']);
}
