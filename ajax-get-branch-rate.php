<?php
// ajax-get-branch-rate.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');
// firm_id is passed but not used here; kept for future flexibility
$firm_id     = trim($_POST['firm_id'] ?? '');

file_put_contents('rate-debug.log', date("Y-m-d H:i:s")." | branch_code=$branch_code | month=$month | firm_id=$firm_id\n", FILE_APPEND);

if (empty($branch_code) || empty($month)) {
    echo json_encode(['success' => false, 'message' => 'Branch code and month required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT rate, no_of_shifts 
    FROM tbl_admin_budget_security 
    WHERE branch_code = ? AND month_applicable = ? 
    LIMIT 1
");
$stmt->bind_param("ss", $branch_code, $month);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success'       => true,
        'rate'          => (float)$row['rate'],
        'budget_shifts' => (int)$row['no_of_shifts']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Rate / budget not found']);
}
