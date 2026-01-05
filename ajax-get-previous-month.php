<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = $_POST['branch_code'];

$stmt = $conn->prepare("
    SELECT month_applicable, actual_shifts, total_amount 
    FROM tbl_admin_actual_security 
    WHERE branch_code = ? 
    ORDER BY STR_TO_DATE(month_applicable, '%M %Y') DESC 
    LIMIT 1
");
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$res = $stmt->get_result();

if($res && $row = $res->fetch_assoc()){
    echo json_encode([
        'success' => true,
        'month' => $row['month_applicable'],
        'shifts' => $row['actual_shifts'],
        'amount' => $row['total_amount']
    ]);
} else {
    echo json_encode(['success'=>false]);
}
?>
