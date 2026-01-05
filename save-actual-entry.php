<?php
require_once 'connections/connection.php';
$branch_code = $_POST['branch_code'];
$month = $_POST['month'];
$branch = $_POST['branch'];
$shifts = (int)$_POST['shifts'];
$amount = (float)$_POST['amount'];

$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_admin_actual_security WHERE branch_code = ? AND month_applicable = ?");
$stmt->bind_param("ss", $branch_code, $month);
$stmt->execute(); $stmt->bind_result($count); $stmt->fetch(); $stmt->close();

if ($count > 0) exit(json_encode(['status' => 'duplicate']));

$stmt = $conn->prepare("INSERT INTO tbl_admin_actual_security (branch_code, branch, month_applicable, actual_shifts, total_amount) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssii", $branch_code, $branch, $month, $shifts, $amount);
$stmt->execute();
echo json_encode(['status' => 'saved']);
?>
