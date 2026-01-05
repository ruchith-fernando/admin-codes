<?php
require_once 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . ($_GET['filename'] ?? 'repair-records.csv') . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['#', 'Vehicle Number', 'Assigned User', 'Date', 'Maintenance Type', 'Description', 'Mileage/Meter', 'Amount (Rs)', 'Handled By']);

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$search     = trim($_GET['search'] ?? '');

$rows = [];

/* ===== VEHICLE MAP ===== */
$assignedUsers = [];
$res = $conn->query("SELECT vehicle_number, assigned_user FROM tbl_admin_vehicle WHERE status='Approved'");
while ($r = $res->fetch_assoc()) {
  $assignedUsers[$r['vehicle_number']] = $r['assigned_user'];
}

function inDateRange($date, $start, $end) {
  if (!$date || $date === '0000-00-00') return false;
  $ts = strtotime($date);
  $start_ts = $start ? strtotime($start) : null;
  $end_ts   = $end ? strtotime($end . ' +1 day') : null;
  if ($start_ts && $ts < $start_ts) return false;
  if ($end_ts && $ts >= $end_ts) return false;
  return true;
}

function getDescription($type, $raw = '') {
  return match ($type) {
    'Emission Test'   => 'For Emission Test',
    'Revenue License' => 'For Revenue License',
    'Battery'         => 'For Purchase of Battery',
    'Tire'            => 'Replacement of Tires',
    'AC', 'AC Repair' => 'For Repair of AC',
    'Service'         => 'For Service',
    'Other'           => $raw ?: 'Other Maintenance Work',
    default           => $raw
  };
}

/* ===== LICENSING ===== */
$qLic = "
  SELECT vehicle_number, emission_test_date AS entry_date, emission_test_amount AS amount,
         'Emission Test' AS type, person_handled AS person
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND emission_test_date IS NOT NULL AND emission_test_date <> '0000-00-00'
  UNION ALL
  SELECT vehicle_number, revenue_license_date AS entry_date, revenue_license_amount AS amount,
         'Revenue License' AS type, person_handled AS person
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND revenue_license_date IS NOT NULL AND revenue_license_date <> '0000-00-00'
";
$res = $conn->query($qLic);
while ($r = $res->fetch_assoc()) {
  if (!inDateRange($r['entry_date'], $start_date, $end_date)) continue;
  $rows[] = [
    'vehicle' => $r['vehicle_number'],
    'date' => $r['entry_date'],
    'type' => $r['type'],
    'desc' => getDescription($r['type']),
    'mileage' => '',
    'amount' => $r['amount'],
    'person' => $r['person'],
    'assigned_user' => $assignedUsers[$r['vehicle_number']] ?? ''
  ];
}

/* ===== MAINTENANCE ===== */
$qMaint = "
  SELECT * FROM tbl_admin_vehicle_maintenance
  WHERE STATUS='Approved'
  AND ((purchase_date IS NOT NULL AND purchase_date <> '0000-00-00')
    OR (repair_date IS NOT NULL AND repair_date <> '0000-00-00'))
";
$res = $conn->query($qMaint);
while ($r = $res->fetch_assoc()) {
  $date = ($r['purchase_date'] && $r['purchase_date'] !== '0000-00-00') ? $r['purchase_date'] : $r['repair_date'];
  if (!inDateRange($date, $start_date, $end_date)) continue;
  $type = $r['maintenance_type'] ?: 'Other';
  $desc = getDescription($type, $r['problem_description']);
  $rows[] = [
    'vehicle' => $r['vehicle_number'],
    'date' => $date,
    'type' => ($type === 'AC') ? 'AC Repair' : $type,
    'desc' => $desc,
    'mileage' => $r['mileage'],
    'amount' => $r['price'],
    'person' => $r['driver_name'],
    'assigned_user' => $assignedUsers[$r['vehicle_number']] ?? ''
  ];
}

/* ===== SERVICE ===== */
$qServ = "
  SELECT * FROM tbl_admin_vehicle_service
  WHERE STATUS='Approved'
  AND service_date IS NOT NULL AND service_date <> '0000-00-00'
";
$res = $conn->query($qServ);
while ($r = $res->fetch_assoc()) {
  if (!inDateRange($r['service_date'], $start_date, $end_date)) continue;
  $rows[] = [
    'vehicle' => $r['vehicle_number'],
    'date' => $r['service_date'],
    'type' => 'Service',
    'desc' => getDescription('Service'),
    'mileage' => $r['meter_reading'],
    'amount' => $r['amount'],
    'person' => $r['driver_name'],
    'assigned_user' => $assignedUsers[$r['vehicle_number']] ?? ''
  ];
}

/* ===== SORT ===== */
usort($rows, fn($a, $b) => strcmp($a['vehicle'], $b['vehicle']) ?: strcmp($a['date'], $b['date']));

/* ===== SEARCH FILTER ===== */
if ($search !== '') {
  $rows = array_filter($rows, function ($r) use ($search) {
    return stripos($r['vehicle'], $search) !== false ||
           stripos($r['assigned_user'], $search) !== false ||
           stripos($r['desc'], $search) !== false ||
           stripos($r['type'], $search) !== false ||
           stripos($r['person'], $search) !== false;
  });
}

/* ===== OUTPUT CSV ===== */
$i = 1;
foreach ($rows as $r) {
  fputcsv($output, [
    $i++,
    $r['vehicle'],
    $r['assigned_user'],
    $r['date'],
    $r['type'],
    $r['desc'],
    $r['mileage'],
    number_format((float)$r['amount'], 2),
    $r['person']
  ]);
}

fclose($output);
exit;
?>
