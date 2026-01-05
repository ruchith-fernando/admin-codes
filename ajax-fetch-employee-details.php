<?php
include 'connections/connection.php';

header("Content-Type: application/json");

$hris = trim($_POST['hris'] ?? '');

if (!$hris) {
    echo json_encode(['status' => 'error', 'message' => 'HRIS missing']);
    exit;
}

$q = $conn->prepare("SELECT * FROM tbl_admin_employee_details WHERE hris = ? AND status = 'Active'");
$q->bind_param("s", $hris);
$q->execute();
$res = $q->get_result();

if ($res->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not found']);
    exit;
}

$row = $res->fetch_assoc();

// Fetch branch code
$bq = $conn->prepare("SELECT branch_id FROM tbl_admin_branch_information WHERE branch_name = ?");
$bq->bind_param("s", $row['location']);
$bq->execute();
$r2 = $bq->get_result();

$branch_code = ($r2->num_rows > 0) ? $r2->fetch_assoc()['branch_id'] : '';

echo json_encode([
    'status' => 'success',
    'name' => $row['display_name'],
    'designation' => $row['designation'],
    'title' => $row['title'],
    'company_hierarchy' => $row['company_hierarchy'],
    'location' => $row['location'],
    'category' => $row['category'],
    'branch_code' => $branch_code
]);
