<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  echo json_encode(['ok'=>false, 'error'=>'Search value is required']);
  exit;
}

$q_clean = preg_replace('/\s+/', '', $q);

// Search employee table by "contains" (6428 matches 01006428, 16428, etc.)
$like = '%' . $q_clean . '%';

$stmt = $conn->prepare("
  SELECT
    e.hris,
    e.name_of_employee AS owner_name,
    e.status AS emp_status,
    (
      SELECT a.mobile_number
      FROM tbl_admin_mobile_allocations a
      WHERE TRIM(a.hris_no)=TRIM(e.hris)
        AND a.status='Active'
        AND a.effective_to IS NULL
      ORDER BY a.effective_from DESC, a.id DESC
      LIMIT 1
    ) AS active_mobile
  FROM tbl_admin_employee_details e
  WHERE TRIM(e.hris) LIKE ?
  ORDER BY (TRIM(e.hris)=?) DESC, LENGTH(TRIM(e.hris)) ASC
  LIMIT 20
");
$stmt->bind_param("ss", $like, $q_clean);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

echo json_encode([
  'ok' => true,
  'query' => $q_clean,
  'count' => count($rows),
  'rows' => $rows
]);
