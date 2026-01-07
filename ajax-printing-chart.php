<?php
// ajax-printing-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* -------------------------------------------------
   FY months (Apr 2025 → Mar 2026)
-------------------------------------------------- */
$months = [];
$start = new DateTime('2025-04-01');
$end   = new DateTime('2026-03-01');
while ($start <= $end) {
    $months[] = $start->format('F Y');
    $start->modify('+1 month');
}

$labels    = $months;
$budgetMap = [];
$actualMap = [];

/* -------------------------------------------------
   1) Budget totals by month
   budget_year column contains month text (ex: "April 2025")
-------------------------------------------------- */
$budgetRes = $conn->query("
    SELECT budget_year, SUM(amount) AS amt
    FROM tbl_admin_budget_printing
    WHERE budget_year IS NOT NULL AND TRIM(budget_year) <> ''
    GROUP BY budget_year
");

if ($budgetRes) {
    while ($r = $budgetRes->fetch_assoc()) {
        $m = trim((string)$r['budget_year']);
        $budgetMap[$m] = (float)($r['amt'] ?? 0);
    }
}

/* -------------------------------------------------
   2) Actual totals by month
-------------------------------------------------- */
$actualRes = $conn->query("
    SELECT month_applicable,
           SUM(CAST(REPLACE(total_amount, ',', '') AS DECIMAL(15,2))) AS amt
    FROM tbl_admin_actual_printing
    WHERE month_applicable IS NOT NULL AND TRIM(month_applicable) <> ''
      AND TRIM(total_amount) <> ''
    GROUP BY month_applicable
");

if ($actualRes) {
    while ($r = $actualRes->fetch_assoc()) {
        $m = trim((string)$r['month_applicable']);
        $actualMap[$m] = (float)($r['amt'] ?? 0);
    }
}

/* -------------------------------------------------
   Fill missing months with 0
-------------------------------------------------- */
foreach ($labels as $m) {
    if (!isset($budgetMap[$m])) $budgetMap[$m] = 0.0;
    if (!isset($actualMap[$m])) $actualMap[$m] = 0.0;
}

/* -------------------------------------------------
   Helper: chart arrays (use null for empty)
   - keeps chart clean (no zero dots)
-------------------------------------------------- */
function prepSeries($labels, $map) {
    $out = [];
    foreach ($labels as $m) {
        $v = round((float)($map[$m] ?? 0));
        $out[] = ($v > 0) ? $v : null;
    }
    return $out;
}

/* -------------------------------------------------
   Trim trailing months with no actuals
-------------------------------------------------- */
$lastIdx = null;
foreach ($labels as $i => $m) {
    if (!empty($actualMap[$m]) && $actualMap[$m] > 0) $lastIdx = $i;
}

if ($lastIdx !== null) {
    $labels = array_slice($labels, 0, $lastIdx + 1);
} else {
    // no actuals at all - keep first month only (optional)
    $labels = array_slice($labels, 0, 1);
}

echo json_encode([
    'labels' => $labels,
    'budget' => prepSeries($labels, $budgetMap),
    'actual' => prepSeries($labels, $actualMap),
]);
exit;
