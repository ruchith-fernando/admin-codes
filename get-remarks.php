<?php
// get-remarks.php
require_once 'connections/connection.php';

$category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
$record   = mysqli_real_escape_string($conn, $_POST['record'] ?? '');

$res = $conn->query("SELECT sr_number, hris_id, comment, commented_at 
                     FROM tbl_admin_remarks 
                     WHERE category='$category' AND record_key='$record'
                     ORDER BY commented_at DESC");

$out = [];
while($row = $res->fetch_assoc()){ $out[] = $row; }
echo json_encode($out);
