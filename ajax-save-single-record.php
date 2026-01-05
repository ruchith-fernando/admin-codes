<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code=trim($_POST['branch_code']??'');
$branch=trim($_POST['branch']??'');
$month=trim($_POST['month_applicable']??'');
$shifts=intval($_POST['actual_shifts']??0);
$amount=floatval(str_replace(',','',$_POST['total_amount']??0));
$provision=trim($_POST['provision']??'no');

if($branch_code===''||$shifts<1){
    echo json_encode(['success'=>false,'message'=>'Branch code and minimum shifts required']);
    exit;
}

$stmt=$conn->prepare("INSERT INTO tbl_admin_actual_security (branch_code,branch,month_applicable,actual_shifts,total_amount,provision) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("sssids",$branch_code,$branch,$month,$shifts,$amount,$provision);

echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'message'=>'Database error']);
?>
