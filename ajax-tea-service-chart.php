<?php
// ajax-tea-service-chart.php
require_once 'connections/connection.php';

// Absolutely disable caching of this JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

$labels = [];
$budget_amount = [];
$actual_amount = [];

/* 1) Labels from budget table (authoritative order) */
$qBudget = "
  SELECT month_year, budget_amount
  FROM tbl_admin_budget_tea_service
  ORDER BY STR_TO_DATE(month_year, '%M %Y')
";
if ($r = $conn->query($qBudget)) {
  while ($row = $r->fetch_assoc()) {
      $month = $row['month_year'];
      $labels[] = $month;
      $budget_amount[$month] = (float)$row['budget_amount'];
      $actual_amount[$month] = 0.0; // default
  }
}

/* 2) Actuals per month */
$qActual = "
  SELECT month_year, SUM(grand_total) AS total_actual
  FROM tbl_admin_tea_service
  WHERE month_year IN (SELECT month_year FROM tbl_admin_budget_tea_service)
  GROUP BY month_year
";
if ($r2 = $conn->query($qActual)) {
  while ($row = $r2->fetch_assoc()) {
      $month = $row['month_year'];
      $actual_amount[$month] = (float)$row['total_actual'];
  }
}

/* Helper to align series with (trimmed) labels and render zeros as gaps */
function prepareData($labels, $map) {
  $out = [];
  foreach ($labels as $label) {
    $v = round($map[$label] ?? 0);
    $out[] = ($v > 0) ? $v : null;
  }
  return $out;
}

/* 3) Trim trailing months AFTER the last non-zero Actual */
$lastNonZeroIdx = null;
foreach ($labels as $i => $m) {
  if (!empty($actual_amount[$m]) && (float)$actual_amount[$m] > 0) {
    $lastNonZeroIdx = $i;
  }
}

if ($lastNonZeroIdx === null) {
  // No actuals at all → return empty payload
  echo json_encode([
    'labels'        => [],
    'budget_amount' => [],
    'actual_amount' => [],
  ]);
  exit;
}

// Keep labels only up to (and including) last month with actuals
$labels = array_slice($labels, 0, $lastNonZeroIdx + 1);

/* 4) Build payload aligned to trimmed labels */
echo json_encode([
  'labels'        => array_values($labels),
  'budget_amount' => prepareData($labels, $budget_amount),
  'actual_amount' => prepareData($labels, $actual_amount),
]);
