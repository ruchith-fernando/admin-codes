<?php
// dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    'Printing & Stationary'        => ['table' => 'tbl_admin_budget_stationary',           'column' => 'month',            'calc' => 'budget_amount'],
    'Electricity Charges'          => ['table' => 'tbl_admin_budget_electricity',          'column' => 'budget_year',      'calc' => 'amount'], // handled specially
    'Photocopy'                    => ['table' => 'tbl_admin_budget_photocopies',          'column' => 'month_year',       'calc' => 'budget_amount'],
    'Courier'                      => ['table' => 'tbl_admin_budget_courier',              'column' => 'budget_month',     'calc' => 'amount'],
    'Vehicle Maintenance'          => ['table' => 'tbl_admin_budget_vehicle_maintenance',  'column' => 'budget_month',     'calc' => 'amount'],
    'Postage & Stamps'             => ['table' => 'tbl_admin_budget_postage_stamps',       'column' => 'month_year',       'calc' => 'budget_amount'],
    'Staff Transport'              => ['table' => 'tbl_admin_budget_staff_transport',      'column' => 'budget_month',     'calc' => 'budget_amount'],
    'Telephone Bills'              => ['table' => 'tbl_admin_budget_telephone',            'column' => 'budget_month',     'calc' => 'budget_amount'],
    'Newspaper'                    => ['table' => 'tbl_admin_budget_newspaper',            'column' => 'month_year',       'calc' => 'budget_amount'],
    'Water'                        => ['table' => 'tbl_admin_budget_water',                'column' => 'month_year',       'calc' => 'budget_amount'],
];

$category_links = [
    'Security Charges'           => 'security-cost-report.php',
    'Tea Service - Head Office'  => 'tea-budget-vs-actual.php',
    'Printing & Stationary'      => 'budget-vs-actual-stationary.php',
    'Electricity Charges'        => 'electricity-overview.php',
    'Photocopy'                  => 'photocopy-budget-report.php',
    'Courier'                    => 'courier-cost-report.php',
    'Vehicle Maintenance'        => 'vehicle-budget-vs-actual.php',
    'Postage & Stamps'           => 'postage-budget-vs-actual.php',
    'Telephone Bills'            => 'telephone-budget-vs-actual.php',
    'Newspaper'                  => '#',
    'Water'                      => '#',
];

$budgets = [];
$monthly_budget_breakdown = [];

$currentFY = ((int)date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;
foreach ($budget_tables as $category => $info) {
    // ⚡ Electricity: budget table now stores MONTHLY amount per branch by FY (budget_year).
    if ($category === 'Electricity Charges') {
        $budgets[$category] = 0;

        // Monthly total (all branches) for the current FY
        $fyEsc = mysqli_real_escape_string($conn, (string)$currentFY);
        $row   = $conn->query("
            SELECT SUM(amount) AS monthly_total
            FROM tbl_admin_budget_electricity
            WHERE budget_year = '{$fyEsc}'
        ")->fetch_assoc();

        $monthly_total = (float)($row['monthly_total'] ?? 0);

        // Full-year budget = monthly_total * 12 (Apr→Mar of current FY)
        $budgets[$category] = $monthly_total * 12;

        // Per-month breakdown for the current FY months shown on the dashboard
        foreach ($all_months as $mlbl) {
            $monthly_budget_breakdown[$category][$mlbl] =
                ($monthly_budget_breakdown[$category][$mlbl] ?? 0) + $monthly_total;
        }

        // Continue to next category; leave others unchanged
        continue;
    }

    // ✅ All other categories: generic logic
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
$res = mysqli_query($conn, "SELECT month_applicable AS month, total_amount FROM tbl_admin_actual_security");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];
    $monthly_actual_breakdown['Security Charges'][$month] =
        ($monthly_actual_breakdown['Security Charges'][$month] ?? 0) + $amount;
    $actuals['Security Charges'] += $amount;
}

/* Electricity */
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

/* Photocopy */
$actuals['Photocopy'] = 0;
$res = mysqli_query($conn, "SELECT record_date AS month, total FROM tbl_admin_actual_photocopy");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total'];
    $monthly_actual_breakdown['Photocopy'][$month] =
        ($monthly_actual_breakdown['Photocopy'][$month] ?? 0) + $amount;
    $actuals['Photocopy'] += $amount;
}

