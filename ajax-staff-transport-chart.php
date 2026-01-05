<?php
// ajax-staff-transport-chart.php
require_once 'connections/connection.php';

// Absolutely disable caching of this JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

/*
  Data sources:
  - Budget: tbl_admin_budget_staff_transport (budget_month 'F Y', budget_amount)
  - Kangaroo: tbl_admin_kangaroo_transport (date DATE, total DECIMAL)
  - PickMe:   tbl_admin_pickme_data (pickup_time VARCHAR like "Wednesday, June 12th 2025, 4:12:22 PM", total_fare DECIMAL)
*/

$labels = [];              // ordered months (F Y)
$budget_amount   = [];     // map month => budget
$pickme_amount   = [];     // map month => pickme
$kangaroo_amount = [];     // map month => kangaroo
$total_amount    = [];     // map month => pickme + kangaroo

// 1) Labels from budget table (authoritative order)
$qBudget = "
  SELECT budget_month, budget_amount
  FROM tbl_admin_budget_staff_transport
  ORDER BY STR_TO_DATE(budget_month, '%M %Y')
";
if ($r = $conn->query($qBudget)) {
  while ($row = $r->fetch_assoc()) {
    $m = $row['budget_month'];
    $labels[] = $m;
    $budget_amount[$m]   = (float)$row['budget_amount'];
    $pickme_amount[$m]   = 0.0;
    $kangaroo_amount[$m] = 0.0;
    $total_amount[$m]    = 0.0;
  }
}

// 2) Actuals per month from Kangaroo
$qK = "
  SELECT DATE_FORMAT(`date`, '%M %Y') AS m, SUM(total) AS amt
  FROM tbl_admin_kangaroo_transport
  GROUP BY m
";
if ($rk = $conn->query($qK)) {
  while ($row = $rk->fetch_assoc()) {
    $m = $row['m'];
    if ($m && isset($kangaroo_amount[$m])) {
      $kangaroo_amount[$m] += (float)$row['amt'];
    }
  }
}

// 3) Actuals per month from PickMe (parse textual pickup_time)
$qP = "
  SELECT
    DATE_FORMAT(STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p'), '%M %Y') AS m,
    SUM(total_fare) AS amt
  FROM tbl_admin_pickme_data
  WHERE pickup_time IS NOT NULL AND pickup_time <> ''
  GROUP BY m
";
if ($rp = $conn->query($qP)) {
  while ($row = $rp->fetch_assoc()) {
    $m = $row['m'];
    if ($m && isset($pickme_amount[$m])) {
      $pickme_amount[$m] += (float)$row['amt'];
    }
  }
}

// 4) Calculate totals aligned to labels
foreach ($labels as $m) {
  $total_amount[$m] = ($pickme_amount[$m] ?? 0) + ($kangaroo_amount[$m] ?? 0);
}

// Helper to align series with (trimmed) labels and render zeros as gaps
function prepareData($labels, $map) {
  $out = [];
  foreach ($labels as $label) {
    $v = round($map[$label] ?? 0);
    $out[] = ($v > 0) ? $v : null; // show gaps for 0
  }
  return $out;
}

// 5) Trim trailing months AFTER the last non-zero Total Actual
$lastNonZeroIdx = null;
foreach ($labels as $i => $m) {
  if (!empty($total_amount[$m]) && (float)$total_amount[$m] > 0) {
    $lastNonZeroIdx = $i;
  }
}

if ($lastNonZeroIdx === null) {
  // No actuals at all → return empty payload
  echo json_encode([
    'labels'         => [],
    'budget_amount'  => [],
    'pickme_amount'  => [],
    'kangaroo_amount'=> [],
    'total_amount'   => [],
  ]);
  exit;
}

// Keep labels only up to (and including) last month with actuals
$labels = array_slice($labels, 0, $lastNonZeroIdx + 1);

// 6) Build payload aligned to trimmed labels
echo json_encode([
  'labels'          => array_values($labels),
  'budget_amount'   => prepareData($labels, $budget_amount),
  'pickme_amount'   => prepareData($labels, $pickme_amount),
  'kangaroo_amount' => prepareData($labels, $kangaroo_amount),
  'total_amount'    => prepareData($labels, $total_amount),
]);
