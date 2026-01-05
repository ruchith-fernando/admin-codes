<?php
// ajax-get-printing-branch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');

if ($branch_code === '') {
    echo json_encode(['status'=>'error','message'=>'No branch code']);
    exit;
}

$sql = "SELECT branch_code, branch_name
    FROM tbl_admin_branch_printing
    WHERE branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    echo json_encode(['status'=>'success','data'=>$row]);
} else {
    echo json_encode(['status'=>'empty']);
}
