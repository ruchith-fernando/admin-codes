<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$qLike = "%{$q}%";

$stmt = $conn->prepare("
  SELECT branch_code, branch_name
  FROM tbl_admin_branches
  WHERE is_active = 1
    AND (
      branch_code LIKE ?
      OR branch_name LIKE ?
    )
  ORDER BY branch_name
  LIMIT 50
");
$stmt->bind_param("ss", $qLike, $qLike);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  $items[] = [
    "id" => $row['branch_code'],                 // Select2 value
    "text" => $row['branch_name']." (".$row['branch_code'].")",
    "branch_name" => $row['branch_name'],
    "branch_code" => $row['branch_code']
  ];
}

echo json_encode([
  "results" => $items
]);
