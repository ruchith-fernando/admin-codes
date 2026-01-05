<?php
require_once 'connections/connection.php';

$code = $_GET['code'] ?? '';
$name = '';

if ($code !== '') {
    $stmt = $conn->prepare("SELECT branch FROM tbl_admin_budget_security WHERE branch_code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
}

echo $name;
?>
