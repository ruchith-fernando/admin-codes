<?php
require_once 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Build months from April 2025 → March 2026
$months = [];
$start = strtotime("April 2025");
for ($i = 0; $i < 12; $i++) {
    $months[] = date("F Y", strtotime("+$i month", $start));
}

$labels = $months;
$budgetMap = [];
$actualMap = [];

/* --- Load Budgets --- */
$q = "SELECT month_name, amount 
      FROM tbl_admin_budget_security_vpn
      WHERE amount IS NOT NULL";
$res = $conn->query($q);
if (!$res) {
    echo json_encode(['error' => $conn->error, 'query' => $q]);
    exit;
}
while ($row = $res->fetch_assoc()) {
    $budgetMap[trim($row['month_name'])] = (float)$row['amount'];
}

/* --- Load Actuals --- */
$q = "SELECT month_name, total_amount 
      FROM tbl_admin_actual_security_vpn
      WHERE total_amount IS NOT NULL AND total_amount <> 0";
$res = $conn->query($q);
if (!$res) {
    echo json_encode(['error' => $conn->error, 'query' => $q]);
    exit;
}
while ($row = $res->fetch_assoc()) {
    $actualMap[trim($row['month_name'])] = (float)$row['total_amount'];
}

/* --- Helper --- */
function prep($labels, $map) {
    $out = [];
    foreach ($labels as $m) {
        $v = round((float)($map[$m] ?? 0), 2);
        $out[] = $v > 0 ? $v : null;
    }
    return $out;
}

/* --- Trim trailing months with no data --- */
$lastIdx = null;
foreach ($labels as $i => $m) {
    if (!empty($actualMap[$m]) && $actualMap[$m] > 0) $lastIdx = $i;
}
if ($lastIdx !== null) $labels = array_slice($labels, 0, $lastIdx + 1);

echo json_encode([
    'labels' => $labels,
    'budget' => prep($labels, $budgetMap),
    'actual' => prep($labels, $actualMap)
]);
