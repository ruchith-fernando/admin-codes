<?php
// dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'connections/connection.php';

function getCurrentFinancialYearMonths() {
    $currentMonth = (int)date('n');
    $currentYear  = (int)date('Y');
    $startYear    = ($currentMonth < 4) ? $currentYear - 1 : $currentYear;
    $start        = strtotime("$startYear-04-01");
    $end          = strtotime(($startYear + 1) . "-03-01");

    $months = [];
    while ($start <= $end) {
        $months[] = date('F Y', $start);
        $start = strtotime("+1 month", $start);
    }
    return $months;
}

function month_bounds($label){
    $s = DateTime::createFromFormat('F Y', $label);
    if(!$s) return [null,null,null,null];
    $s->modify('first day of this month'); $e=clone $s; $e->modify('last day of this month');
    return [$s->format('Y-m-d'), $e->format('Y-m-d'), $s->format('Y-m'), $s->format('Y-m-01')];
}

/* Dialog part (mobile bills) – same logic as telephone page */
function dialog_actual(mysqli $conn, array $cfg, string $month_label, string $ym, string $ymd01): float {
    $tbl=$cfg['table']; $pc=$cfg['period_col']; $ac=$cfg['amount_col']; $nc=$cfg['number_col'];
    $special = $cfg['special_include']; $exclude = $cfg['exclude_numbers'];
    $m_dash = str_replace(' ', '-', $month_label);
    $sum = 0.0;

    // base (exclude specials & hard excludes)
    $exPlace = $exclude ? implode(',', array_fill(0,count($exclude),'?')) : '';
    $condEx  = $exclude ? "AND $nc NOT IN ($exPlace)" : '';
    $spPlace = implode(',', array_fill(0, max(1,count($special)), '?'));
    $sql1 = "SELECT SUM($ac) s FROM $tbl WHERE ($pc=? OR $pc=?) $condEx AND $nc NOT IN ($spPlace)";
    $st1 = $conn->prepare($sql1);
    $params = array_merge([$m_dash, $ymd01], $exclude, ($special ?: ['__none__']));
    $types  = str_repeat('s', count($params));
    $st1->bind_param($types, ...$params);
    $st1->execute(); $r1=$st1->get_result()->fetch_assoc(); $st1->close();
    $sum += (float)($r1['s'] ?? 0);

    // add back specials
    if ($special) {
        $sql2 = "SELECT SUM($ac) s FROM $tbl WHERE ($pc=? OR $pc=?) AND $nc IN (".implode(',', array_fill(0,count($special),'?')).")";
        $st2 = $conn->prepare($sql2);
        $st2->bind_param(str_repeat('s', 2+count($special)), ...array_merge([$m_dash,$ymd01], $special));
        $st2->execute(); $r2=$st2->get_result()->fetch_assoc(); $st2->close();
        $sum += (float)($r2['s'] ?? 0);
    }

    // include negatives explicitly
    $sql3 = "SELECT SUM($ac) s FROM $tbl WHERE ($pc=? OR $pc=?) AND $ac < 0";
    $st3 = $conn->prepare($sql3);
    $st3->bind_param('ss', $m_dash, $ymd01);
    $st3->execute(); $r3=$st3->get_result()->fetch_assoc(); $st3->close();
    $sum += (float)($r3['s'] ?? 0);

    return $sum;
}

/* Ratio-based part for CDMA/SLT – same as telephone page */
function ratio_actual(mysqli $conn, string $monthly, string $charges, string $conns, array $col, string $start, string $end): float {
    $bs=$col['bill_start']; $be=$col['bill_end']; $u=$col['upload_id']; $s=$col['subtotal']; $t=$col['tax_total']; $m='m';
    $sql = "
      WITH base AS (
        SELECT c.$u upload_id, SUM(c.$s) conn_total
        FROM $conns c
        JOIN $monthly $m ON $m.id = c.$u
        WHERE $m.$bs <= ? AND $m.$be >= ?
        GROUP BY c.$u
      ),
      ratio AS (
        SELECT b.upload_id, b.conn_total, COALESCE(ch.$t,0) tax_total
        FROM base b
        LEFT JOIN $charges ch ON ch.$u = b.upload_id
      )
      SELECT SUM(c.$s * (1 + COALESCE(r.tax_total / NULLIF(r.conn_total,0),0))) AS total
      FROM $conns c
      JOIN $monthly $m ON $m.id = c.$u
      LEFT JOIN ratio r ON r.upload_id = c.$u
      WHERE $m.$bs <= ? AND $m.$be >= ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param('ssss', $end, $start, $end, $start);
    $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
    return (float)($row['total'] ?? 0.0);
}

/* --------------------------- Data prep --------------------------- */

$all_months = getCurrentFinancialYearMonths();

