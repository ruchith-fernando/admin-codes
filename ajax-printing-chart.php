<?php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Fixed financial year months April → March
$months = [];
$start = strtotime("April 2025");
for ($i = 0; $i < 12; $i++) {
    $months[] = date("F Y", strtotime("+$i month", $start));
}

$labels = $months;
$budgetMap = [];
$actualMap = [];

// Get yearly budget (budget_year + amount)
$q = "SELECT budget_year, SUM(amount) AS amt 
      FROM tbl_admin_budget_printing
      GROUP BY budget_year";
$res = $conn->query($q);
$yearlyBudget = 0;
if ($res && $row = $res->fetch_assoc()) {
    $yearlyBudget = (float)$row['amt'];
}

// Split yearly into 12 months
$monthlyBudget = $yearlyBudget > 0 ? $yearlyBudget / 12 : 0;
foreach ($months as $m) {
    $budgetMap[$m] = $monthlyBudget;
    $actualMap[$m] = 0.0;
}

// Get actuals by month
$q = "SELECT month_applicable, REPLACE(total_amount, ',', '') AS amt
      FROM tbl_admin_actual_printing";
$res = $conn->query($q);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $m = $row['month_applicable'];
        if (isset($actualMap[$m])) {
            $actualMap[$m] += (float)$row['amt'];
        }
    }
}

// Helper
function prep($labels, $map){
    $out=[];
    foreach($labels as $m){ 
        $v=round((float)($map[$m]??0)); 
        $out[] = $v>0 ? $v : null; 
    }
    return $out;
}

// Trim trailing empty months
$lastIdx=null;
foreach($labels as $i=>$m){
    if (!empty($actualMap[$m]) && $actualMap[$m]>0) $lastIdx=$i;
}
if ($lastIdx!==null) $labels=array_slice($labels,0,$lastIdx+1);

echo json_encode([
    'labels'=>$labels,
    'budget'=>prep($labels,$budgetMap),
    'actual'=>prep($labels,$actualMap)
]);
