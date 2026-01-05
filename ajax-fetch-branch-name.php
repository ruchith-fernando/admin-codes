<?php
include 'connections/connection.php';
header('Content-Type: application/json');

if (!isset($_POST['branch_code'])) {
    echo json_encode(['success' => false, 'message' => 'Branch code not provided.']);
    exit;
}

$branch_code = trim($_POST['branch_code']);

$query = "SELECT branch_name FROM tbl_admin_branch_information WHERE branch_id = '$branch_code' LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Branch not found.']);
    exit;
}

$row = mysqli_fetch_assoc($result);

echo json_encode([
    'success' => true,
    'branch_name' => $row['branch_name']
]);
