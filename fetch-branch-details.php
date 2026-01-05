<?php
require_once 'connections/connection.php';
$branch_code = $_POST['branch_code'];
$month = $_POST['month'];
$response = ['branch' => '', 'rate' => 0];

$stmt = $conn->prepare("SELECT branch, rate FROM tbl_admin_budget_security WHERE branch_code = ? AND month_applicable = ? LIMIT 1");
$stmt->bind_param("ss", $branch_code, $month);
$stmt->execute();
$stmt->bind_result($branch, $rate);
if ($stmt->fetch()) $response = ['branch' => $branch, 'rate' => $rate];
echo json_encode($response);
?>
