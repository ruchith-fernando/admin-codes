<?php
// ajax-photocopy-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

// If the DB connection fails, return a safe empty payload so the UI won't crash
if ($conn->connect_error) {
  echo json_encode([
    'labels'       => [],
    'budget'       => [],
    'total_actual' => [],
    'error'        => 'DB connection failed'
  ]);
  exit;
}

/*
  Supported query params:

  - start / end   : custom range in YYYY-MM (ex: start=2025-04&end=2025-12)
  - fy            : "2024" or "2024-2025" (Apr -> Mar)
  - fy_offset     : relative FY shift (0=current FY, -1=previous FY, etc.)
  - include_zero  : 1 to keep months even when actual is 0 (default: skip)
*/
$startParam    = $_GET['start'] ?? null;
$endParam      = $_GET['end']   ?? null;
$fyParam       = $_GET['fy']    ?? null;
$fyOffsetParam = isset($_GET['fy_offset']) ? intval($_GET['fy_offset']) : null;
$includeZero   = (isset($_GET['include_zero']) && $_GET['include_zero'] === '1');

// Fixed monthly budget (matches the existing report behavior)
$monthly_budget = 750000.00;

/**
 * FY is Apr -> Mar.
 * Returns the FY start year based on today's date (Asia/Colombo).
 */
function currentFyStartYear(): int {
  $y = (int)date('Y');
  $m = (int)date('n'); // 1..12
  return ($m >= 4) ? $y : ($y - 1);
}

/**
 * Converts YYYY-MM-01 into a chart label like "June 2025".
 * If parsing fails, we fall back to the raw input.
 */
function ymToLabel(string $ym): string {
  $dt = DateTime::createFromFormat('Y-m-d', $ym);
  return $dt ? $dt->format('F Y') : $ym;
}

/**
 * Returns a list of month-start dates between two month-starts (inclusive).
 * Example: 2025-04-01 .. 2025-06-01 => [04-01, 05-01, 06-01]
 */
function monthStartsBetween(string $startYm01, string $endYm01): array {
  $out = [];
  $cur = new DateTime($startYm01);
  $end = new DateTime($endYm01);

  while ($cur <= $end) {
    $out[] = $cur->format('Y-m-01');
    $cur->modify('+1 month');
  }
  return $out;
}

/* -------------------------------------------------------
   Resolve the month range (priority order):
   1) start/end (explicit month range)
   2) fy param
   3) fy_offset
   4) current FY fallback
-------------------------------------------------------- */
$startYm01 = null; // YYYY-MM-01
$endYm01   = null; // YYYY-MM-01

if ($startParam && $endParam) {
  // User picked an explicit range: normalize to month-start boundaries
  $startYm01 = date('Y-m-01', strtotime($startParam . '-01'));
  $endYm01   = date('Y-m-01', strtotime($endParam   . '-01'));

} elseif ($fyParam) {
  // FY can be "2024-2025" or just "2024"
  if (preg_match('/^\d{4}-\d{4}$/', $fyParam)) {
    [$y1, $y2] = array_map('intval', explode('-', $fyParam));

    // If the second year doesn't match the expected pattern, fix it silently
    if ($y2 !== $y1 + 1) $y2 = $y1 + 1;

    $startYm01 = sprintf('%04d-04-01', $y1);
    $endYm01   = sprintf('%04d-03-01', $y2);

  } elseif (preg_match('/^\d{4}$/', $fyParam)) {
    $y1 = (int)$fyParam;
    $y2 = $y1 + 1;

    $startYm01 = sprintf('%04d-04-01', $y1);
    $endYm01   = sprintf('%04d-03-01', $y2);

  } else {
    // If fy is invalid, fall back to current FY
    $y1 = currentFyStartYear();
    $y2 = $y1 + 1;

    $startYm01 = sprintf('%04d-04-01', $y1);
    $endYm01   = sprintf('%04d-03-01', $y2);
  }

} elseif ($fyOffsetParam !== null) {
  // Offset is useful for arrow buttons: -1 previous FY, +1 next FY, etc.
  $y1 = currentFyStartYear() + $fyOffsetParam;
  $y2 = $y1 + 1;

  $startYm01 = sprintf('%04d-04-01', $y1);
  $endYm01   = sprintf('%04d-03-01', $y2);

} else {
  // Default: current FY
  $y1 = currentFyStartYear();
  $y2 = $y1 + 1;

  $startYm01 = sprintf('%04d-04-01', $y1);
  $endYm01   = sprintf('%04d-03-01', $y2);
}

// Exclusive upper bound for SQL: first day of the month after endYm01
$endExcl = (new DateTime($endYm01))->modify('+1 month')->format('Y-m-01');

// Month list used for labels and for building aligned series
$monthStarts = monthStartsBetween($startYm01, $endYm01);

/* -------------------------------------------------------
   Fetch actuals grouped by month (single query)
   - We group by the month of month_applicable
   - We keep the query parameterized to avoid injection
-------------------------------------------------------- */
$actualByYm = []; // "YYYY-MM-01" => float

$sql = "
  SELECT DATE_FORMAT(month_applicable, '%Y-%m-01') AS ym,
         SUM(total_amount) AS actual
  FROM tbl_admin_actual_photocopy
  WHERE month_applicable >= ?
    AND month_applicable <  ?
  GROUP BY ym
  ORDER BY ym
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode([
    'labels'       => [],
    'budget'       => [],
    'total_actual' => [],
    'error'        => 'Prepare failed'
  ]);
  exit;
}

$stmt->bind_param('ss', $startYm01, $endExcl);
$stmt->execute();

$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $ym = (string)$row['ym'];
  $actualByYm[$ym] = (float)($row['actual'] ?? 0);
}
$stmt->close();

/* -------------------------------------------------------
   Build the response arrays
   Default behavior (include_zero=0):
   - show only months where actual > 0 (matches report behavior)
-------------------------------------------------------- */
$labels       = [];
$budgetSeries = [];
$actualSeries = [];

foreach ($monthStarts as $ym) {
  $actual = (float)($actualByYm[$ym] ?? 0);

  // Match report: skip empty months unless include_zero=1
  if (!$includeZero && $actual <= 0) continue;

  $labels[]       = ymToLabel($ym);         // "June 2025"
  $budgetSeries[] = (float)$monthly_budget; // fixed monthly budget line
  $actualSeries[] = $actual;                // actual spend for that month
}

echo json_encode([
  'labels'       => $labels,
  'budget'       => $budgetSeries,
  'total_actual' => $actualSeries
]);
