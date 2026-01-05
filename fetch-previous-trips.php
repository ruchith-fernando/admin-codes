<?php
// fetch-previous-trips.php
include 'connections/connection.php';
header('Content-Type: application/json');

$start = trim($_GET['start'] ?? '');
$end   = trim($_GET['end'] ?? '');

$trips = [];

if ($start && $end) {
  $stmt = $conn->prepare("SELECT date, voucher_no, total_km, additional_charges, total, created_at, created_by_hris FROM tbl_admin_kangaroo_transport
    WHERE LOWER(TRIM(start_location)) = LOWER(TRIM(?))
      AND LOWER(TRIM(end_location))   = LOWER(TRIM(?))
    ORDER BY date DESC
    LIMIT 10
  ");
  $stmt->bind_param("ss", $start, $end);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $trips[] = $row; // raw data; frontend does formatting
  }
  $stmt->close();
}
$conn->close();

echo json_encode($trips);
