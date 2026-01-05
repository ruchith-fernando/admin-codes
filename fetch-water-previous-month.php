<?php
// fetch-water-previous-month.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');

if ($month === '' || $branch_code === '') {
    echo json_encode(['status'=>'error','message'=>'Missing params']);
    exit;
}

// Convert "June 2025" → previous month "May 2025"
$ts = strtotime('1 '.$month);
$prev = date('F Y', strtotime('-1 month', $ts));

$sql = "
    SELECT total_amount, is_provision, provision_reason
    FROM tbl_admin_actual_water
    WHERE month_applicable = '".mysqli_real_escape_string($conn,$prev)."'
      AND branch_code = '".mysqli_real_escape_string($conn,$branch_code)."'
    LIMIT 1
";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    echo json_encode(['status'=>'success','month'=>$prev,'data'=>$row]);
} else {
    echo json_encode(['status'=>'empty','month'=>$prev]);
}
