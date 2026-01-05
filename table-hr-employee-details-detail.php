<?php
include 'connections/connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$hris = trim($_GET['hris'] ?? '');
if ($hris === '') {
  echo '<div class="alert alert-warning">Missing HRIS.</div>';
  exit;
}

try {
  $stmt = $conn->prepare("
    SELECT * FROM tbl_admin_employee_details WHERE hris = ? LIMIT 1
  ");
  $stmt->bind_param("s", $hris);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) {
    echo '<div class="alert alert-info">No employee found for HRIS ' . htmlspecialchars($hris) . '</div>';
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
  exit;
}
?>
<table class="table table-sm table-bordered mt-3">
  <tr><th>HRIS</th><td><?= htmlspecialchars($row['hris']); ?></td></tr>
  <tr><th>Name</th><td><?= htmlspecialchars($row['name_of_employee']); ?></td></tr>
  <tr><th>Designation</th><td><?= htmlspecialchars($row['designation']); ?></td></tr>
  <tr><th>Location</th><td><?= htmlspecialchars($row['location']); ?></td></tr>
  <tr><th>Status</th><td><?= htmlspecialchars($row['status']); ?></td></tr>
  <tr><th>Joined</th><td><?= htmlspecialchars($row['date_joined']); ?></td></tr>
  <tr><th>Resigned</th><td><?= htmlspecialchars($row['date_resigned']); ?></td></tr>
</table>
