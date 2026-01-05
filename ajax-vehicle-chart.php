<?php
// ajax-vehicle-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

// If the DB connection isn't healthy, return a safe empty response
if ($conn->connect_error) {
  echo json_encode([
    'labels' => [],
    'budget' => [],
    'actual' => [],
    'error'  => 'DB connection failed'
  ]);
  exit;
}

/*
  Optional query param:
  - include_zero=1  => keep months even if actual is 0 (default: skip/return nulls)
*/
$includeZero = (isset($_GET['include_zero']) && $_GET['include_zero'] === '1');

/* -----------------------------------------
   Figure out the current FY (Apr -> Mar)
   We only want months that fall inside this FY window.
------------------------------------------ */
$tz  = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);

$fyStartMonth = 4; // April
$fyStartYear  = ((int)$now->format('n') >= $fyStartMonth)
  ? (int)$now->format('Y')
  : ((int)$now->format('Y') - 1);

$fyStart = new DateTime($fyStartYear . '-04-01', $tz);
$fyEnd   = (clone $fyStart)->modify('+1 year')->modify('-1 day'); // up to Mar 31

$fyStartStr = $fyStart->format('Y-m-d');
$fyEndStr   = $fyEnd->format('Y-m-d');

/* ---------------------------------------------------------
   1) Build the label list (months) for this FY.
   We don’t assume a fixed Apr->Mar list here.
   Instead, we take the union of:
   - budget months
   - months that actually have approved entries (maintenance/service/licensing)
   Then we filter that union to only months inside the FY.
---------------------------------------------------------- */
$sqlMonths = "
  SELECT m.month_name
  FROM (
    SELECT budget_month AS month_name
    FROM tbl_admin_budget_vehicle_maintenance
    WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''

    UNION

    SELECT DATE_FORMAT(report_date, '%M %Y') AS month_name
    FROM tbl_admin_vehicle_maintenance
    WHERE status='Approved' AND report_date IS NOT NULL

    UNION

    SELECT DATE_FORMAT(report_date, '%M %Y') AS month_name
    FROM tbl_admin_vehicle_service
    WHERE status='Approved' AND report_date IS NOT NULL

    UNION

    SELECT DATE_FORMAT(report_date, '%M %Y') AS month_name
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE status='Approved' AND report_date IS NOT NULL
  ) m
  WHERE m.month_name IS NOT NULL AND TRIM(m.month_name) <> ''
    AND STR_TO_DATE(CONCAT('01 ', m.month_name), '%d %M %Y')
        BETWEEN '{$fyStartStr}' AND '{$fyEndStr}'
  ORDER BY STR_TO_DATE(m.month_name, '%M %Y')
";

$labels = [];
$resM = $conn->query($sqlMonths);
if ($resM) {
  while ($r = $resM->fetch_assoc()) {
    $labels[] = $r['month_name'];
  }
}

// Nothing to plot for this FY
if (!count($labels)) {
  echo json_encode(['labels' => [], 'budget' => [], 'actual' => []]);
  exit;
}

// Safe IN(...) list of months
$inMonths = implode(", ", array_map(
  fn($m) => "'" . $conn->real_escape_string($m) . "'",
  $labels
));

/* ---------------------------------------------------------
   2) Budget per month (vehicle maintenance budget table)
---------------------------------------------------------- */
$budgetMap = array_fill_keys($labels, 0.0);

