<?php
// cdma-report-export-department-csv.php

ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

// Require login
if (empty($_SESSION['hris'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Please log in.";
    exit;
}

// DB charset / collation
if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn, 'utf8mb4'); }
@mysqli_query($conn, "SET collation_connection = 'utf8mb4_unicode_ci'");

function norm_space($s){ return trim(preg_replace('/\s+/u',' ', (string)$s)); }

// Validate month (YYYY-MM)
$month = $_GET['month'] ?? '';
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Invalid or missing ?month=YYYY-MM";
    exit;
}

$start = DateTime::createFromFormat('Y-m-d', $month.'-01');
if (!$start) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Invalid month.";
    exit;
}
$end = clone $start; $end->modify('last day of this month');
$startStr = $start->format('Y-m-d');
$endStr   = $end->format('Y-m-d');

/**
 * 1) Build per-contract ratios for the selected month:
 *    ratio(contract) = SUM(tax_total) / SUM(connection subtotals)
 */
$sqlAgg = "
    WITH conn_agg AS (
      SELECT
        c.upload_id,
        c.contract_number,
        SUM(c.subtotal) AS contract_conn_total
      FROM tbl_admin_cdma_monthly_data_connections c
      JOIN tbl_admin_cdma_monthly_data m
        ON m.id = c.upload_id
      WHERE m.bill_period_start <= ? AND m.bill_period_end >= ?
      GROUP BY c.upload_id, c.contract_number
    )
    SELECT
      ca.contract_number,
      SUM(ca.contract_conn_total)  AS connection_total,
      SUM(IFNULL(ch.tax_total, 0)) AS tax_total
    FROM conn_agg ca
    LEFT JOIN tbl_admin_cdma_monthly_data_charges ch
      ON ch.upload_id = ca.upload_id
    GROUP BY ca.contract_number
    ORDER BY ca.contract_number ASC
";
$stmtAgg = mysqli_prepare($conn, $sqlAgg);
mysqli_stmt_bind_param($stmtAgg, 'ss', $endStr, $startStr);
mysqli_stmt_execute($stmtAgg);
$resAgg = mysqli_stmt_get_result($stmtAgg);

$contractRatio = []; // contract_number => ratio
while ($a = mysqli_fetch_assoc($resAgg)) {
    $contract = norm_space($a['contract_number'] ?? '');
    $connTot  = (float)($a['connection_total'] ?? 0);
    $taxTot   = (float)($a['tax_total'] ?? 0);
    $contractRatio[$contract] = ($connTot != 0.0) ? ($taxTot / $connTot) : 0.0;
}
mysqli_stmt_close($stmtAgg);

/**
 * 2) Fetch rows for the month, join allocated_to via TRIMMED connection_no, and aggregate by allocated_to
 */
$sqlRows = "
  SELECT
    m.id AS upload_id,
    c.connection_no,
    c.contract_number,
    c.subtotal,
    d.allocated_to
  FROM tbl_admin_cdma_monthly_data m
  JOIN tbl_admin_cdma_monthly_data_connections c
    ON c.upload_id = m.id
  LEFT JOIN tbl_admin_cdma_details d
    ON d.subscription_number COLLATE utf8mb4_unicode_ci =
       (
         CASE
           WHEN c.connection_no IS NULL THEN ''
           WHEN CHAR_LENGTH(c.connection_no) = 0 THEN ''
           ELSE SUBSTRING(c.connection_no, 1, CHAR_LENGTH(c.connection_no) - 1)
         END
       ) COLLATE utf8mb4_unicode_ci
  WHERE m.bill_period_start <= ? AND m.bill_period_end >= ?
  ORDER BY m.bill_period_start DESC, m.id DESC, c.connection_no ASC
";
$stmtRows = mysqli_prepare($conn, $sqlRows);
mysqli_stmt_bind_param($stmtRows, 'ss', $endStr, $startStr);
mysqli_stmt_execute($stmtRows);
$resRows = mysqli_stmt_get_result($stmtRows);

$groups = []; // allocated_to => ['count'=>n, 'subtotal'=>x, 'tax'=>y, 'total'=>z]
$grandCount = 0;
$grandSub   = 0.0;
$grandTax   = 0.0;
$grandTotal = 0.0;

while ($r = mysqli_fetch_assoc($resRows)) {
    $allocatedTo = norm_space($r['allocated_to'] ?? '');
    $key         = ($allocatedTo !== '') ? $allocatedTo : 'Unknown';

    $contract = norm_space($r['contract_number'] ?? '');
    $sub      = (float)($r['subtotal'] ?? 0);
    $ratio    = isset($contractRatio[$contract]) ? (float)$contractRatio[$contract] : 0.0;
    $tax      = $sub * $ratio;
    $tot      = $sub + $tax;

    if (!isset($groups[$key])) {
        $groups[$key] = ['count'=>0, 'subtotal'=>0.0, 'tax'=>0.0, 'total'=>0.0];
    }
    $groups[$key]['count']    += 1;
    $groups[$key]['subtotal'] += $sub;
    $groups[$key]['tax']      += $tax;
    $groups[$key]['total']    += $tot;

    $grandCount += 1;
    $grandSub   += $sub;
    $grandTax   += $tax;
    $grandTotal += $tot;
}
mysqli_stmt_close($stmtRows);

// Sort by allocated_to (natural, case-insensitive)
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

// ----------------- CSV OUTPUT -----------------
$filename = "CDMA_Group_By_AllocatedTo_{$month}.csv";

// Clean output buffers to avoid BOM/whitespace before headers
while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// Excel-friendly UTF-8 BOM
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, ['Allocated To', 'Connections', 'Subtotal', 'Tax', 'Total']);

// Data rows (keep numeric values plain; 2 decimals, dot separator)
foreach ($groups as $allocatedTo => $v) {
    fputcsv($out, [
        $allocatedTo,
        (int)$v['count'],
        number_format((float)$v['subtotal'], 2, '.', ''),
        number_format((float)$v['tax'],      2, '.', ''),
        number_format((float)$v['total'],    2, '.', ''),
    ]);
}

// Grand totals
fputcsv($out, [
    'Grand Totals',
    (int)$grandCount,
    number_format((float)$grandSub,   2, '.', ''),
    number_format((float)$grandTax,   2, '.', ''),
    number_format((float)$grandTotal, 2, '.', ''),
]);

fclose($out);
exit;