/* Tea Service */
$actuals['Tea Service - Head Office'] = 0;
$res = mysqli_query($conn, "SELECT month_year AS month, total_price FROM tbl_admin_tea_service");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_price'];
    $monthly_actual_breakdown['Tea Service - Head Office'][$month] =
        ($monthly_actual_breakdown['Tea Service - Head Office'][$month] ?? 0) + $amount;
    $actuals['Tea Service - Head Office'] += $amount;
}

/* ------------------ Telephone Bills (Dialog + CDMA + SLT) ------------------ */
$actuals['Telephone Bills'] = 0;

foreach ($all_months as $mlbl) {
    [$start,$end,$ym,$ymd01] = month_bounds($mlbl);

    // Dialog
    $dialog = dialog_actual($conn, [
        'table'           => 'tbl_admin_mobile_bill_data',
        'period_col'      => 'Update_date',
        'amount_col'      => 'TOTAL_AMOUNT_PAYABLE',
        'number_col'      => 'MOBILE_Number',
        'special_include' => ['765055020'],
        'exclude_numbers' => []
    ], $mlbl, $ym, $ymd01);

    // CDMA
    $cdma = ratio_actual(
        $conn,
        'tbl_admin_cdma_monthly_data',
        'tbl_admin_cdma_monthly_data_charges',
        'tbl_admin_cdma_monthly_data_connections',
        [
            'bill_start' => 'bill_period_start',
            'bill_end'   => 'bill_period_end',
            'upload_id'  => 'upload_id',
            'subtotal'   => 'subtotal',
            'tax_total'  => 'tax_total',
        ],
        $start, $end
    );

    // SLT
    $slt = ratio_actual(
        $conn,
        'tbl_admin_slt_monthly_data',
        'tbl_admin_slt_monthly_data_charges',
        'tbl_admin_slt_monthly_data_connections',
        [
            'bill_start' => 'bill_period_start',
            'bill_end'   => 'bill_period_end',
            'upload_id'  => 'upload_id',
            'subtotal'   => 'subtotal',
            'tax_total'  => 'tax_total',
        ],
        $start, $end
    );

    $total_this_month = $dialog + $cdma + $slt;

    if ($total_this_month != 0) {
        $monthly_actual_breakdown['Telephone Bills'][$mlbl] =
            ($monthly_actual_breakdown['Telephone Bills'][$mlbl] ?? 0) + $total_this_month;
        $actuals['Telephone Bills'] += $total_this_month;
    }
}
/* --------------------------------------------------------------------------- */

