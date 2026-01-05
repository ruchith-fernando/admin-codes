<?php
// download-budget-security.php
require_once 'connections/connection.php';

// Force Excel download
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=budget_security_summary.xls");
header("Pragma: no-cache");
header("Expires: 0");

// -------------------------------
// 1. Build fiscal-year month list (April -> March, dynamic)
// -------------------------------
$startYear = (date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;

$months1 = []; // Apr -> Sep
$months2 = []; // Oct -> Mar

for ($i = 0; $i < 12; $i++) {
    $monthNum  = ($i + 4) % 12 ?: 12; // start from April
    $year      = ($i < 9) ? $startYear : $startYear + 1; // Apr-Dec in startYear, Jan-Mar next year
    $monthName = date('F', mktime(0, 0, 0, $monthNum, 1));

    if ($i < 6) $months1[] = "$monthName $year";
    else        $months2[] = "$monthName $year";
}
$allMonths = array_merge($months1, $months2);

// -------------------------------
// 2. Fetch BUDGET data
//    (sum per branch_code per month in FY)
// -------------------------------
$sqlBudget = "
    SELECT branch_code, branch, no_of_shifts, rate, month_applicable
    FROM tbl_admin_budget_security
";
$resultBudget = $conn->query($sqlBudget);
if (!$resultBudget) {
    echo "SQL Error: " . htmlspecialchars($conn->error);
    exit;
}

$dataBudget    = []; // branch_code => [branch, months[month]=>total]
$monthlyBudget = array_fill_keys($allMonths, 0.0);

// Monthly breakdown buckets (budget only)
$monthlyBreakdown = [];
foreach ($allMonths as $m) {
    $monthlyBreakdown[$m] = [
        'branches'   => 0.0, // 1–999 and >= 9000
        'yards'      => 0.0, // 2001–2013
        'police'     => 0.0, // 2014
        'additional' => 0.0, // 2015
        'radio'      => 0.0, // 2016
        'total'      => 0.0,
    ];
}

while ($row = $resultBudget->fetch_assoc()) {
    $code  = strtoupper(trim((string)$row['branch_code']));
    $month = trim((string)$row['month_applicable']);

    if (!in_array($month, $allMonths, true)) {
        continue; // only this FY
    }

    $total = (float)$row['no_of_shifts'] * (float)$row['rate'];

    // store per-branch totals
    if (!isset($dataBudget[$code])) {
        $dataBudget[$code] = [
            'branch_code' => $code,
            'branch'      => $row['branch'],
            'months'      => []
        ];
    }

    if (!isset($dataBudget[$code]['months'][$month])) {
        $dataBudget[$code]['months'][$month] = 0.0;
    }
    $dataBudget[$code]['months'][$month] += $total;

    // store month total
    $monthlyBudget[$month] += $total;

    // month bucket breakdown
    $num = is_numeric($code) ? (int)$code : 0;

    if (($num >= 1 && $num <= 999) || ($num >= 9000)) {
        $monthlyBreakdown[$month]['branches'] += $total;
    } elseif ($num >= 2001 && $num <= 2013) {
        $monthlyBreakdown[$month]['yards'] += $total;
    } elseif ($num === 2014) {
        $monthlyBreakdown[$month]['police'] += $total;
    } elseif ($num === 2015) {
        $monthlyBreakdown[$month]['additional'] += $total;
    } elseif ($num === 2016) {
        $monthlyBreakdown[$month]['radio'] += $total;
    }

    $monthlyBreakdown[$month]['total'] += $total;
}

// Sort by branch code
ksort($dataBudget, SORT_NATURAL);

// -------------------------------
// 3. Output main budget table
// -------------------------------
echo "<table border='1'>";
echo "<tr>";
echo "<th>Branch Code</th>";
echo "<th>Branch</th>";
foreach ($allMonths as $month) {
    echo "<th>" . htmlspecialchars($month) . "</th>";
}
echo "</tr>";

foreach ($dataBudget as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['branch_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['branch']) . "</td>";

    foreach ($allMonths as $m) {
        if (isset($row['months'][$m])) {
            echo "<td>" . number_format($row['months'][$m], 2) . "</td>";
        } else {
            echo "<td>-</td>";
        }
    }
    echo "</tr>";
}

// -------------------------------
// 4. Monthly Budget Breakdown row (NO actuals, NO variance)
// -------------------------------
echo "<tr>";
echo "<th colspan='2'>Budget Breakdown</th>";

foreach ($allMonths as $m) {
    $b = $monthlyBreakdown[$m];

    // Excel supports <br> inside HTML cell; user can Wrap Text
    $cell = "Total: " . number_format($b['total'], 2) . "<br>"
          . "Branches: " . number_format($b['branches'], 2) . "<br>"
          . "Y/B: " . number_format($b['yards'], 2) . "<br>"
          . "Police: " . number_format($b['police'], 2) . "<br>"
          . "Add: " . number_format($b['additional'], 2) . "<br>"
          . "Radio: " . number_format($b['radio'], 2);

    echo "<th>$cell</th>";
}
echo "</tr>";
echo "</table>";

// -------------------------------
// 5. Overall Summary (Budget ONLY)
// -------------------------------
$summaryBudget = [
    'Branches'             => 0.0,
    'Yard and Bungalow'    => 0.0,
    'Police'               => 0.0,
    'Radio Transmission'   => 0.0,
    'Additional Security'  => 0.0,
];

foreach ($dataBudget as $branch) {
    $codeStr = $branch['branch_code'];
    $num     = is_numeric($codeStr) ? (int)$codeStr : 0;
    $total   = array_sum($branch['months']);

    if (($num >= 1 && $num <= 999) || ($num >= 9000)) {
        $summaryBudget['Branches'] += $total;
    } elseif ($num >= 2001 && $num <= 2013) {
        $summaryBudget['Yard and Bungalow'] += $total;
    } elseif ($num === 2014) {
        $summaryBudget['Police'] += $total;
    } elseif ($num === 2016) {
        $summaryBudget['Radio Transmission'] += $total;
    } elseif ($num === 2015) {
        $summaryBudget['Additional Security'] += $total;
    }
}

$grandBudget = array_sum($summaryBudget);

echo "<br><br><strong>Summary (Budget Only):</strong><br>";
foreach ($summaryBudget as $label => $budVal) {
    echo htmlspecialchars($label) . ": Budget " . number_format($budVal, 2) . "<br>";
}

echo "<br><strong>Grand Total (Budget):</strong> " . number_format($grandBudget, 2) . "<br>";

exit;