$resB = $conn->query("
  SELECT budget_month,
         SUM(CAST(REPLACE(COALESCE(amount,'0'), ',', '') AS DECIMAL(15,2))) AS budget_amount
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IN ($inMonths)
  GROUP BY budget_month
");

if ($resB) {
  while ($r = $resB->fetch_assoc()) {
    $mm = trim((string)$r['budget_month']);
    if (isset($budgetMap[$mm])) {
      $budgetMap[$mm] = (float)($r['budget_amount'] ?? 0);
    }
  }
}

/* ---------------------------------------------------------
   3) Actuals per month (Approved only)
   Actuals are cumulative across:
   - Maintenance
   - Service
   - Licensing/Insurance
---------------------------------------------------------- */
$actualMap = array_fill_keys($labels, 0.0);

/*
  Maintenance actual:
  - "Tire" is special: it may have multiple tire items (tbl_admin_vehicle_maintenance_tire_items)
    plus wheel alignment.
  - Other types use vm.price as the main amount.
*/
$resA1 = $conn->query("
  SELECT
    DATE_FORMAT(vm.report_date, '%M %Y') AS month_name,
    SUM(
      CASE
        WHEN vm.maintenance_type='Tire'
          THEN
            COALESCE(
              ti.tire_sum,
              CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2))
            )
            +
            CAST(REPLACE(COALESCE(vm.wheel_alignment_amount,'0'), ',', '') AS DECIMAL(15,2))
        WHEN vm.maintenance_type='Battery'
          THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2))
        WHEN vm.maintenance_type='AC'
          THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2))
        WHEN vm.maintenance_type IN ('Other','Running Repairs')
          THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2))
        ELSE 0
      END
    ) AS total_amount
  FROM tbl_admin_vehicle_maintenance vm
  LEFT JOIN (
    SELECT maintenance_id,
           SUM(CAST(REPLACE(COALESCE(tire_price,'0'), ',', '') AS DECIMAL(15,2))) AS tire_sum
    FROM tbl_admin_vehicle_maintenance_tire_items
    GROUP BY maintenance_id
  ) ti ON ti.maintenance_id = vm.id
  WHERE vm.status='Approved'
    AND vm.report_date IS NOT NULL
    AND DATE_FORMAT(vm.report_date, '%M %Y') IN ($inMonths)
  GROUP BY month_name
");

if ($resA1) {
  while ($r = $resA1->fetch_assoc()) {
    $mm = trim((string)$r['month_name']);
    if (isset($actualMap[$mm])) {
      $actualMap[$mm] += (float)($r['total_amount'] ?? 0);
    }
  }
}

/* Service actual (simple sum of amount) */
$resA2 = $conn->query("
  SELECT DATE_FORMAT(report_date, '%M %Y') AS month_name,
         SUM(CAST(REPLACE(COALESCE(amount,'0'), ',', '') AS DECIMAL(15,2))) AS total_amount
  FROM tbl_admin_vehicle_service
  WHERE status='Approved'
    AND report_date IS NOT NULL
    AND DATE_FORMAT(report_date, '%M %Y') IN ($inMonths)
  GROUP BY month_name
");

if ($resA2) {
  while ($r = $resA2->fetch_assoc()) {
    $mm = trim((string)$r['month_name']);
    if (isset($actualMap[$mm])) {
      $actualMap[$mm] += (float)($r['total_amount'] ?? 0);
    }
  }
}

/* Licensing/Insurance actual (sum of the three related amounts) */
$resA3 = $conn->query("
  SELECT DATE_FORMAT(report_date, '%M %Y') AS month_name,
         SUM(
           CAST(REPLACE(COALESCE(emission_test_amount, '0'), ',', '') AS DECIMAL(15,2)) +
           CAST(REPLACE(COALESCE(revenue_license_amount, '0'), ',', '') AS DECIMAL(15,2)) +
           CAST(REPLACE(COALESCE(insurance_amount, '0'), ',', '') AS DECIMAL(15,2))
         ) AS total_amount
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE status='Approved'
    AND report_date IS NOT NULL
    AND DATE_FORMAT(report_date, '%M %Y') IN ($inMonths)
  GROUP BY month_name
");

if ($resA3) {
  while ($r = $resA3->fetch_assoc()) {
    $mm = trim((string)$r['month_name']);
    if (isset($actualMap[$mm])) {
      $actualMap[$mm] += (float)($r['total_amount'] ?? 0);
    }
  }
}

/* ---------------------------------------------------------
   4) Output helpers
   - When include_zero=0, we return null for 0 values
     so the chart doesn't draw a flat baseline.
---------------------------------------------------------- */
function prep($labels, $map, $includeZero){
  $out = [];
  foreach ($labels as $m) {
    $v = round((float)($map[$m] ?? 0), 2);
    $out[] = $includeZero ? $v : (($v > 0) ? $v : null);
  }
  return $out;
}

/*
  Optional: match the report behavior and completely drop months
  where actual <= 0 (unless include_zero=1).
*/
if (!$includeZero) {
  $newLabels = [];
  foreach ($labels as $m) {
    if (((float)($actualMap[$m] ?? 0)) > 0) {
      $newLabels[] = $m;
    }
  }
  $labels = $newLabels;
}

echo json_encode([
  'labels' => $labels,
  'budget' => prep($labels, $budgetMap, $includeZero),
  'actual' => prep($labels, $actualMap, $includeZero)
]);