/* Printing & Stationary */
$actuals['Printing & Stationary'] = 0;
$res = mysqli_query($conn, "
    SELECT DATE_FORMAT(issued_date, '%M %Y') AS month, SUM(total_cost) AS total_amount
    FROM tbl_admin_stationary_stock_out
    WHERE dual_control_status = 'approved'
    GROUP BY DATE_FORMAT(issued_date, '%M %Y')
");
while ($row = mysqli_fetch_assoc($res)) {
    $month  = $row['month'];
    $amount = (float)$row['total_amount'];
    $monthly_actual_breakdown['Printing & Stationary'][$month] =
        ($monthly_actual_breakdown['Printing & Stationary'][$month] ?? 0) + $amount;
    $actuals['Printing & Stationary'] += $amount;
}

/* Vehicle Maintenance (overall + monthly splits) */
// $actuals['Vehicle Maintenance'] = 0;
// $res = mysqli_query($conn, "
//     SELECT SUM(total_actual) AS actual_amount FROM (
//         SELECT CAST(emission_test_amount AS DECIMAL(15,2)) AS total_actual
//         FROM tbl_admin_vehicle_licensing_insurance
//         WHERE STATUS = 'Approved' AND emission_test_date IS NOT NULL

//         UNION ALL
//         SELECT CAST(revenue_license_amount AS DECIMAL(15,2))
//         FROM tbl_admin_vehicle_licensing_insurance
//         WHERE STATUS = 'Approved' AND revenue_license_date IS NOT NULL

//         UNION ALL
//         SELECT CAST(price AS DECIMAL(15,2))
//         FROM tbl_admin_vehicle_maintenance
//         WHERE STATUS = 'Approved' AND purchase_date IS NOT NULL

//         UNION ALL
//         SELECT CAST(amount AS DECIMAL(15,2))
//         FROM tbl_admin_vehicle_service
//         WHERE STATUS = 'Approved' AND service_date IS NOT NULL
//     ) all_actuals
// ");
// $total_vehicle_actual = (float)(mysqli_fetch_assoc($res)['actual_amount'] ?? 0);
// $actuals['Vehicle Maintenance'] = $total_vehicle_actual;

// // monthly breakdowns
// $res = mysqli_query($conn, "
//     SELECT DATE_FORMAT(emission_test_date, '%M %Y') AS month, SUM(emission_test_amount) AS amt
//     FROM tbl_admin_vehicle_licensing_insurance
//     WHERE STATUS = 'Approved'
//     GROUP BY DATE_FORMAT(emission_test_date, '%M %Y')
// ");
// while ($row = mysqli_fetch_assoc($res)) {
//     $monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] =
//         ($monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] ?? 0) + (float)$row['amt'];
// }
// $res = mysqli_query($conn, "
//     SELECT DATE_FORMAT(revenue_license_date, '%M %Y') AS month, SUM(revenue_license_amount) AS amt
//     FROM tbl_admin_vehicle_licensing_insurance
//     WHERE STATUS = 'Approved'
//     GROUP BY DATE_FORMAT(revenue_license_date, '%M %Y')
// ");
// while ($row = mysqli_fetch_assoc($res)) {
//     $monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] =
//         ($monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] ?? 0) + (float)$row['amt'];
// }
// $res = mysqli_query($conn, "
//     SELECT DATE_FORMAT(purchase_date, '%M %Y') AS month, SUM(price) AS amt
//     FROM tbl_admin_vehicle_maintenance
//     WHERE STATUS = 'Approved'
//     GROUP BY DATE_FORMAT(purchase_date, '%M %Y')
// ");
// while ($row = mysqli_fetch_assoc($res)) {
//     $monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] =
//         ($monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] ?? 0) + (float)$row['amt'];
// }
// $res = mysqli_query($conn, "
//     SELECT DATE_FORMAT(service_date, '%M %Y') AS month, SUM(amount) AS amt
//     FROM tbl_admin_vehicle_service
//     WHERE STATUS = 'Approved'
//     GROUP BY DATE_FORMAT(service_date, '%M %Y')
// ");
// while ($row = mysqli_fetch_assoc($res)) {
//     $monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] =
//         ($monthly_actual_breakdown['Vehicle Maintenance'][$row['month']] ?? 0) + (float)$row['amt'];
// }

/* Vehicle Maintenance — unified robust query (created_at fallback) */
$actuals['Vehicle Maintenance'] = 0;
if (!isset($monthly_actual_breakdown['Vehicle Maintenance'])) {
    $monthly_actual_breakdown['Vehicle Maintenance'] = [];
}

$sqlVehicle = "
/* Vehicle Budget vs Actual by Month — robust dates (with created_at fallback) */
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
  /* All distinct months appearing anywhere (budget or actuals) */
  SELECT budget_month AS month_name
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''

  UNION
  SELECT DATE_FORMAT(
           COALESCE(NULLIF(repair_date,'0000-00-00'), NULLIF(purchase_date,'0000-00-00'), created_at),
           '%M %Y'
         )
  FROM tbl_admin_vehicle_maintenance
  WHERE STATUS='Approved'

  UNION
  SELECT DATE_FORMAT(
           COALESCE(NULLIF(service_date,'0000-00-00'), created_at),
           '%M %Y'
         )
  FROM tbl_admin_vehicle_service
  WHERE STATUS='Approved'

  UNION
  SELECT DATE_FORMAT(
           COALESCE(NULLIF(emission_test_date,'0000-00-00'), created_at),
           '%M %Y'
         )
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND TRIM(COALESCE(emission_test_amount,'')) <> ''

  UNION
  SELECT DATE_FORMAT(
           COALESCE(NULLIF(revenue_license_date,'0000-00-00'), created_at),
           '%M %Y'
         )
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND TRIM(COALESCE(revenue_license_amount,'')) <> ''

  UNION
  SELECT DATE_FORMAT(
           COALESCE(NULLIF(revenue_license_date,'0000-00-00'), NULLIF(emission_test_date,'0000-00-00'), created_at),
           '%M %Y'
         )
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE STATUS='Approved' AND TRIM(COALESCE(insurance_amount,'')) <> ''
) m
LEFT JOIN
(
  /* Budget by month */
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
  /* Actuals split by category, aggregated to month (robust date handling) */
  SELECT month_name,
         SUM(actual_maintenance) AS maintenance,
         SUM(actual_service)     AS service,
         SUM(actual_lic_ins)     AS lic_ins
  FROM (
    /* Maintenance (Table 2) */
    SELECT
      DATE_FORMAT(
        COALESCE(NULLIF(repair_date,'0000-00-00'), NULLIF(purchase_date,'0000-00-00'), created_at),
        '%M %Y'
      ) AS month_name,
      SUM(CAST(REPLACE(price, ',', '') AS DECIMAL(15,2))) AS actual_maintenance,
      0 AS actual_service,
      0 AS actual_lic_ins
    FROM tbl_admin_vehicle_maintenance
    WHERE STATUS='Approved' AND TRIM(COALESCE(price,'')) <> ''
    GROUP BY month_name

    UNION ALL

    /* Service (Table 3) */
    SELECT
      DATE_FORMAT(
        COALESCE(NULLIF(service_date,'0000-00-00'), created_at),
        '%M %Y'
      ) AS month_name,
      0,
      SUM(CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))) AS actual_service,
      0
    FROM tbl_admin_vehicle_service
    WHERE STATUS='Approved' AND TRIM(COALESCE(amount,'')) <> ''
    GROUP BY month_name

    UNION ALL

    /* Licensing & Insurance (Table 1) — three components */
    SELECT
      DATE_FORMAT(
        COALESCE(NULLIF(emission_test_date,'0000-00-00'), created_at),
        '%M %Y'
      ) AS month_name,
      0, 0,
      SUM(CAST(REPLACE(emission_test_amount, ',', '') AS DECIMAL(15,2)))
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS='Approved' AND TRIM(COALESCE(emission_test_amount,'')) <> ''
    GROUP BY month_name

    UNION ALL

    SELECT
      DATE_FORMAT(
        COALESCE(NULLIF(revenue_license_date,'0000-00-00'), created_at),
        '%M %Y'
      ) AS month_name,
      0, 0,
      SUM(CAST(REPLACE(revenue_license_amount, ',', '') AS DECIMAL(15,2)))
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS='Approved' AND TRIM(COALESCE(revenue_license_amount,'')) <> ''
    GROUP BY month_name

    UNION ALL

    SELECT
      DATE_FORMAT(
        COALESCE(NULLIF(revenue_license_date,'0000-00-00'), NULLIF(emission_test_date,'0000-00-00'), created_at),
        '%M %Y'
      ) AS month_name,
      0, 0,
      SUM(CAST(REPLACE(insurance_amount, ',', '') AS DECIMAL(15,2)))
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS='Approved' AND TRIM(COALESCE(insurance_amount,'')) <> ''
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
    // Limit to current FY months already calculated by getCurrentFinancialYearMonths()
    $fyMonths = array_flip($all_months);

    while ($r = $resVehicle->fetch_assoc()) {
        $mLabel = $r['Month'] ?? '';
        if ($mLabel === '' || !isset($fyMonths[$mLabel])) continue;

        $actualTotal = (float)($r['actual_total'] ?? 0);
        if ($actualTotal == 0.0) continue;

        $monthly_actual_breakdown['Vehicle Maintenance'][$mLabel] =
            ($monthly_actual_breakdown['Vehicle Maintenance'][$mLabel] ?? 0) + $actualTotal;

        $actuals['Vehicle Maintenance'] += $actualTotal; // overall (not used for to-date calc, but kept for completeness)
    }
} else {
    // Optional: you can log $conn->error here if you keep a dashboard log file.
    // $actuals['Vehicle Maintenance'] remains whatever has been accumulated (0 by default).
}

