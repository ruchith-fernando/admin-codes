<?php
// ajax-tea-all-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

/* FY (Apr -> Mar) */
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

// month_key => first_date (for sorting)
$monthDate = [];

// series maps
$hoActual = [];
$brActual = [];
$hoBudget = [];
$brBudget = [];

/* -----------------------------
   1) HO ACTUALS (approved only)
------------------------------ */
$qHoA = $conn->query("
  SELECT month_year AS month_key,
         SUM(grand_total) AS amt,
         MIN(month_date)  AS first_date
  FROM tbl_admin_tea_service_hdr
  WHERE month_date >= '{$fyStartStr}'
    AND month_date <  '{$fyEndStr}'
    AND approval_status = 'approved'
    AND grand_total <> 0
  GROUP BY month_year
  HAVING SUM(grand_total) > 0
");

if ($qHoA) {
  while ($r = $qHoA->fetch_assoc()) {
    $mm = trim((string)$r['month_key']);
    $hoActual[$mm] = (float)($r['amt'] ?? 0);

    $d = (string)($r['first_date'] ?? '');
    if ($d !== '' && (!isset($monthDate[$mm]) || $d < $monthDate[$mm])) {
      $monthDate[$mm] = $d;
    }
  }
}

/* -----------------------------
   2) BRANCH ACTUALS (ABS debits)
------------------------------ */
$qBrA = $conn->query("
  SELECT applicable_month AS month_key,
         SUM(ABS(debits)) AS amt,
         MIN(dateoftran)  AS first_date
  FROM tbl_admin_actual_branch_gl_tea
  WHERE dateoftran >= '{$fyStartStr}'
    AND dateoftran <  '{$fyEndStr}'
    AND debits <> 0
    AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
  GROUP BY applicable_month
  HAVING SUM(ABS(debits)) > 0
");

if ($qBrA) {
  while ($r = $qBrA->fetch_assoc()) {
    $mm = trim((string)$r['month_key']);
    $brActual[$mm] = (float)($r['amt'] ?? 0);

    $d = (string)($r['first_date'] ?? '');
    if ($d !== '' && (!isset($monthDate[$mm]) || $d < $monthDate[$mm])) {
      $monthDate[$mm] = $d;
    }
  }
}

// No actuals at all = nothing to chart
if (!count($monthDate)) {
  echo json_encode([
    'labels'=>[],
    'ho_budget'=>[], 'ho_actual'=>[],
    'br_budget'=>[], 'br_actual'=>[]
  ]);
  exit;
}

/* -----------------------------
   3) Sort month keys by date
------------------------------ */
$labels = array_keys($monthDate);
usort($labels, function($a, $b) use ($monthDate){
  return strcmp($monthDate[$a] ?? '', $monthDate[$b] ?? '');
});

/* -----------------------------
   4) Budgets for those months only
------------------------------ */
$inMonths = implode(", ", array_map(
  fn($mm) => "'" . $conn->real_escape_string($mm) . "'",
  $labels
));

// HO budgets (month_year)
$qHoB = $conn->query("
  SELECT month_year AS month_key,
         SUM(budget_amount) AS amt
  FROM tbl_admin_budget_tea_service
  WHERE month_year IN ({$inMonths})
  GROUP BY month_year
");
if ($qHoB) {
  while ($r = $qHoB->fetch_assoc()) {
    $mm = trim((string)$r['month_key']);
    $hoBudget[$mm] = (float)($r['amt'] ?? 0);
  }
}

// Branch budgets (applicable_month)
$qBrB = $conn->query("
  SELECT applicable_month AS month_key,
         SUM(budget_amount) AS amt
  FROM tbl_admin_budget_tea_branch
  WHERE applicable_month IN ({$inMonths})
  GROUP BY applicable_month
");
if ($qBrB) {
  while ($r = $qBrB->fetch_assoc()) {
    $mm = trim((string)$r['month_key']);
    $brBudget[$mm] = (float)($r['amt'] ?? 0);
  }
}

/* -----------------------------
   5) Align to label order
------------------------------ */
function prep($labels, $map) {
  $out = [];
  foreach ($labels as $m) {
    $v = round((float)($map[$m] ?? 0), 2);
    $out[] = ($v > 0) ? $v : null;
  }
  return $out;
}

echo json_encode([
  'labels'    => $labels,
  'ho_budget' => prep($labels, $hoBudget),
  'ho_actual' => prep($labels, $hoActual),
  'br_budget' => prep($labels, $brBudget),
  'br_actual' => prep($labels, $brActual),
]);
