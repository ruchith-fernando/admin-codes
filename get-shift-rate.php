<?php
require_once 'connections/connection.php';

$code = $_GET['code'] ?? '';
$month = $_GET['month'] ?? '';

$response = ['rate' => 0];

if ($code && $month) {
    $stmt = $conn->prepare("SELECT rate FROM tbl_admin_budget_security WHERE branch_code = ? AND month_applicable = ?");
    $stmt->bind_param("ss", $code, $month);
    $stmt->execute();
    $stmt->bind_result($rate);
    if ($stmt->fetch()) {
        $response['rate'] = $rate;
    }
    $stmt->close();
}

echo json_encode($response);
?>