/* Staff Transport */
$actuals['Staff Transport'] = 0;
// Kangaroo
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
// PickMe
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
    $month  = $row['month'];                       // "April 2025"
    $amount = (float)($row['total_amount'] ?? 0);  // stamps-only money
    if ($amount == 0) continue;                    // skip zero months just in case
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
<style>
  table td { word-wrap: break-word; white-space: normal; }
</style>

<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Administration Overall Budget Overview</h5>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Category</th>
              <th>Budget (Full Year)</th>
              <th>Budget (To Date)</th>
              <th>Actual (To Date)</th>
              <th>Variance Balance</th>
              <th>Variance (%)</th>
            </tr>
          </thead>
          <tbody>
          <?php
            $i = 1;
            $totals = ['budget' => 0, 'to_date' => 0, 'actual' => 0, 'balance' => 0];

            foreach ($combined as $row) {
                $category       = $row['category'];
                $budget_full    = $row['budget_full'];
                $budget_to_date = $row['budget_to_date'];
                $actual         = $row['actual'];
                $balance        = $row['balance'];
                $variance       = $row['variance'];
                $month_count    = $row['month_count'];
                $months_text    = $row['months_text'];

                $variance_class = ($variance < 0) ? 'text-danger' : 'text-success';
                $link           = $category_links[$category] ?? '#';

                echo "<tr>";
                echo "<td>{$i}</td>";
                echo "<td style='max-width: 220px; word-break: break-word; white-space: normal;'>
                        <span class='load-report text-primary' style='cursor:pointer;' data-url='{$link}'>{$category}</span><br>
                        <span class='text-danger'>($months_text)</span>
                      </td>";
                echo "<td class='text-end'>" . number_format($budget_full) . "</td>";

                echo "<td class='text-end'>" . number_format($budget_to_date);
                if ($month_count > 0) {
                    echo "<br><span class='text-danger'>({$month_count} months)</span>";
                } else {
                    echo "<br><span class='text-danger'>(No months selected to display)</span>";
                }
                echo "</td>";

                echo "<td class='text-end'>" . number_format($actual) . "</td>";
                echo "<td class='text-end'>" . number_format($balance) . "</td>";
                echo "<td class='text-end {$variance_class}'>" . number_format($variance) . "%</td>";
                echo "</tr>";

                $i++;
                $totals['budget']  += $budget_full;
                $totals['to_date'] += $budget_to_date;
                $totals['actual']  += $actual;
                $totals['balance'] += $balance;
            }

            $total_variance = ($totals['to_date'] > 0)
              ? round((($totals['to_date'] - $totals['actual']) / $totals['to_date']) * 100)
              : 0;
          ?>
            <tr class="fw-bold table-light">
              <td colspan="2" class="text-start">Total</td>
              <td class="text-end"><?= number_format($totals['budget']) ?></td>
              <td class="text-end"><?= number_format($totals['to_date']) ?></td>
              <td class="text-end"><?= number_format($totals['actual']) ?></td>
              <td class="text-end"><?= number_format($totals['balance']) ?></td>
              <td class="text-end <?= ($total_variance < 0) ? 'text-danger' : 'text-success' ?>">
                <?= number_format($total_variance) ?>%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
/* DASHBOARD: load-report click -> single, namespaced, de-duped */
(function () {
  const NS = '.page.dashboard';

  // 1) Kill any legacy (un-namespaced) duplicates already attached
  $(document).off('click', '.load-report');

  // 2) Kill our own namespaced handlers if this fragment is reinserted
  $(document).off(NS);

  // 3) Bind once, namespaced, so PageSandbox.reset() removes it
  $(document).on('click' + NS, '.load-report', function (e) {
    e.preventDefault();
    const url = $(this).data('url');
    const $area = $('#contentArea');
    $area.html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><p class="mt-3">Loading report...</p></div>');
    $.get(url, function (res) {
      $area.html(res);
    }).fail(function () {
      $area.html('<div class="alert alert-danger p-4 text-center">Failed to load report.</div>');
    });
  });

  // 4) Extra safety: let the loader explicitly tear us down on nav
  window.stopEverything = function () { $(document).off(NS); };
})();
</script>
