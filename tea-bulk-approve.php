<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
$current_name = trim((string)($_SESSION['name'] ?? ''));
if($current_hris===''){ echo json_encode(["success"=>false,"message"=>"Session expired"]); exit; }

$ids_raw = $_POST['ids'] ?? '';
$id_list = array_filter(array_map('intval', explode(',', $ids_raw)));
$id_list = array_values(array_unique($id_list));
if(!$id_list){ echo json_encode(["success"=>false,"message"=>"Invalid request"]); exit; }

$id_str = implode(",", $id_list);

/* approve pending except own entries */
$sql = "
  UPDATE tbl_admin_tea_service_hdr
  SET approval_status='approved',
      approved_hris='" . mysqli_real_escape_string($conn, $current_hris) . "',
      approved_name='" . mysqli_real_escape_string($conn, $current_name) . "',
      approved_at=NOW()
  WHERE id IN ($id_str)
    AND approval_status='pending'
    AND TRIM(entered_hris) <> '" . mysqli_real_escape_string($conn, $current_hris) . "'
";
mysqli_query($conn, $sql);
$count = mysqli_affected_rows($conn);

userlog("✅ Tea Bulk Approve | By: {$current_name} ({$current_hris}) | Count: {$count} | IDs: {$id_str}");

if($count <= 0){
  echo json_encode(["success"=>false,"message"=>"No records approved. They may all be non-pending or your own entries."]);
  exit;
}

echo json_encode(["success"=>true,"message"=>"Bulk approval completed ({$count} record(s))."]);
