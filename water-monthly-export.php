<?php
// water-monthly-export.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$month = trim($_GET['month'] ?? '');
if ($month === '') {
    http_response_code(400);
    echo "No month selected";
    exit;
}

$monthEsc = mysqli_real_escape_string($conn, $month);

// ✅ SAME AS FETCH: Financial Year budget_year = FY start year (Apr→Mar)
$ts = strtotime("1 " . $month);
$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$budget_year = ($mn < 4) ? ($y - 1) : $y;

function money_plain($n) {
    return number_format((float)$n, 2, '.', '');
}
function variance_plain($budget, $actual) {
    $budget = (float)$budget;
    $actual = (float)$actual;

    if ($actual > $budget) {
        return "(" . number_format($actual - $budget, 2, '.', '') . ")";
    }
    return number_format($budget - $actual, 2, '.', '');
}

/* ======================================================
   1) MASTER BRANCH + REQUIRED CONNECTIONS (ACTIVE TYPES ONLY)
   ✅ same as fetch (build required_keys + key_to_label)
====================================================== */
$master = [];

$map_sql = "
    SELECT
        bw.branch_code,
        bw.branch_name,
        bw.water_type_id,
        bw.connection_no,
        wt.water_type_name
    FROM tbl_admin_branch_water bw
    INNER JOIN tbl_admin_water_types wt
        ON wt.water_type_id = bw.water_type_id
    WHERE wt.is_active = 1
    ORDER BY bw.branch_code, wt.water_type_name, bw.connection_no
";
$map_res = mysqli_query($conn, $map_sql);

if ($map_res) {
    while ($r = mysqli_fetch_assoc($map_res)) {
        $code = $r['branch_code'];

        if (!isset($master[$code])) {
            $master[$code] = [
                'branch_name'   => $r['branch_name'] ?? '',
                'required_keys' => [],
                'key_to_label'  => [],
            ];
        }

        $typeId = (int)$r['water_type_id'];
        $connNo = (int)($r['connection_no'] ?? 1);
        if ($connNo <= 0) $connNo = 1;

        $key   = $typeId . '|' . $connNo;
        $label = ($r['water_type_name'] ?? 'Type') . " (Conn " . $connNo . ")";

        $master[$code]['required_keys'][] = $key;
        $master[$code]['key_to_label'][$key] = $label;
    }

    // ✅ same as fetch: unique required_keys per branch
    foreach ($master as $code => $m) {
        $master[$code]['required_keys'] = array_values(array_unique($master[$code]['required_keys']));
    }
}

/* ======================================================
   2) BUDGET DATA (per branch)
   ✅ MUST MATCH FETCH EXACTLY (no SUM, no GROUP BY)
====================================================== */
$budget = [];
$budget_sql = "
    SELECT branch_code, amount AS monthly_amount
    FROM tbl_admin_budget_water
    WHERE budget_year = '" . mysqli_real_escape_string($conn, (string)$budget_year) . "'
";
$budget_res = mysqli_query($conn, $budget_sql);
if ($budget_res) {
    while ($b = mysqli_fetch_assoc($budget_res)) {
        $budget[$b['branch_code']] = (float)($b['monthly_amount'] ?? 0);
    }
}

/* ======================================================
   3) ACTUAL DATA FOR THE MONTH (ALL STATUSES)
====================================================== */
$actualMap = []; // actualMap[branch][type|conn] = ['status','amount','is_provision']

$actual_sql = "
    SELECT branch_code, water_type_id, connection_no, approval_status, total_amount, is_provision
    FROM tbl_admin_actual_water
    WHERE month_applicable = '{$monthEsc}'
";
$actual_res = mysqli_query($conn, $actual_sql);

if ($actual_res) {
    while ($a = mysqli_fetch_assoc($actual_res)) {
        $code = $a['branch_code'];
        $tid  = (int)($a['water_type_id'] ?? 0);
        $cno  = (int)($a['connection_no'] ?? 1);
        if ($cno <= 0) $cno = 1;

        $key = $tid . '|' . $cno;

        $status = strtolower(trim($a['approval_status'] ?? ''));
        $rawAmt = trim((string)($a['total_amount'] ?? ''));

        $amtNum = null;
        if ($rawAmt !== '') {
            $clean = str_replace(',', '', $rawAmt);
            if (is_numeric($clean)) $amtNum = (float)$clean;
        }

        if (!isset($actualMap[$code])) $actualMap[$code] = [];
        $actualMap[$code][$key] = [
            'status'       => $status,
            'amount'       => $amtNum,
            'is_provision' => strtolower(trim($a['is_provision'] ?? 'no')),
        ];
    }
}

