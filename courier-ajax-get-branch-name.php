<?php
// courier-ajax-get-branch-name.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = $_POST['branch_code'] ?? '';
// month is not really needed but we accept it to mirror security interface
$month       = $_POST['month'] ?? '';

if ($branch_code === '') {
    echo json_encode(['success' => false, 'message' => 'Branch code is required.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT branch_name 
    FROM tbl_admin_branch_courier
    WHERE branch_code = ?
    LIMIT 1
");
$stmt->bind_param("s", $branch_code);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'branch'  => $row['branch_name']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Branch not found'
    ]);
}