/* Selected months per category (per user) */
$user_id = $_SESSION['hris'];
$selected_months_by_category = [];
$res = mysqli_query($conn, "
    SELECT category, month_name 
    FROM tbl_admin_dashboard_month_selection 
    WHERE is_selected='yes' AND user_id = '".mysqli_real_escape_string($conn, $user_id)."'
");
while ($row = mysqli_fetch_assoc($res)) {
    $selected_months_by_category[$row['category']][] = $row['month_name'];
}

/* Budget (full year) */
$budget_tables = [
    'Security Charges'             => ['table' => 'tbl_admin_budget_security',              'column' => 'month_applicable', 'calc' => 'no_of_shifts * rate'],
    'Tea Service - Head Office'    => ['table' => 'tbl_admin_budget_tea_service',          'column' => 'month_year',       'calc' => 'budget_amount'],
    'Printing & Stationary'        => ['table' => 'tbl_admin_budget_printing',           'column' => 'budget_year',            'calc' => 'amount'], 
    'Electricity Charges'          => ['table' => 'tbl_admin_budget_electricity',          'column' => 'budget_year',      'calc' => 'amount'], // handled specially
    'Photocopy'                    => ['table' => 'tbl_admin_budget_photocopies',          'column' => 'month_year',       'calc' => 'budget_amount'],
    'Courier'                      => ['table' => 'tbl_admin_budget_courier',              'column' => 'budget_year',     'calc' => 'amount'],
    'Vehicle Maintenance'          => ['table' => 'tbl_admin_budget_vehicle_maintenance',  'column' => 'budget_month',     'calc' => 'amount'],
    'Postage & Stamps' => ['table' => 'tbl_admin_budget_postage_stamps',       'column' => 'month_year',       'calc' => 'budget_amount'],
    'Staff Transport' => ['table' => 'tbl_admin_budget_staff_transport',      'column' => 'budget_month',     'calc' => 'budget_amount'],
    'Telephone Bills' => ['table' => 'tbl_admin_budget_telephone',            'column' => 'budget_month',     'calc' => 'budget_amount'],
    'News Paper' => ['table' => 'tbl_admin_budget_newspaper',            'column' => 'budget_year',       'calc' => 'amount'],
    'Water' => ['table' => 'tbl_admin_budget_water', 'column' => 'budget_year', 'calc' => 'amount'],
    'Tea Branches' => ['table' => 'tbl_admin_budget_tea_branches', 'column' => 'budget_year', 'calc' => 'amount'],
    'Security VPN' => ['table' => 'tbl_admin_budget_security_vpn', 'column' => 'budget_year', 'calc' => 'amount'],
];

$category_links = [
    'Security Charges'           => 'security-cost-report.php',
    'Tea Service - Head Office'  => 'tea-budget-vs-actual.php',
    'Printing & Stationary'      => 'printing-overview.php',
    'Electricity Charges'        => 'electricity-overview.php',
    'Photocopy'                  => 'photocopy-overview.php',
    'Courier'                    => 'courier-overview.php',
    'Vehicle Maintenance'        => 'vehicle-budget-vs-actual.php',
    'Postage & Stamps'           => 'postage-budget-vs-actual.php',
    'Telephone Bills'            => 'telephone-budget-vs-actual.php',
    'News Paper' => 'newspaper-overview.php',
    'Water' => 'water-overview.php',
    'Tea Branches' => 'tea-branches-overview.php',
    'Security VPN' => 'security-vpn-overview.php',
];

$budgets = [];
$monthly_budget_breakdown = [];

$currentFY = ((int)date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;
foreach ($budget_tables as $category => $info) {
    if ($category === 'Electricity Charges') {
        $budgets[$category] = 0;

        $fyEsc = mysqli_real_escape_string($conn, (string)$currentFY);
        $row   = $conn->query("
            SELECT SUM(amount) AS monthly_total
            FROM tbl_admin_budget_electricity
            WHERE budget_year = '{$fyEsc}'
        ")->fetch_assoc();

        $monthly_total = (float)($row['monthly_total'] ?? 0);

        $budgets[$category] = $monthly_total * 12;

        foreach ($all_months as $mlbl) {
            $monthly_budget_breakdown[$category][$mlbl] =
                ($monthly_budget_breakdown[$category][$mlbl] ?? 0) + $monthly_total;
        }

        continue;
    }

    $table  = $info['table'];
    $column = $info['column'];
    $calc   = $info['calc'];

    $budgets[$category] = 0;
    $res = mysqli_query($conn, "SELECT `$column` AS month, $calc AS amount FROM $table");
    while ($row = mysqli_fetch_assoc($res)) {
        $month  = $row['month'];
        $amount = (float)$row['amount'];
        $budgets[$category] += $amount;
        $monthly_budget_breakdown[$category][$month] =
            ($monthly_budget_breakdown[$category][$month] ?? 0) + $amount;
    }
}

/* --------------------------- Actuals --------------------------- */
$monthly_actual_breakdown = [];
$actuals = [];

/* Security Charges */
$actuals['Security Charges'] = 0;
$monthly_actual_breakdown['Security Charges'] = [];

foreach ($all_months as $mlbl) {
    $month_esc = mysqli_real_escape_string($conn, $mlbl);

    // NON-2000 (approved) excluding active 2000 branches
    $row1 = $conn->query("SELECT COALESCE(SUM(a.total_amount),0) AS s
        FROM tbl_admin_actual_security_firmwise a
        LEFT JOIN tbl_admin_security_2000_branches s
               ON s.branch_code = a.branch_code
              AND s.active = 'yes'
        WHERE a.month_applicable = '{$month_esc}'
          AND a.approval_status = 'approved'
          AND s.branch_code IS NULL")->fetch_assoc();
    $actual_non2000 = (float)($row1['s'] ?? 0);

    // 2000 invoices (approved)
    $row2 = $conn->query("SELECT COALESCE(SUM(i.amount),0) AS s
        FROM tbl_admin_actual_security_2000_invoices i
        WHERE i.month_applicable = '{$month_esc}'
          AND i.approval_status = 'approved'")->fetch_assoc();
    $actual_2000 = (float)($row2['s'] ?? 0);

    $actual = $actual_non2000 + $actual_2000;

    if ($actual > 0) {
        $monthly_actual_breakdown['Security Charges'][$mlbl] =
            ($monthly_actual_breakdown['Security Charges'][$mlbl] ?? 0) + $actual;
        $actuals['Security Charges'] += $actual;
    }
}


/* Electricity Need to complete first */
$actuals['Electricity Charges'] = 0;
$res = mysqli_query($conn, "
  SELECT month_applicable AS month,
         SUM(CAST(REPLACE(TRIM(total_amount), ',', '') AS DECIMAL(15,2))) AS total_amount
  FROM tbl_admin_actual_electricity
  GROUP BY month_applicable
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];
    $monthly_actual_breakdown['Electricity Charges'][$month] =
        ($monthly_actual_breakdown['Electricity Charges'][$month] ?? 0) + $amount;
    $actuals['Electricity Charges'] += $amount;
}

$actuals['Photocopy'] = 0;
$monthly_actual_breakdown['Photocopy'] = [];

foreach ($all_months as $mlbl) {
    // reuse your helper
    [$start,$end,$ym,$ymd01] = month_bounds($mlbl);
    if (!$start) continue;

    // next month start (exclusive upper bound)
    $dt = DateTime::createFromFormat('Y-m-d', $ymd01);
    $next = (clone $dt)->modify('+1 month')->format('Y-m-01');

    $startEsc = mysqli_real_escape_string($conn, $ymd01);
    $nextEsc  = mysqli_real_escape_string($conn, $next);

    $row = $conn->query("SELECT SUM(CAST(REPLACE(COALESCE(total_amount,'0'), ',', '') AS DECIMAL(15,2))) AS s
        FROM tbl_admin_actual_photocopy
        WHERE month_applicable >= '{$startEsc}'
          AND month_applicable <  '{$nextEsc}'")->fetch_assoc();

    $actual = (float)($row['s'] ?? 0);

    // ✅ Match your photocopy page behavior (skip if actual == 0)
    if ($actual <= 0) continue;

    $monthly_actual_breakdown['Photocopy'][$mlbl] =
        ($monthly_actual_breakdown['Photocopy'][$mlbl] ?? 0) + $actual;

    $actuals['Photocopy'] += $actual;
}

/* Tea Service - Head Office */
$actuals['Tea Service - Head Office'] = 0;
$monthly_actual_breakdown['Tea Service - Head Office'] = [];
$monthly_completion_breakdown['Tea Service - Head Office'] = []; // optional if you want completion in dashboard table

$categoryTea = 'Tea Service - Head Office';
$ot_floor_id = 12;

// denominator: active floors excluding OT
$floorRow = $conn->query("SELECT COUNT(*) AS c FROM tbl_admin_floors
    WHERE is_active=1 AND id <> $ot_floor_id")->fetch_assoc();
$total_floors = (int)($floorRow['c'] ?? 0);

// month loop to stay inside FY and keep keys consistent ("F Y")
foreach ($all_months as $mlbl) {
    $month_esc = mysqli_real_escape_string($conn, $mlbl);

    // Actuals + approved floors 
    $approved_row = $conn->query("SELECT COALESCE(SUM(grand_total),0) AS actual_amount,
            COUNT(DISTINCT floor_id) AS approved_floors_all,
            COUNT(DISTINCT CASE WHEN floor_id <> $ot_floor_id THEN floor_id END) AS approved_floors_no_ot
        FROM tbl_admin_tea_service_hdr
        WHERE month_year = '$month_esc'
        AND approval_status = 'approved'")->fetch_assoc();

    $actual            = (float)($approved_row['actual_amount'] ?? 0);
    $approvedFloorsAll = (int)($approved_row['approved_floors_all'] ?? 0);
    $approvedFloors    = (int)($approved_row['approved_floors_no_ot'] ?? 0);

    // ✅ only show/use months that have ANY approved records (even OT-only)
    if ($approvedFloorsAll <= 0) continue;

    $monthly_actual_breakdown[$categoryTea][$mlbl] =
        ($monthly_actual_breakdown[$categoryTea][$mlbl] ?? 0) + $actual;
    $actuals[$categoryTea] += $actual;

    // optional: store completion text/numbers if you want to show it
    $monthly_completion_breakdown[$categoryTea][$mlbl] = [
        'approved_no_ot' => $approvedFloors,
        'total_no_ot'    => $total_floors
    ];
}



/* ------------------ Telephone Bills (Corrected) ------------------ */
$actuals['Telephone Bills'] = 0;
$monthly_actual_breakdown['Telephone Bills'] = [];

foreach ($all_months as $mlbl) {
    [$start,$end,$ym,$ymd01] = month_bounds($mlbl);

    // Budget (per month)
    $stmtB = $conn->prepare("SELECT SUM(budget_amount) b 
                             FROM tbl_admin_budget_telephone 
                             WHERE budget_month=?");
    $stmtB->bind_param('s', $mlbl);
    $stmtB->execute();
    $rowB = $stmtB->get_result()->fetch_assoc();
    $stmtB->close();
    $budget = (float)($rowB['b'] ?? 0);

    // Dialog (from figures table)
    $m_dash = str_replace(' ', '-', $mlbl);
    $stmtD = $conn->prepare("SELECT dialog_bill_amount 
                             FROM tbl_admin_dialog_figures 
                             WHERE billing_month=?");
    $stmtD->bind_param('s', $m_dash);
    $stmtD->execute();
    $rowD = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();
    $dialog = (float)($rowD['dialog_bill_amount'] ?? 0);

    // CDMA
    $cdma = ratio_actual(
        $conn,
        'tbl_admin_cdma_monthly_data',
        'tbl_admin_cdma_monthly_data_charges',
        'tbl_admin_cdma_monthly_data_connections',
        [
            'bill_start'=>'bill_period_start',
            'bill_end'=>'bill_period_end',
            'upload_id'=>'upload_id',
            'subtotal'=>'subtotal',
            'tax_total'=>'tax_total'
        ],
        $start,$end
    );

    // SLT
    $slt = ratio_actual(
        $conn,
        'tbl_admin_slt_monthly_data',
        'tbl_admin_slt_monthly_data_charges',
        'tbl_admin_slt_monthly_data_connections',
        [
            'bill_start'=>'bill_period_start',
            'bill_end'=>'bill_period_end',
            'upload_id'=>'upload_id',
            'subtotal'=>'subtotal',
            'tax_total'=>'tax_total'
        ],
        $start,$end
    );

    // Combine actuals
    $total_this_month = $dialog + $cdma + $slt;
    if ($total_this_month > 0) {
        $monthly_actual_breakdown['Telephone Bills'][$mlbl] =
            ($monthly_actual_breakdown['Telephone Bills'][$mlbl] ?? 0) + $total_this_month;
        $actuals['Telephone Bills'] += $total_this_month;
    }
}

// Water Actuals
$actuals['Water'] = 0;
$monthly_actual_breakdown['Water'] = [];

$res = mysqli_query($conn, "
  SELECT DATE_FORMAT(STR_TO_DATE(month_applicable, '%M %Y'), '%M %Y') AS month,
         SUM(CAST(REPLACE(TRIM(total_amount), ',', '') AS DECIMAL(15,2))) AS total_amount
  FROM tbl_admin_actual_water
  WHERE TRIM(total_amount) != ''
  GROUP BY month
");

while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];

    // only count if month is selected
    if (in_array($month, $selected_months_by_category['Water'] ?? [])) {
        $monthly_actual_breakdown['Water'][$month] =
            ($monthly_actual_breakdown['Water'][$month] ?? 0) + $amount;
        $actuals['Water'] += $amount;
    }
}


// Water Budget
$budgets['Water'] = 0;
$monthly_budget_breakdown['Water'] = [];

$res = mysqli_query($conn, "
  SELECT budget_year, SUM(amount) AS monthly_total
  FROM tbl_admin_budget_water
  GROUP BY budget_year
");

while ($row = mysqli_fetch_assoc($res)) {
    $year          = $row['budget_year'];
    $monthly_total = (float)$row['monthly_total'];

    // Budget (Full Year)
    $budgets['Water'] += ($monthly_total * 12);

    // Budget (To Date) handled later via selected months
    foreach ($all_months as $mlbl) {
        if (strpos($mlbl, (string)$year) !== false) {
            $monthly_budget_breakdown['Water'][$mlbl] =
                ($monthly_budget_breakdown['Water'][$mlbl] ?? 0) + $monthly_total;
        }
    }
}


// courier 
$actuals['Courier'] = 0;
$monthly_actual_breakdown['Courier'] = [];

$res = mysqli_query($conn, "
  SELECT DATE_FORMAT(STR_TO_DATE(month_applicable, '%M %Y'), '%M %Y') AS month,
         SUM(CAST(REPLACE(TRIM(total_amount), ',', '') AS DECIMAL(15,2))) AS total_amount
  FROM tbl_admin_actual_courier
  WHERE TRIM(total_amount) != ''
  GROUP BY month
");

while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];

    // only count if month is selected
    if (in_array($month, $selected_months_by_category['Courier'] ?? [])) {
        $monthly_actual_breakdown['Courier'][$month] =
            ($monthly_actual_breakdown['Courier'][$month] ?? 0) + $amount;
        $actuals['Courier'] += $amount;
    }
}


// courier
$budgets['Courier'] = 0;
$monthly_budget_breakdown['Courier'] = [];

$res = mysqli_query($conn, "
  SELECT budget_year, SUM(amount) AS monthly_total
  FROM tbl_admin_budget_courier
  GROUP BY budget_year
");

while ($row = mysqli_fetch_assoc($res)) {
    $year          = $row['budget_year'];
    $monthly_total = (float)$row['monthly_total'];

    // Budget (Full Year)
    $budgets['Courier'] += ($monthly_total * 12);

    // Budget (To Date) handled later via selected months
    foreach ($all_months as $mlbl) {
        if (strpos($mlbl, (string)$year) !== false) {
            $monthly_budget_breakdown['Courier'][$mlbl] =
                ($monthly_budget_breakdown['Courier'][$mlbl] ?? 0) + $monthly_total;
        }
    }
}

/* ---------------- Tea Branches  ---------------- */
$catTeaB = 'Tea Branches';

// init
$actuals[$catTeaB] = 0;
$monthly_actual_breakdown[$catTeaB] = [];

$budgets[$catTeaB] = 0;
$monthly_budget_breakdown[$catTeaB] = [];

// denominator: total distinct branches from budget master table
$total_branches = 0;
$resTB = $conn->query("SELECT COUNT(DISTINCT branch_code) AS total FROM tbl_admin_budget_tea_branch");
if ($resTB && $rowTB = $resTB->fetch_assoc()) {
    $total_branches = (int)($rowTB['total'] ?? 0);
}

// (optional) store completion if you later show it in UI
$monthly_completion_breakdown[$catTeaB] = [];

foreach ($all_months as $mlbl) {
    $mEsc = mysqli_real_escape_string($conn, $mlbl);

    /* Budget: month total */
    $bRow = $conn->query("SELECT COALESCE(SUM(budget_amount),0) AS b
        FROM tbl_admin_budget_tea_branch
        WHERE applicable_month = '{$mEsc}'")->fetch_assoc();

    $budget = (float)($bRow['b'] ?? 0);

    $monthly_budget_breakdown[$catTeaB][$mlbl] =
        ($monthly_budget_breakdown[$catTeaB][$mlbl] ?? 0) + $budget;

    $budgets[$catTeaB] += $budget;

    /* Actual: sum debits for that month (robust debit flag handling) */
    $aRow = $conn->query("SELECT COALESCE(SUM(debits),0) AS a
        FROM tbl_admin_actual_branch_gl_tea
        WHERE applicable_month = '{$mEsc}'
          AND debits IS NOT NULL
          AND debits > 0
          AND (
                tran_db_cr_flg IS NULL
                OR UPPER(TRIM(tran_db_cr_flg)) IN ('D','DR','DEBIT')
                OR UPPER(TRIM(tran_db_cr_flg)) LIKE 'D%'
              )")->fetch_assoc();

    $actual = (float)($aRow['a'] ?? 0);

    // match your page: show/use only months with actuals
    if ($actual <= 0) continue;

    $monthly_actual_breakdown[$catTeaB][$mlbl] =
        ($monthly_actual_breakdown[$catTeaB][$mlbl] ?? 0) + $actual;

    $actuals[$catTeaB] += $actual;

    /* Completion: distinct branches with debits > 0 */
    $cRow = $conn->query("SELECT COUNT(DISTINCT enterd_brn) AS c
        FROM tbl_admin_actual_branch_gl_tea
        WHERE applicable_month = '{$mEsc}'
          AND debits IS NOT NULL
          AND debits > 0
          AND enterd_brn IS NOT NULL
          AND TRIM(enterd_brn) <> ''
          AND (
                tran_db_cr_flg IS NULL
                OR UPPER(TRIM(tran_db_cr_flg)) IN ('D','DR','DEBIT')
                OR UPPER(TRIM(tran_db_cr_flg)) LIKE 'D%'
              )")->fetch_assoc();

    $completed = (int)($cRow['c'] ?? 0);

    $monthly_completion_breakdown[$catTeaB][$mlbl] = [
        'completed' => $completed,
        'total'     => $total_branches
    ];
}
/* ---------------- News Paper  ---------------- */
$catNP = 'News Paper';

$actuals[$catNP] = 0;
$monthly_actual_breakdown[$catNP] = [];

$budgets[$catNP] = 0;
$monthly_budget_breakdown[$catNP] = [];

$monthly_completion_breakdown[$catNP] = [];

/* total branches (denominator) from budget table */
$total_np_branches = 0;
$resTB = $conn->query("SELECT COUNT(DISTINCT branch_code) AS total
    FROM tbl_admin_budget_newspaper_branch");
if ($resTB && $rowTB = $resTB->fetch_assoc()) {
    $total_np_branches = (int)($rowTB['total'] ?? 0);
}

foreach ($all_months as $mlbl) {
    $mEsc = mysqli_real_escape_string($conn, $mlbl);

    /* Budget: monthly total */
    $bRow = $conn->query("SELECT COALESCE(SUM(budget_amount),0) AS b
        FROM tbl_admin_budget_newspaper_branch
        WHERE applicable_month = '{$mEsc}'")->fetch_assoc();
    $budget = (float)($bRow['b'] ?? 0);

    $monthly_budget_breakdown[$catNP][$mlbl] =
        ($monthly_budget_breakdown[$catNP][$mlbl] ?? 0) + $budget;

    $budgets[$catNP] += $budget;

    /* Actuals: sum of debits (Debit-side only, robust flag handling) */
    $aRow = $conn->query("SELECT COALESCE(SUM(debits),0) AS a
        FROM tbl_admin_actual_branch_gl_newspaper
        WHERE applicable_month = '{$mEsc}'
          AND COALESCE(debits,0) <> 0
          AND (
                UPPER(TRIM(COALESCE(tran_db_cr_flg,''))) = 'D'
                OR UPPER(TRIM(COALESCE(tran_db_cr_flg,''))) = 'DR'
                OR UPPER(TRIM(COALESCE(tran_db_cr_flg,''))) = 'DEBIT'
                OR UPPER(TRIM(COALESCE(tran_db_cr_flg,''))) LIKE 'D%'
              )")->fetch_assoc();
    $actual = (float)($aRow['a'] ?? 0);

    // ✅ keep same behavior: skip months with 0 actual, but keep negatives
    if (abs($actual) < 0.005) {
        continue;
    }

    $monthly_actual_breakdown[$catNP][$mlbl] =
        ($monthly_actual_breakdown[$catNP][$mlbl] ?? 0) + $actual;

    $actuals[$catNP] += $actual;

    /* Completion: branches having ANY actual (debits != 0) */
    $cRow = $conn->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(brn_code,''), enterd_brn)) AS c
        FROM tbl_admin_actual_branch_gl_newspaper
        WHERE applicable_month = '{$mEsc}'
          AND COALESCE(debits,0) <> 0")->fetch_assoc();
    $completed = (int)($cRow['c'] ?? 0);

    $monthly_completion_breakdown[$catNP][$mlbl] = [
        'completed' => $completed,
        'total'     => $total_np_branches
    ];
}
/* ---------------- Printing & Stationary ---------------- */
$catP = 'Printing & Stationary';

$actuals[$catP] = 0;
$monthly_actual_breakdown[$catP] = [];

$budgets[$catP] = 0;
$monthly_budget_breakdown[$catP] = [];

// Optional: completion tracking (like other branch modules)
$monthly_completion_breakdown[$catP] = [];

/* total branches (denominator) from master branch list */
$total_print_branches = 0;
$resTP = $conn->query("SELECT COUNT(DISTINCT branch_code) AS total FROM tbl_admin_branch_printing");
if ($resTP && $rowTP = $resTP->fetch_assoc()) {
    $total_print_branches = (int)($rowTP['total'] ?? 0);
}

foreach ($all_months as $mlbl) {
    $mEsc = mysqli_real_escape_string($conn, $mlbl);

    /* Budget: sum(amount) for the month label (budget_year stores month text) */
    $bRow = $conn->query("SELECT COALESCE(SUM(amount),0) AS b
        FROM tbl_admin_budget_printing
        WHERE budget_year = '{$mEsc}'")->fetch_assoc();

    $budget = (float)($bRow['b'] ?? 0);

    $monthly_budget_breakdown[$catP][$mlbl] =
        ($monthly_budget_breakdown[$catP][$mlbl] ?? 0) + $budget;

    $budgets[$catP] += $budget;

    /* Actual: sum(total_amount) for the month */
    $aRow = $conn->query("SELECT COALESCE(
            SUM(CAST(REPLACE(TRIM(COALESCE(total_amount,'0')), ',', '') AS DECIMAL(15,2))), 0
        ) AS a
        FROM tbl_admin_actual_printing
        WHERE month_applicable = '{$mEsc}'
          AND TRIM(COALESCE(total_amount,'')) <> ''")->fetch_assoc();

    $actual = (float)($aRow['a'] ?? 0);

    // ✅ match your module behavior: skip empty months (but keep negative if any)
    if (abs($actual) < 0.005) continue;

    $monthly_actual_breakdown[$catP][$mlbl] =
        ($monthly_actual_breakdown[$catP][$mlbl] ?? 0) + $actual;

    $actuals[$catP] += $actual;

    /* Completion: distinct branches with actual rows (total_amount != 0) */
    $cRow = $conn->query("SELECT COUNT(DISTINCT branch_code) AS c
        FROM tbl_admin_actual_printing
        WHERE month_applicable = '{$mEsc}'
          AND TRIM(COALESCE(branch_code,'')) <> ''
          AND CAST(REPLACE(TRIM(COALESCE(total_amount,'0')), ',', '') AS DECIMAL(15,2)) <> 0")->fetch_assoc();

    $completed = (int)($cRow['c'] ?? 0);

    $monthly_completion_breakdown[$catP][$mlbl] = [
        'completed' => $completed,
        'total'     => $total_print_branches
    ];
}


/* ---------------- Security VPN (fixed) ---------------- */

$actuals['Security VPN'] = 0;
$monthly_actual_breakdown['Security VPN'] = [];

$res = mysqli_query($conn, "
    SELECT month_name AS month,
           SUM(COALESCE(total_amount, 0)) AS total_amount
    FROM tbl_admin_actual_security_vpn
    WHERE total_amount IS NOT NULL
      AND total_amount <> 0
    GROUP BY month_name
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];

    // only include if month selected for this category
    if (in_array($month, $selected_months_by_category['Security VPN'] ?? [])) {
        $monthly_actual_breakdown['Security VPN'][$month] = $amount;
        $actuals['Security VPN'] += $amount;
    }
}

$budgets['Security VPN'] = 0;
$monthly_budget_breakdown['Security VPN'] = [];

$res = mysqli_query($conn, "
    SELECT month_name AS month,
           COALESCE(amount, 0) AS amount
    FROM tbl_admin_budget_security_vpn
    WHERE amount IS NOT NULL
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['amount'];

    $budgets['Security VPN'] += $amount;
    $monthly_budget_breakdown['Security VPN'][$month] = $amount;
}



/* Vehicle Maintenance — unified robust query using report_date */
$actuals['Vehicle Maintenance'] = 0;
if (!isset($monthly_actual_breakdown['Vehicle Maintenance'])) {
    $monthly_actual_breakdown['Vehicle Maintenance'] = [];
}

$sqlVehicle = "
SELECT
  m.month_name                                AS `Month`,
  COALESCE(b.budget_amount, 0)                AS budget_amount,
  COALESCE(a.maintenance, 0)                  AS actual_maintenance,
  COALESCE(a.service, 0)                      AS actual_service,
  COALESCE(a.lic_ins, 0)                      AS actual_lic_ins,
  (COALESCE(a.maintenance,0)+COALESCE(a.service,0)+COALESCE(a.lic_ins,0)) AS actual_total,
  COALESCE(b.budget_amount,0) - (COALESCE(a.maintenance,0)+COALESCE(a.service,0)+COALESCE(a.lic_ins,0)) AS difference,
  CASE
    WHEN COALESCE(b.budget_amount,0) > 0 THEN ROUND(
      (COALESCE(b.budget_amount,0) - (COALESCE(a.maintenance,0)+COALESCE(a.service,0)+COALESCE(a.lic_ins,0)))
      / COALESCE(b.budget_amount,0) * 100
    )
    ELSE NULL
  END                                          AS variance_pct
FROM
(
  SELECT budget_month AS month_name
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''

  UNION
  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_maintenance
  WHERE STATUS='Approved' AND report_date IS NOT NULL

  UNION
  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_service
  WHERE STATUS='Approved' AND report_date IS NOT NULL

  UNION
  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND report_date IS NOT NULL
) m
LEFT JOIN
(
  SELECT
    budget_month,
    SUM(CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))) AS budget_amount
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''
  GROUP BY budget_month
) b
  ON b.budget_month = m.month_name
LEFT JOIN
(
  SELECT month_name,
         SUM(actual_maintenance) AS maintenance,
         SUM(actual_service)     AS service,
         SUM(actual_lic_ins)     AS lic_ins
  FROM (
    /* Maintenance */
    SELECT
      DATE_FORMAT(report_date, '%M %Y') AS month_name,
      SUM(CAST(REPLACE(price, ',', '') AS DECIMAL(15,2))) AS actual_maintenance,
      0 AS actual_service,
      0 AS actual_lic_ins
    FROM tbl_admin_vehicle_maintenance
    WHERE STATUS='Approved' AND report_date IS NOT NULL
    GROUP BY month_name

    UNION ALL

    /* Service */
    SELECT
      DATE_FORMAT(report_date, '%M %Y') AS month_name,
      0,
      SUM(CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))) AS actual_service,
      0
    FROM tbl_admin_vehicle_service
    WHERE STATUS='Approved' AND report_date IS NOT NULL
    GROUP BY month_name

    UNION ALL

    /* Licensing + Insurance */
    SELECT
      DATE_FORMAT(report_date, '%M %Y') AS month_name,
      0, 0,
      SUM(
        CAST(REPLACE(COALESCE(emission_test_amount, '0'), ',', '') AS DECIMAL(15,2)) +
        CAST(REPLACE(COALESCE(revenue_license_amount, '0'), ',', '') AS DECIMAL(15,2)) +
        CAST(REPLACE(COALESCE(insurance_amount, '0'), ',', '') AS DECIMAL(15,2))
      ) AS actual_lic_ins
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS='Approved' AND report_date IS NOT NULL
    GROUP BY month_name
  ) x
  GROUP BY month_name
) a
  ON a.month_name = m.month_name
WHERE m.month_name IS NOT NULL AND m.month_name <> ''
ORDER BY STR_TO_DATE(m.month_name, '%M %Y');
";

$resVehicle = $conn->query($sqlVehicle);
if ($resVehicle) {
    $fyMonths = array_flip($all_months);

    while ($r = $resVehicle->fetch_assoc()) {
        $mLabel = $r['Month'] ?? '';
        if ($mLabel === '' || !isset($fyMonths[$mLabel])) continue;

        $actualTotal = (float)($r['actual_total'] ?? 0);
        if ($actualTotal == 0.0) continue;

        $monthly_actual_breakdown['Vehicle Maintenance'][$mLabel] =
            ($monthly_actual_breakdown['Vehicle Maintenance'][$mLabel] ?? 0) + $actualTotal;

        $actuals['Vehicle Maintenance'] += $actualTotal;
    }
}

/* Staff Transport */
$actuals['Staff Transport'] = 0;
$res = mysqli_query($conn, "
  SELECT DATE_FORMAT(`date`, '%M %Y') AS month, SUM(total) AS amount
  FROM tbl_admin_kangaroo_transport
  GROUP BY DATE_FORMAT(`date`, '%M %Y')
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['amount'];
    $monthly_actual_breakdown['Staff Transport'][$month] =
        ($monthly_actual_breakdown['Staff Transport'][$month] ?? 0) + $amount;
    $actuals['Staff Transport'] += $amount;
}
$res = mysqli_query($conn, "
  SELECT DATE_FORMAT(STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p'), '%M %Y') AS month,
         SUM(total_fare) AS amount
  FROM tbl_admin_pickme_data
  WHERE pickup_time IS NOT NULL AND pickup_time != ''
  GROUP BY DATE_FORMAT(STR_TO_DATE(pickup_time, '%W, %M %D %Y, %l:%i:%s %p'), '%M %Y')
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['amount'];
    $monthly_actual_breakdown['Staff Transport'][$month] =
        ($monthly_actual_breakdown['Staff Transport'][$month] ?? 0) + $amount;
    $actuals['Staff Transport'] += $amount;
}

/* Postage & Stamps — CORRECT: use breakdown subtotals by entry_date month */
$actuals['Postage & Stamps'] = 0;
$fyMonths  = getCurrentFinancialYearMonths();
$fyStartDt = DateTime::createFromFormat('F Y', $fyMonths[0]);
$fyEndDt   = DateTime::createFromFormat('F Y', $fyMonths[count($fyMonths)-1]);
$fyStartStr = $fyStartDt ? $fyStartDt->format('Y-m-01') : null;
$fyEndStr   = $fyEndDt ? $fyEndDt->modify('+1 month')->format('Y-m-01') : null;

$sqlPostage = "
  SELECT
    DATE_FORMAT(MIN(a.entry_date), '%M %Y') AS month,
    SUM(COALESCE(b.subtotal, 0))           AS total_amount
  FROM tbl_admin_postage_stamps_breakdown b
  JOIN tbl_admin_actual_postage_stamps a
    ON a.id = b.postage_id
";
if ($fyStartStr && $fyEndStr) {
  $sqlPostage .= " WHERE a.entry_date >= '".$conn->real_escape_string($fyStartStr)."'
                    AND a.entry_date <  '".$conn->real_escape_string($fyEndStr)."' ";
}
$sqlPostage .= "
  GROUP BY YEAR(a.entry_date), MONTH(a.entry_date)
  ORDER BY YEAR(a.entry_date), MONTH(a.entry_date)
";
$res = mysqli_query($conn, $sqlPostage);
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)($row['total_amount'] ?? 0);
    if ($amount == 0) continue;
    $monthly_actual_breakdown['Postage & Stamps'][$month] =
        ($monthly_actual_breakdown['Postage & Stamps'][$month] ?? 0) + $amount;
    $actuals['Postage & Stamps'] += $amount;
}

/* ---------------------- Combine (respect selections) ---------------------- */
$combined = [];
$to_date_budgets = [];

foreach ($budgets as $category => $budget_full) {
    $budget_to_date = 0;
    $actual = 0;
    $months_selected = $selected_months_by_category[$category] ?? [];

    foreach ($months_selected as $month) {
        if (isset($monthly_budget_breakdown[$category][$month])) {
            $budget_to_date += $monthly_budget_breakdown[$category][$month];
        }
        if (isset($monthly_actual_breakdown[$category][$month])) {
            $actual += $monthly_actual_breakdown[$category][$month];
        }
    }

    $balance  = $budget_to_date - $actual;
    $variance = ($budget_to_date > 0) ? round((($budget_to_date - $actual) / $budget_to_date) * 100) : 0;
    $month_count = count($months_selected);
    $months_text = $month_count ? implode(', ', $months_selected) : 'No data selected to display';

    $combined[] = [
        'category'        => $category,
        'budget_full'     => $budget_full,
        'budget_to_date'  => $budget_to_date,
        'actual'          => $actual,
        'balance'         => $balance,
        'variance'        => $variance,
        'month_count'     => $month_count,
        'months_text'     => $months_text
    ];

    $to_date_budgets[$category] = $budget_to_date;
}

usort($combined, fn($a, $b) => $b['budget_full'] <=> $a['budget_full']);

$startYear = (int)date('n') < 4 ? date('Y') - 1 : date('Y');
$endYear   = $startYear + 1;
?>