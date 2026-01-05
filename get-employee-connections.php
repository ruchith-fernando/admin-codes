<?php
include 'connections/connection.php';

$hris = $_POST['hris_no'] ?? '';
$hris = $conn->real_escape_string($hris);

if (!$hris) {
  echo '';
  exit;
}

$sql = "SELECT mobile_no, status
FROM tbl_admin_mobile_issues
WHERE hris_no = '$hris' 
  AND (connection_status='Connected' OR connection_status='Active')
ORDER BY id DESC";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
  echo "<ul class='list-unstyled mb-0'>";
  while ($row = $res->fetch_assoc()) {
    echo "<li>📱 " . htmlspecialchars($row['mobile_no']) . " – " . htmlspecialchars($row['status']) . "</li>";
  }
  echo "</ul>";
} else {
  echo "<div class='text-muted small'>(No active connections)</div>";
}
