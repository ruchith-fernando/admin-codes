<?php
// ajax-tea-ho-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

/*
  Returns Tea (Head Office) Budget vs Actual for current FY (Apr -> Mar)

  Labels are driven by ACTUALS (same approach as your postage chart).
*/

/* FY range */
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
$fyEndStr   = $fyEndExcl->format('Y-m-d'); // exclusive

$labels = [];
$actualByMonth = [];
$budgetByMonth = [];

/* 1) ACTUALS (approved only) */
$qA = $conn->query("
  SELECT
    month_year,
    SUM(grand_total) AS actual_amount,
    MIN(month_date) AS first_date
  FROM tbl_admin_tea_service_hdr
  WHERE month_date >= '{$fyStartStr}'
    AND month_date <  '{$fyEndStr}'
    AND approval_status = 'approved'
    AND grand_total <> 0
  GROUP BY month_year
  HAVING SUM(grand_total) > 0
  ORDER BY first_date
");

if ($qA) {
  while ($r = $qA->fetch_assoc()) {
    $mm = trim((string)$r['month_year']);
    $labels[] = $mm;
    $actualByMonth[$mm] = (float)($r['actual_amount'] ?? 0);
  }
}

if (!count($labels)) {
  echo json_encode(['labels' => [], 'budget' => [], 'actual' => []]);
  exit;
}

/* 2) BUDGETS for same months */
$inMonths = implode(", ", array_map(
  fn($mm) => "'" . $conn->real_escape_string($mm) . "'",
  $labels
));

$qB = $conn->query("
  SELECT month_year, SUM(budget_amount) AS budget_amount
  FROM tbl_admin_budget_tea_service
  WHERE month_year IN ({$inMonths})
  GROUP BY month_year
");

if ($qB) {
  while ($r = $qB->fetch_assoc()) {
    $mm = trim((string)$r['month_year']);
    $budgetByMonth[$mm] = (float)($r['budget_amount'] ?? 0);
  }
}

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
