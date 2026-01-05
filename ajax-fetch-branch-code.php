<?php
include 'connections/connection.php';

$branch_name = trim($_POST['branch_name'] ?? '');

$response = ['status' => 'error'];

if ($branch_name !== '') {
    $stmt = $conn->prepare("SELECT branch_id FROM tbl_admin_branch_information WHERE branch_name = ?");
    $stmt->bind_param("s", $branch_name);
    $stmt->execute();
    $stmt->bind_result($branch_id);
    if ($stmt->fetch()) {
        $response = ['status' => 'success', 'branch_code' => $branch_id];
    }
    $stmt->close();
}

echo json_encode($response);