/* ======================================================
   OUTPUT CSV
====================================================== */
$filename = 'water_report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $month) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

$fh = fopen('php://output', 'w');

// Header row (matches fetch table)
fputcsv($fh, [
    'Branch Code',
    'Branch Name',
    'Budget (Monthly)',
    'Branch Breakdown',
    'Actual',
    'Variance',
    'Water Connection(s)',
    'Breakdown',
    'Remarks'
]);

$total_budget_all = 0.0;
$total_actual_all = 0.0;

uksort($master, 'strnatcmp');

foreach ($master as $code => $mdata) {

    $branch_name = $mdata['branch_name'] ?? '';
    $required    = $mdata['required_keys'] ?? [];
    $keyToLabel  = $mdata['key_to_label']  ?? [];

    $reqCount = count($required);
    if ($reqCount <= 0) continue;

    $b_amt = (float)($budget[$code] ?? 0);

    // ✅ same as fetch: budget=0 => do not show
    if ($b_amt <= 0) continue;

    $total_budget_all += $b_amt;

    $enteredCount = 0;
    $pendingCount = 0;
    $provLabels   = [];
    $actualTotal  = 0.0; // ✅ APPROVED ONLY

    // ✅ Build breakRows EXACTLY like fetch (approved-only amounts)
    $breakRows = [];
    foreach ($required as $k) {
        $label = $keyToLabel[$k] ?? $k;

        $row = $actualMap[$code][$k] ?? null;
        $status = $row ? strtolower(trim($row['status'] ?? '')) : '';

        $isMissing = (!$row || in_array($status, ['rejected','deleted'], true));

        $amt = null;
        if (!$isMissing) {
            $enteredCount++;
            if ($status === 'pending') $pendingCount++;

            if (($row['is_provision'] ?? 'no') === 'yes') {
                $provLabels[] = $label;
            }

            // ✅ ONLY APPROVED sum/show
            if ($status === 'approved' && $row['amount'] !== null && $row['amount'] > 0) {
                $amt = (float)$row['amount'];
                $actualTotal += $amt;
            }
        }

        $breakRows[] = [
            'label'  => $label,
            'status' => $isMissing ? 'missing' : $status,
            'amount' => $amt
        ];
    }

    $missingCount = max(0, $reqCount - $enteredCount);
    $total_actual_all += $actualTotal;

    // remarks EXACT like fetch
    $remarks = [];
    $remarks[] = "No. of Connections: {$reqCount}";
    if ($pendingCount > 0) $remarks[] = "Pending for Approval - {$pendingCount}";
    $remarks[] = "Entered Connections - {$enteredCount}";
    if ($missingCount > 0) $remarks[] = "Missing Connections - {$missingCount}";

    $provLabels = array_values(array_unique($provLabels));
    if (!empty($provLabels)) {
        $remarks[] = "Provision - " . implode(", ", $provLabels);
    }
    $remarksCell = implode("\n", $remarks);

    // ✅ CSV layout to match fetch "rowspan":
    // First row shows branch summary columns, next rows keep them blank.
    for ($i = 0; $i < count($breakRows); $i++) {
        $br = $breakRows[$i];

        $isFirst = ($i === 0);

        $branchCodeCell = $isFirst ? $code : '';
        $branchNameCell = $isFirst ? $branch_name : '';
        $budgetCell     = $isFirst ? money_plain($b_amt) : '';
        $actualCell     = $isFirst ? money_plain($actualTotal) : '';
        $varianceCell   = $isFirst ? variance_plain($b_amt, $actualTotal) : '';
        $remarksOut     = $isFirst ? $remarksCell : '';

        // ✅ breakdown amount: approved only, else "-"
        $amtCell = ($br['amount'] !== null) ? money_plain($br['amount']) : '-';

        // Branch Breakdown column in fetch is shown every line (not rowspanned)
        $branchBreakdownCell = $branch_name;

        fputcsv($fh, [
            $branchCodeCell,
            $branchNameCell,
            $budgetCell,
            $branchBreakdownCell,
            $actualCell,
            $varianceCell,
            $br['label'],
            $amtCell,
            $remarksOut
        ]);
    }
}

// totals row (matches fetch totals)
fputcsv($fh, [
    'Total',
    '',
    money_plain($total_budget_all),
    '',
    money_plain($total_actual_all),
    variance_plain($total_budget_all, $total_actual_all),
    '',
    '',
    ''
]);

userlog("⬇️ Water CSV Download | Month: {$month} | User: " . ($_SESSION['name'] ?? 'Unknown'));
exit;
