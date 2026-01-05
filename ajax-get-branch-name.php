<?php
// ajax-get-branch-name.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$firm_id     = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;
$month       = trim($_POST['month'] ?? '');

// Logging incoming request for debugging
file_put_contents(
    'branch-debug.log',
    date("Y-m-d H:i:s")." | branch_code={$branch_code} | firm_id={$firm_id} | month={$month}\n",
    FILE_APPEND
);

if (empty($branch_code) || !$firm_id || empty($month)) {
    echo json_encode(['success' => false, 'message' => 'Branch code, firm and month are required']);
    exit;
}

/*
 * Only accept branches that are:
 *   1) Mapped to this firm (active = yes)
 *   2) Present in the budget for this month
 */
$stmt = $conn->prepare("
    SELECT m.branch_name
    FROM tbl_admin_branch_firm_map m
    INNER JOIN tbl_admin_budget_security b
        ON b.branch_code = m.branch_code
       AND b.month_applicable = ?
    WHERE m.branch_code = ?
      AND m.firm_id     = ?
      AND m.active      = 'yes'
    LIMIT 1
");
$stmt->bind_param("ssi", $month, $branch_code, $firm_id);
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
        'message' => 'Branch not mapped to this firm for the selected month, or not in the budget'
    ]);
}
