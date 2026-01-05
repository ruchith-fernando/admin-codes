<?php
// ajax-security-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

// If the DB connection isn't available, return an empty payload + error
if ($conn->connect_error) {
  echo json_encode([
    'labels' => [],
    'budget' => [],
    'actual' => [],
    'error'  => 'DB connection failed'
  ]);
  exit;
}

// Query param: include_zero=1 will keep months even when actual is 0 (default: skip those months)
$includeZero = (isset($_GET['include_zero']) && $_GET['include_zero'] === '1');

// Build the current financial year window (Apr -> Mar) using Asia/Colombo time
$tz  = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);

$fyStartMonth = 4; // April
$fyStartYear  = ((int)$now->format('n') >= $fyStartMonth)
  ? (int)$now->format('Y')
  : ((int)$now->format('Y') - 1);

$start = new DateTime($fyStartYear . '-04-01', $tz);
$end   = (clone $start)->modify('+11 months'); // total 12 months

// These labels must match your DB's month_applicable format (e.g., "April 2025")
$months = [];
$cursor = clone $start;
while ($cursor <= $end) {
  $months[] = $cursor->format('F Y');
  $cursor->modify('+1 month');
}

// Create a safe IN(...) list for month_applicable
$inMonths = implode(", ", array_map(
  fn($m) => "'" . $conn->real_escape_string($m) . "'",
  $months
));

/* -------------------------
   1) Budget per month
   - Excludes branches marked as "Point Close"
-------------------------- */
$budgetMap = array_fill_keys($months, 0.0);

$qB = $conn->query("
  SELECT month_applicable, COALESCE(SUM(no_of_shifts * rate), 0) AS budget_amount
  FROM tbl_admin_budget_security
  WHERE month_applicable IN ($inMonths)
    AND branch NOT LIKE '%Point Close%'
  GROUP BY month_applicable
");

if ($qB) {
  while ($r = $qB->fetch_assoc()) {
    $mm = trim((string)$r['month_applicable']);
    if (isset($budgetMap[$mm])) {
      $budgetMap[$mm] = (float)($r['budget_amount'] ?? 0);
    }
  }
}

/* -------------------------
   2) Actuals (NON-2000) per month
   - Only approved rows
   - Excludes branches that are active in the 2000 branch list
-------------------------- */
$actualNon2000Map = array_fill_keys($months, 0.0);

$qA1 = $conn->query("
  SELECT a.month_applicable, COALESCE(SUM(a.total_amount), 0) AS actual_amount
  FROM tbl_admin_actual_security_firmwise a
  LEFT JOIN tbl_admin_security_2000_branches s
    ON s.branch_code = a.branch_code
   AND s.active = 'yes'
  WHERE a.month_applicable IN ($inMonths)
    AND a.approval_status = 'approved'
    AND s.branch_code IS NULL
  GROUP BY a.month_applicable
");

if ($qA1) {
  while ($r = $qA1->fetch_assoc()) {
    $mm = trim((string)$r['month_applicable']);
    if (isset($actualNon2000Map[$mm])) {
      $actualNon2000Map[$mm] = (float)($r['actual_amount'] ?? 0);
    }
  }
}

/* -------------------------
   3) Actuals (2000 invoices) per month
   - Only approved rows
-------------------------- */
$actual2000Map = array_fill_keys($months, 0.0);

$qA2 = $conn->query("
  SELECT month_applicable, COALESCE(SUM(amount), 0) AS actual_amount
  FROM tbl_admin_actual_security_2000_invoices
  WHERE month_applicable IN ($inMonths)
    AND approval_status = 'approved'
  GROUP BY month_applicable
");

if ($qA2) {
  while ($r = $qA2->fetch_assoc()) {
    $mm = trim((string)$r['month_applicable']);
    if (isset($actual2000Map[$mm])) {
      $actual2000Map[$mm] = (float)($r['actual_amount'] ?? 0);
    }
  }
}

/* -------------------------
   4) Build chart series
   - actual = non-2000 actuals + 2000 invoices
   - If include_zero is not set, skip months with no actuals
   - Use null for 0 values so the chart won’t draw a flat zero line
-------------------------- */
$labels       = [];
$budgetSeries = [];
$actualSeries = [];

foreach ($months as $mm) {
  $budget = (float)($budgetMap[$mm] ?? 0);
  $actual = (float)($actualNon2000Map[$mm] ?? 0) + (float)($actual2000Map[$mm] ?? 0);

  if (!$includeZero && $actual <= 0) {
    continue;
  }

  $labels[]       = $mm;
  $budgetSeries[] = ($budget > 0) ? round($budget, 2) : null;
  $actualSeries[] = ($actual > 0) ? round($actual, 2) : null;
}

echo json_encode([
  'labels' => $labels,
  'budget' => $budgetSeries,
  'actual' => $actualSeries
]);
