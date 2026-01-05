<?php
include 'connections/connection.php';

$mobile = $_POST['mobile_no'] ?? '';
$mobile = $conn->real_escape_string($mobile);

if (!$mobile) {
  echo '';
  exit;
}

$sql = "
  SELECT name_of_employee, hris_no, status
  FROM tbl_admin_mobile_issues
  WHERE mobile_no = '$mobile'
  ORDER BY id DESC
  LIMIT 1
";
$res = $conn->query($sql);

if ($res && $row = $res->fetch_assoc()) {
  echo $row['name_of_employee'] . " (" . $row['hris_no'] . ") – " . $row['status'];
} else {
  echo '';
}
