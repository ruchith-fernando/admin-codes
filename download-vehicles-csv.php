<?php
// download-vehicles-csv.php
include 'connections/connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset('utf8mb4');

function formatMileage($v): string {
    if ($v === null) return 'Not Available';

    $raw = trim((string)$v);
    if ($raw === '') return 'Not Available';

    $u = strtoupper($raw);
    if ($u === 'NA' || $u === 'N/A') return 'Not Available';

    // If numeric and equals 1 => Not Available
    if (is_numeric($raw)) {
        $num = (float)$raw;

        if (abs($num - 1.0) < 0.0000001) return 'Not Available';

        // ✅ return as plain number (NO thousand separators)
        // If it's an integer, remove decimals
        if (floor($num) == $num) return (string)(int)$num;

        // Otherwise return as-is (keeps decimals if any)
        // You can also control decimals here if needed
        return rtrim(rtrim((string)$num, '0'), '.');
    }

    return 'Not Available';
}


$search = trim($_GET['search'] ?? '');

$where  = "status = 'Approved'";
$params = [];
$types  = '';

if ($search !== '') {
    $cols = [
        'make_model',
        'vehicle_number',
        'assigned_user',
        'assigned_user_hris',
        'vehicle_type',
        'chassis_number'
    ];

    $likes = [];
    foreach ($cols as $c) $likes[] = "$c LIKE CONCAT('%', ?, '%')";

    $where .= " AND (" . implode(' OR ', $likes) . ")";
    $types  = str_repeat('s', count($cols));
    $params = array_fill(0, count($cols), $search);
}

$sql = "
  SELECT
    vehicle_type,
    vehicle_number,
    chassis_number,
    make_model,
    engine_capacity,
    year_of_manufacture,
    fuel_type,
    purchase_date,
    purchase_value,
    original_mileage,
    assigned_user,
    assigned_user_hris,
    vehicle_category
  FROM tbl_admin_vehicle
  WHERE $where
  ORDER BY purchase_date DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rs = $stmt->get_result();

$filename = 'approved_vehicle_records_' . date('Y-m-d_H-i-s') . '.csv';

// headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename='.$filename);

$out = fopen('php://output', 'w');

// column headers
fputcsv($out, [
  'Vehicle Type',
  'Vehicle Number',
  'Chassis Number',
  'Make & Model',
  'Engine Capacity (cc)',
  'Year',
  'Fuel Type',
  'Purchase Date',
  'Value (LKR)',
  'Original Mileage',
  'Assigned User',
  'Assigned User HRIS',
  'Category'
]);

while ($r = $rs->fetch_assoc()) {
    fputcsv($out, [
      $r['vehicle_type'] ?? '',
      $r['vehicle_number'] ?? '',
      $r['chassis_number'] ?? '',
      $r['make_model'] ?? '',
      $r['engine_capacity'] ?? '',
      $r['year_of_manufacture'] ?? '',
      $r['fuel_type'] ?? '',
      $r['purchase_date'] ?? '',
      $r['purchase_value'] ?? '',
       formatMileage($r['original_mileage'] ?? null),
      $r['assigned_user'] ?? '',
      $r['assigned_user_hris'] ?? '',
      $r['vehicle_category'] ?? ''
    ]);
}

fclose($out);
$stmt->close();
exit;
