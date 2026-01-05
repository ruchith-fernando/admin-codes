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

    $dialog = dialog_actual($conn, [
        'table'           => 'tbl_admin_mobile_bill_data',
        'period_col'      => 'Update_date',
        'amount_col'      => 'TOTAL_AMOUNT_PAYABLE',
        'number_col'      => 'MOBILE_Number',
        'special_include' => ['765055020'],
        'exclude_numbers' => []
    ], $mlbl, $ym, $ymd01);

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

/* Vehicle Maintenance — unified robust query (created_at fallback) */
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
<style>
  table td { word-wrap: break-word; white-space: normal; }
  /* per-row adjustment UI */
  .adj-note { display:inline-block; font-size: 0.85rem; color:#6c757d; margin-left: 6px; }
  .adj-up { color:#198754; }
  .adj-down { color:#dc3545; }
  .adj-select { width: 84px; }
  .cell-controls { margin-top: 6px; }

  /* export table (for the generated HTML sent to Excel) */
  .export-table { border-collapse: collapse; }
  .export-table th, .export-table td { border: 1px solid #999; padding: 6px 8px; text-align: right; }
  .export-table th:first-child, .export-table td:first-child { text-align: left; }

  .table.table-bordered {
    table-layout: fixed;   /* Enforce widths */
    width: 100%;           /* Stretch full width */
  }

  .table.table-bordered th {
    white-space: normal !important;   /* allow wrapping */
    word-wrap: break-word;            /* break long words if needed */
    vertical-align: middle;           /* keep text centered vertically */
    position: sticky;                 /* 🔥 freeze header */
    top: 0;                           /* stick to top of container */
    z-index: 10;                      /* ensure above cells */
    background: #f8f9fa;              /* match .table-light background */
  }

  /* ✅ scroll container */
  .table-scroll {
    overflow-x: auto;   /* horizontal scroll */
    overflow-y: auto;   /* vertical scroll */
    max-height: 600px;  /* 🔥 required for sticky header */
    max-width: 120%;
    margin-bottom: 1rem;
  }

  .table-scroll table {
    min-width: 1200px; /* force wide table */
  }
</style>


<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Administration Overall Budget Overview</h5>
        <button id="btnExportExcel" class="btn btn-sm btn-success">Download Excel (Adjusted)</button>
      </div>

      <div class="table-scroll">
        <table class="table table-bordered">
          <colgroup>
            <col style="width:40px">    <!-- # -->
            <col style="width:230px">   <!-- Category -->
            <col style="width:180px">   <!-- Budget (Full Year) -->
            <col style="width:180px">   <!-- Budget (To Date) -->
            <col style="width:100px">   <!-- Actual (To Date) -->
            <col style="width:200px">   <!-- Forecast (Year-End) -->
            <col style="width:100px">   <!-- YE Variance vs Budget -->
            <col style="width:100px">   <!-- YE Variance (%) -->
            <col style="width:100px">   <!-- Variance Balance -->
            <col style="width:100px">   <!-- Variance (%) -->
          </colgroup>
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Category</th>
              <th>Budget (Full Year)</th>
              <th>Budget (To Date)</th>
              <th>Actual (To Date)</th>
              <th>Forecast (Year-End)</th>
              <th>YE Variance vs Budget</th>
              <th>YE Variance (%)</th>
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
                  echo "<td style='width:60px;'>{$i}</td>";
                  echo "<td style='width:460px; max-width:460px; word-break:break-word; white-space:normal;'>
                          <span class='load-report text-primary' style='cursor:pointer;' data-url='{$link}'>{$category}</span><br>
                          <span class='text-danger'>($months_text)</span>
                        </td>";

                  echo "<td style='width:180px;' class='text-end budget-fy' data-base='{$budget_full}'>" . number_format($budget_full) .
                      "<span class='adj-note adj-budget-fy'></span>
                        <div class='cell-controls d-flex justify-content-end align-items-center gap-2'>
                          <label class='small text-muted mb-0'>Adjustment:</label>
                          <select class='form-select form-select-sm adj-select adj-budget'>
                            <option value='0'>0%</option>
                            <option value='5'>+5%</option>
                            <option value='10'>+10%</option>
                            <option value='15'>+15%</option>
                            <option value='20'>+20%</option>
                            <option value='25'>+25%</option>
                            <option value='50'>+50%</option>
                            <option value='-5'>-5%</option>
                            <option value='-10'>-10%</option>
                            <option value='-15'>-15%</option>
                            <option value='-20'>-20%</option>
                            <option value='-25'>-25%</option>
                            <option value='-50'>-50%</option>
                          </select>
                        </div>
                      </td>";

                  echo "<td style='width:180px;' class='text-end budget-td' data-base='{$budget_to_date}'>" . number_format($budget_to_date);
                  if ($month_count > 0) {
                      echo "<br><span class='text-danger'>({$month_count} months)</span>";
                  } else {
                      echo "<br><span class='text-danger'>(No months selected to display)</span>";
                  }
                  echo "</td>";

                  echo "<td style='width:180px;' class='text-end actual-td' data-base='{$actual}'>" . number_format($actual) . "</td>";

                  echo "<td style='width:200px;' class='text-end forecast-ye' data-bfy='{$budget_full}' data-btd='{$budget_to_date}' data-act='{$actual}'>
                          <span class='forecast-val'>0</span>
                          <div class='small text-muted forecast-note'></div>
                        </td>";
                  echo "<td style='width:200px;' class='text-end ye-variance-balance'>0</td>";
                  echo "<td style='width:160px;' class='text-end ye-variance-pct'>0%</td>";

                  echo "<td style='width:200px;' class='text-end'>" . number_format($balance) . "</td>";
                  echo "<td style='width:160px;' class='text-end {$variance_class}'>" . number_format($variance) . "%</td>";
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
            <tr id="totals-row" class="fw-bold table-light">
              <td colspan="2" class="text-start">Total</td>
              <td class="text-end budget-fy-total" data-base="<?= (float)$totals['budget'] ?>">
                <?= number_format($totals['budget']) ?>
                <span class="adj-note adj-budget-fy-total"></span>
              </td>
              <td class="text-end budget-td-total" data-base="<?= (float)$totals['to_date'] ?>">
                <?= number_format($totals['to_date']) ?>
              </td>
              <td class="text-end actual-td-total" data-base="<?= (float)$totals['actual'] ?>">
                <?= number_format($totals['actual']) ?>
              </td>
              <td class="text-end forecast-ye-total">0</td>
              <td class="text-end ye-variance-balance-total">0</td>
              <td class="text-end ye-variance-pct-total">0%</td>
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

  // Clear old handlers and namespace
  $(document).off('click', '.load-report');
  $(document).off(NS);

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

  window.stopEverything = function () { $(document).off(NS); };

  // -------------------- Helpers --------------------
  function nf(x) { return Number(Math.round(x)).toLocaleString('en-US'); }
  function clamp0(x){ return x < 0 ? 0 : x; }

  // Note for remaining months (shown under Budget FY cell)
  function buildRemainingNote(remBase, pct) {
    if (!pct) return '';
    const adjRem  = remBase * (1 + pct/100);
    const diffRem = adjRem - remBase;
    const signCls = diffRem >= 0 ? 'adj-up' : 'adj-down';
    const pctLabel = (pct >= 0 ? '+' : '') + pct + '%';
    const diffLabel = (diffRem >= 0 ? '+' : '') + nf(diffRem);
    return ` <span class="${signCls}">(Remaining ${pctLabel} ⇒ ${nf(adjRem)} [${diffLabel}])</span>`;
  }

  // -------------------- Core calcs --------------------
  function recalcRow($tr) {
    const bfy = parseFloat($tr.find('td.budget-fy').data('base')) || 0;
    const btd = parseFloat($tr.find('td.budget-td').data('base')) || 0;
    const act = parseFloat($tr.find('td.actual-td').data('base')) || 0;
    const pct = parseFloat($tr.find('.adj-budget').val() || '0');

    const remainingBase = clamp0(bfy - btd);
    const remainingAdj  = remainingBase * (1 + pct/100);
    const forecastYE    = act + remainingAdj;                 // Actual + adjusted remaining
    const yeVarBalance  = bfy - forecastYE;                   // positive = under budget
    const yeVarPct      = bfy > 0 ? (yeVarBalance / bfy) * 100 : 0;

    // Update Budget FY note (about remaining impact)
    $tr.find('.adj-budget-fy').html(buildRemainingNote(remainingBase, pct));

    // Update Forecast + YE variances
    $tr.find('.forecast-val').text(nf(forecastYE));
    $tr.find('.forecast-note').text(remainingBase ? `Remaining base ${nf(remainingBase)} → adj ${nf(remainingAdj)}` : 'No remaining');

    const $bal = $tr.find('.ye-variance-balance');
    const $pct = $tr.find('.ye-variance-pct');
    $bal.text(nf(yeVarBalance))
        .toggleClass('text-success', yeVarBalance >= 0)
        .toggleClass('text-danger',  yeVarBalance < 0);
    $pct.text((yeVarPct >= 0 ? '' : '') + Math.round(yeVarPct) + '%')
        .toggleClass('text-success', yeVarPct >= 0)
        .toggleClass('text-danger',  yeVarPct < 0);
  }

  function recalcTotals() {
    let sumBFY = 0, sumBtd = 0, sumAct = 0, sumForecast = 0;

    $('tbody tr').each(function() {
      if ($(this).attr('id') === 'totals-row') return;
      const $tr = $(this);

      // Recompute per row to get current forecast
      const bfy = parseFloat($tr.find('td.budget-fy').data('base')) || 0;
      const btd = parseFloat($tr.find('td.budget-td').data('base')) || 0;
      const act = parseFloat($tr.find('td.actual-td').data('base')) || 0;
      const pct = parseFloat($tr.find('.adj-budget').val() || '0');

      const remainingBase = Math.max(bfy - btd, 0);
      const remainingAdj  = remainingBase * (1 + pct/100);
      const forecastYE    = act + remainingAdj;

      sumBFY      += bfy;
      sumBtd      += btd;
      sumAct      += act;
      sumForecast += forecastYE;
    });

    // Totals: show overall Budget FY adj note (vs base totals)
    const $bfyTot = $('.budget-fy-total');
    const baseBFY = parseFloat($bfyTot.data('base')) || 0;

    // Totals: set Forecast YE and YE variances
    $('.forecast-ye-total').text(nf(sumForecast));

    const yeBalTotal = sumBFY - sumForecast; // positive = under budget
    const yePctTotal = sumBFY > 0 ? (yeBalTotal / sumBFY) * 100 : 0;

    $('.ye-variance-balance-total')
      .text(nf(yeBalTotal))
      .toggleClass('text-success', yeBalTotal >= 0)
      .toggleClass('text-danger',  yeBalTotal < 0);

    $('.ye-variance-pct-total')
      .text(Math.round(yePctTotal) + '%')
      .toggleClass('text-success', yePctTotal >= 0)
      .toggleClass('text-danger',  yePctTotal < 0);

    // Optional: show how far the sum of remaining-adj differs from base totals
    const diffBFY = (sumBFY - baseBFY); // this is usually 0 unless rows changed externally
    $('.adj-budget-fy-total').html(
      diffBFY !== 0
        ? ` <span class="${diffBFY>=0?'adj-up':'adj-down'}">(base total change ${diffBFY>=0?'+':''}${nf(diffBFY)})</span>`
        : ''
    );
  }

  function applyAll() {
    $('tbody tr').each(function(){
      if ($(this).attr('id') === 'totals-row') return;
      recalcRow($(this));
    });
    recalcTotals();
  }

  $(document).on('change' + NS, '.adj-budget', function(){
    const $tr = $(this).closest('tr');
    recalcRow($tr);
    recalcTotals();
  });

  applyAll();

  function raw(x){ return Number(x) || 0; }

  function buildExportTableHtml() {
    let html = '';
    html += '<html><head><meta charset="UTF-8"><style>';
    html += '.export-table{border-collapse:collapse} .export-table th,.export-table td{border:1px solid #999;padding:6px 8px;text-align:right} .export-table th:first-child,.export-table td:first-child{text-align:left}';
    html += '</style></head><body>';
    html += '<table class="export-table">';
    html += '<thead><tr>';
    html += '<th>#</th>';
    html += '<th>Category</th>';
    html += '<th>Budget (Full Year) - Base</th>';
    html += '<th>Budget (To Date)</th>';
    html += '<th>Remaining Base</th>';
    html += '<th>Remaining Adj %</th>';
    html += '<th>Remaining Adjusted</th>';
    html += '<th>Actual (To Date)</th>';
    html += '<th>Forecast (Year-End)</th>';
    html += '<th>YE Variance vs Budget</th>';
    html += '<th>YE Variance (%)</th>';
    html += '<th>Variance Balance (To Date)</th>';
    html += '<th>Variance % (To Date)</th>';
    html += '</tr></thead><tbody>';

    let rowIdx = 0;
    let sumBFY=0,sumBtd=0,sumAct=0,sumForecast=0;

    $('tbody tr').each(function() {
      if ($(this).attr('id') === 'totals-row') return;
      const $tr = $(this);

      const catText = $tr.find('td').eq(1).find('.load-report').text().trim();

      const bfy = raw($tr.find('td.budget-fy').data('base'));
      const btd = raw($tr.find('td.budget-td').data('base'));
      const act = raw($tr.find('td.actual-td').data('base'));
      const pct = raw($tr.find('.adj-budget').val());

      const remainingBase = Math.max(bfy - btd, 0);
      const remainingAdj  = remainingBase * (1 + pct/100);
      const forecastYE    = act + remainingAdj;

      const yeVarBalance  = bfy - forecastYE;                       // positive = under budget
      const yeVarPct      = bfy > 0 ? (yeVarBalance / bfy) * 100 : 0;

      // existing to-date variance (already shown on page)
      const balToDate     = btd - act;
      const varPctToDate  = btd > 0 ? ((btd - act) / btd) * 100 : 0;

      rowIdx++;
      sumBFY += bfy; sumBtd += btd; sumAct += act; sumForecast += forecastYE;

      html += '<tr>';
      html += '<td>' + rowIdx + '</td>';
      html += '<td>' + catText + '</td>';
      html += '<td>' + bfy + '</td>';
      html += '<td>' + btd + '</td>';
      html += '<td>' + remainingBase + '</td>';
      html += '<td>' + (pct>=0?'+':'') + pct + '%</td>';
      html += '<td>' + remainingAdj + '</td>';
      html += '<td>' + act + '</td>';
      html += '<td>' + forecastYE + '</td>';
      html += '<td>' + yeVarBalance + '</td>';
      html += '<td>' + Math.round(yeVarPct) + '%</td>';
      html += '<td>' + balToDate + '</td>';
      html += '<td>' + Math.round(varPctToDate) + '%</td>';
      html += '</tr>';
    });

    const yeBalTotal = sumBFY - sumForecast;
    const yePctTotal = sumBFY > 0 ? (yeBalTotal / sumBFY) * 100 : 0;
    const balToDateTotal = sumBtd - sumAct;
    const varPctToDateTotal = sumBtd > 0 ? (balToDateTotal / sumBtd) * 100 : 0;

    html += '<tr>';
    html += '<th colspan="2" style="text-align:left;">Total</th>';
    html += '<th>' + sumBFY + '</th>';
    html += '<th>' + sumBtd + '</th>';
    html += '<th>' + Math.max(sumBFY - sumBtd, 0) + '</th>';
    html += '<th>—</th>';
    html += '<th>—</th>';
    html += '<th>' + sumAct + '</th>';
    html += '<th>' + sumForecast + '</th>';
    html += '<th>' + yeBalTotal + '</th>';
    html += '<th>' + Math.round(yePctTotal) + '%</th>';
    html += '<th>' + balToDateTotal + '</th>';
    html += '<th>' + Math.round(varPctToDateTotal) + '%</th>';
    html += '</tr>';

    html += '</tbody></table></body></html>';
    return html;
  }

  $(document).on('click' + NS, '#btnExportExcel', function () {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const filename = `Admin_Budget_Overview_Adjusted_${y}-${m}-${d}.xls`;

    const tableHtml = buildExportTableHtml();
    const blob = new Blob([tableHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });

    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
      window.navigator.msSaveOrOpenBlob(blob, filename);
      return;
    }

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });

})();
</script>
