<?php
// ajax-get-branch-name-photocopy.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($branch_code === '' || $month === '') {
    echo json_encode(['success' => false, 'message' => 'Branch code and month are required']);
    exit;
}

// FY budget year (Apr -> Mar)
$ts = strtotime("1 " . $month);
if (!$ts) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}
$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$budget_year = ($mn < 4) ? ($y - 1) : $y;

$stmt = $conn->prepare("
    SELECT br.branch_name, COALESCE(b.amount,0) AS bud
    FROM tbl_admin_branches br
    INNER JOIN tbl_admin_budget_photocopy b
      ON b.branch_code = br.branch_code
     AND b.budget_year = ?
    WHERE br.branch_code = ?
      AND br.is_active = 1
    LIMIT 1
");
$stmt->bind_param("ss", $budget_year, $branch_code);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $bud = (float)$row['bud'];
    if ($bud <= 0) {
        echo json_encode(['success'=>false,'message'=>"No budget / 0 budget for FY {$budget_year}."]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'branch'  => $row['branch_name'],
        'budget_year' => (string)$budget_year,
        'budget' => $bud
    ]);
    exit;
}

echo json_encode(['success'=>false,'message'=>"Branch not found/inactive OR no budget for FY {$budget_year}."]);
exit;
