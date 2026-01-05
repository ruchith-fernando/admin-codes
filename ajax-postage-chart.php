<?php
// ajax-postage-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

/*
  This endpoint returns Postage "Budget vs Actual" for the current FY (Apr -> Mar).

  Important behavior notes:
  - The x-axis (labels) is driven by ACTUALS. If a month has no actuals, it won't appear.
  - Budget is then pulled only for those same months.
  - Values <= 0 are returned as null so the chart doesn't draw a flat zero line.
*/

/* -----------------------------
   Work out the current FY range
   FY is April 1st -> next April 1st (exclusive)
------------------------------ */
$now = new DateTimeImmutable('now');
$y   = (int)$now->format('Y');
$m   = (int)$now->format('n');

if ($m >= 4) {
  $fyStart   = new DateTimeImmutable("$y-04-01");
  $fyEndExcl = $fyStart->modify('+1 year');
} else {
  $fyStart   = new DateTimeImmutable(($y - 1) . '-04-01');
  $fyEndExcl = $fyStart->modify('+1 year');
}

$fyStartStr = $fyStart->format('Y-m-d');
$fyEndStr   = $fyEndExcl->format('Y-m-d'); // exclusive upper bound

$labels = [];
$actualByMonth = [];
$budgetByMonth = [];

/* -----------------------------------------
   1) ACTUALS
   We treat the months found here as the “official” list for the chart.
   (So if there are no actuals for a month, we simply don't show it.)
------------------------------------------ */
$qA = $conn->query("
  SELECT
    applicable_month AS month_year,
    SUM(ABS(debits)) AS actual_amount,
    MIN(dateoftran) AS first_date
  FROM tbl_admin_actual_branch_gl_postage
  WHERE dateoftran >= '{$fyStartStr}'
    AND dateoftran <  '{$fyEndStr}'
    AND debits <> 0
    AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
  GROUP BY applicable_month
  HAVING SUM(ABS(debits)) > 0
  ORDER BY first_date
");

if ($qA) {
  while ($r = $qA->fetch_assoc()) {
    $mm = trim((string)$r['month_year']);

    $labels[] = $mm;
    $actualByMonth[$mm] = (float)($r['actual_amount'] ?? 0);
  }
}

// No actuals in the FY = nothing to chart
if (!count($labels)) {
  echo json_encode(['labels' => [], 'budget' => [], 'actual' => []]);
  exit;
}

/* -----------------------------------------
   2) BUDGETS
   Now that we know which months are on the chart,
   pull budgets only for that same set.
------------------------------------------ */
$inMonths = implode(", ", array_map(
  fn($mm) => "'" . $conn->real_escape_string($mm) . "'",
  $labels
));

$qB = $conn->query("
  SELECT applicable_month, SUM(budget_amount) AS budget_amount
  FROM tbl_admin_budget_postage
  WHERE applicable_month IN ({$inMonths})
  GROUP BY applicable_month
");

if ($qB) {
  while ($r = $qB->fetch_assoc()) {
    $mm = trim((string)$r['applicable_month']);
    $budgetByMonth[$mm] = (float)($r['budget_amount'] ?? 0);
  }
}

/* -----------------------------------------
   3) Helper: align map values to the label order
   We return null for 0/empty values so the chart
   doesn't draw a misleading flat baseline.
------------------------------------------ */
function prep($labels, $map) {
  $out = [];
  foreach ($labels as $m) {
    $v = round((float)($map[$m] ?? 0), 2);
    $out[] = ($v > 0) ? $v : null;
  }
  return $out;
}

echo json_encode([
  'labels' => $labels,
  'budget' => prep($labels, $budgetByMonth),
  'actual' => prep($labels, $actualByMonth)
]);
