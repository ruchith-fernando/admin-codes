<?php
// ajax-electricity-chart.php
require_once 'connections/connection.php';

// Absolutely disable caching of this JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

// ---- Classification sets (normalize with strtoupper + trim) ----
$BUNGALOW_CODES = array_map('strtoupper', ['2022','2017','2018','2019']);
$YARD_CODES     = array_map('strtoupper', ['2023','2023-1','2003','2004','2024','2007','2025','2001','2008','2012']);

function norm_code($code) {
    // Uppercase, trim spaces; keep hyphens as-is
    return strtoupper(trim((string)$code));
}
function classify_code($code, $bungalowSet, $yardSet) {
    $c = norm_code($code);
    if ($c !== '' && in_array($c, $bungalowSet, true)) return 'bungalow';
    if ($c !== '' && in_array($c, $yardSet, true))     return 'yard';
    return 'branch'; // default bucket
}

$labels         = [];
$monthly_budget = [];
$total_actual   = [];
$branch_total   = [];
$yard_total     = [];
$bungalow_total = [];

// --- Load budget months (any year range) ---
$q = "
  SELECT budget_month, amount
  FROM tbl_admin_electricity_monthly_budget
  ORDER BY STR_TO_DATE(budget_month, '%M %Y')
";
if ($res = $conn->query($q)) {
    while ($row = $res->fetch_assoc()) {
        $month = $row['budget_month'];
        if (!isset($monthly_budget[$month])) {
            $labels[] = $month; // preserve order
        }
        $monthly_budget[$month] = (float)$row['amount'];
        // initialize aggregates
        $total_actual[$month]   = 0.0;
        $branch_total[$month]   = 0.0;
        $yard_total[$month]     = 0.0;
        $bungalow_total[$month] = 0.0;
    }
}

// If there are no budget months, return empty series
if (empty($labels)) {
    echo json_encode([
        'labels'         => [],
        'budget'         => [],
        'total_actual'   => [],
        'branch_total'   => [],
        'yard_total'     => [],
        'bungalow_total' => [],
    ]);
    exit;
}

// --- Load actuals only for budgeted months ---
$q = "
  SELECT month_applicable, branch_code, REPLACE(total_amount, ',', '') AS total
  FROM tbl_admin_actual_electricity
  WHERE month_applicable IN (SELECT budget_month FROM tbl_admin_electricity_monthly_budget)
";
if ($res = $conn->query($q)) {
    while ($row = $res->fetch_assoc()) {
        $month  = $row['month_applicable'];
        if (!array_key_exists($month, $monthly_budget)) {
            // safety: skip months not present in budget labels
            continue;
        }

        $amount = (float)$row['total'];
        $total_actual[$month] += $amount;

        switch (classify_code($row['branch_code'], $BUNGALOW_CODES, $YARD_CODES)) {
            case 'bungalow':
                $bungalow_total[$month] += $amount;
                break;
            case 'yard':
                $yard_total[$month] += $amount;
                break;
            default:
                $branch_total[$month] += $amount;
        }
    }
}

// Map data in label order; leave true zeros as null for nicer gaps
function prepareData($labels, $dataMap) {
    $out = [];
    foreach ($labels as $label) {
        $v = round((float)($dataMap[$label] ?? 0));
        $out[] = ($v > 0) ? $v : null;
    }
    return $out;
}

// --- Trim trailing months after the last non-zero Actual ---
$lastActualIndex = null;
foreach ($labels as $i => $m) {
    if (!empty($total_actual[$m]) && (float)$total_actual[$m] > 0) {
        $lastActualIndex = $i;
    }
}
if ($lastActualIndex !== null) {
    $labels = array_slice($labels, 0, $lastActualIndex + 1);
}

// Final payload aligned to (possibly trimmed) labels
$payload = [
    'labels'         => array_values($labels),
    'budget'         => prepareData($labels, $monthly_budget),
    'total_actual'   => prepareData($labels, $total_actual),
    'branch_total'   => prepareData($labels, $branch_total),
    'yard_total'     => prepareData($labels, $yard_total),
    'bungalow_total' => prepareData($labels, $bungalow_total),
];

echo json_encode($payload);
