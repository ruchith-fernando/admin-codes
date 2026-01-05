<?php
include 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$hris = trim($_GET['hris'] ?? '');
if ($hris === '') {
  echo '<div class="alert alert-warning">Missing HRIS.</div>';
  exit;
}

try {
  $stmt = $conn->prepare("SELECT * FROM tbl_admin_employee_details WHERE hris = ? LIMIT 1");
  $stmt->bind_param('s', $hris);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) {
    echo '<div class="alert alert-info">No record found for HRIS ' . htmlspecialchars($hris) . '</div>';
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo '<div class="alert alert-danger">[ERR-DETAIL] ' . htmlspecialchars($e->getMessage()) . '</div>';
  exit;
}

// ✅ Make resigned date blank if not valid
$dateResigned = $row['date_resigned'];
if ($dateResigned === '0000-00-00' || $dateResigned === null || trim($dateResigned) === '') {
  $dateResigned = '';
}
?>

<table class="table table-bordered table-sm mt-2">
  <tr><th>HRIS</th><td><?= htmlspecialchars($row['hris']); ?></td></tr>
  <tr><th>Name</th><td><?= htmlspecialchars($row['name_of_employee']); ?></td></tr>
  <tr><th>Display Name</th><td><?= htmlspecialchars($row['display_name']); ?></td></tr>
  <tr><th>EPF No</th><td><?= htmlspecialchars($row['epf_no']); ?></td></tr>
  <tr><th>Designation</th><td><?= htmlspecialchars($row['designation']); ?></td></tr>
  <tr><th>Location</th><td><?= htmlspecialchars($row['location']); ?></td></tr>
  <tr><th>NIC No</th><td><?= htmlspecialchars($row['nic_no']); ?></td></tr>
  <tr><th>Category</th><td><?= htmlspecialchars($row['category']); ?></td></tr>
  <tr><th>Employment Category</th><td><?= htmlspecialchars($row['employment_categories']); ?></td></tr>
  <tr><th>Status</th><td><?= htmlspecialchars($row['status']); ?></td></tr>
  <tr><th>Date Joined</th><td><?= htmlspecialchars($row['date_joined']); ?></td></tr>
  <tr><th>Date Resigned</th><td><?= htmlspecialchars($dateResigned); ?></td></tr>
</table>
