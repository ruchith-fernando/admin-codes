<?php
// cdma-report-export-csv-detailed.php
// CSV download for the main CDMA report (per-connection detail) for a selected month (YYYY-MM)

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
 * 1) Build per-contract tax ratio for the month:
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
 * 2) Fetch detailed rows for the month.
 *    Join allocated_to via TRIMMED connection_no (drop last character).
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
  ORDER BY c.contract_number ASC, c.connection_no ASC
";
$stmtRows = mysqli_prepare($conn, $sqlRows);
mysqli_stmt_bind_param($stmtRows, 'ss', $endStr, $startStr);
mysqli_stmt_execute($stmtRows);
$resRows = mysqli_stmt_get_result($stmtRows);

// ----------------- Aggregate totals while we write rows -----------------
$grandSub   = 0.0;
$grandTax   = 0.0;
$grandTotal = 0.0;

// ----------------- CSV OUTPUT -----------------
$filename = "CDMA_Detail_{$month}.csv";

// Clean output buffers before headers
while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// Excel-friendly UTF-8 BOM
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
  'Connection No',
  'Allocated To',
  'Contract Number',
  'Subtotal',
  'Tax',
  'Total'
]);

// Data rows
while ($r = mysqli_fetch_assoc($resRows)) {
    $connNo    = norm_space($r['connection_no'] ?? '');
    $allocated = norm_space($r['allocated_to'] ?? '');
    if ($allocated === '') $allocated = 'Unknown';

    $contract  = norm_space($r['contract_number'] ?? '');
    $sub       = (float)($r['subtotal'] ?? 0);
    $ratio     = isset($contractRatio[$contract]) ? (float)$contractRatio[$contract] : 0.0;
    $tax       = $sub * $ratio;
    $tot       = $sub + $tax;

    $grandSub   += $sub;
    $grandTax   += $tax;
    $grandTotal += $tot;

    fputcsv($out, [
        $connNo,
        $allocated,
        $contract,
        number_format($sub, 2, '.', ''),
        number_format($tax, 2, '.', ''),
        number_format($tot, 2, '.', ''),
    ]);
}
mysqli_stmt_close($stmtRows);

// Grand totals
fputcsv($out, [
    'Grand Totals',
    '', // Allocated To (blank)
    '', // Contract Number (blank)
    number_format($grandSub,   2, '.', ''),
    number_format($grandTax,   2, '.', ''),
    number_format($grandTotal, 2, '.', ''),
]);

fclose($out);

// ✅ Detailed user log for CDMA CSV export
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();

    // Format month as "Month Year"
    $monthLabel = 'N/A';
    if (!empty($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $dt = DateTime::createFromFormat('Y-m', $month);
        if ($dt) $monthLabel = $dt->format('F Y');
    }

    $actionMessage = sprintf(
        '✅ Exported CDMA Detailed CSV | Month: %s | Subtotal: Rs. %s | Tax: Rs. %s | Total: Rs. %s | HRIS: %s | User: %s',
        $monthLabel,
        number_format($grandSub, 2, '.', ','),
        number_format($grandTax, 2, '.', ','),
        number_format($grandTotal, 2, '.', ','),
        $hris,
        $username
    );

    userlog($actionMessage);
} catch (Throwable $e) {}

exit;
