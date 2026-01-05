<?php
// ajax-tea-branches-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

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
$fyEndStr   = $fyEndExcl->format('Y-m-d'); // exclusive upper bound

$labels = [];
$actualByMonth = [];
$budgetByMonth = [];

/* 1) ACTUALS (labels driven by actuals) — ONLY DEBITS (tran_db_cr_flg = 'D') */
$qA = $conn->query("
  SELECT
    applicable_month AS month_key,
    SUM(debits) AS actual_amount,
    MIN(dateoftran) AS first_date
  FROM tbl_admin_actual_branch_gl_tea
  WHERE dateoftran >= '{$fyStartStr}'
    AND dateoftran <  '{$fyEndStr}'
    AND debits <> 0
    AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
  GROUP BY applicable_month
  HAVING SUM(debits) > 0
  ORDER BY first_date
");

if ($qA) {
  while ($r = $qA->fetch_assoc()) {
    $mm = trim((string)$r['month_key']);
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
  SELECT applicable_month, SUM(budget_amount) AS budget_amount
  FROM tbl_admin_budget_tea_branch
  WHERE applicable_month IN ({$inMonths})
  GROUP BY applicable_month
");

if ($qB) {
  while ($r = $qB->fetch_assoc()) {
    $mm = trim((string)$r['applicable_month']);
    $budgetByMonth[$mm] = (float)($r['budget_amount'] ?? 0);
  }
}

/* align arrays to label order */
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
