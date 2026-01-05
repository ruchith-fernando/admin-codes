<?php
// slt-report-export-csv.php
ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

function bail($msg) {
  header('Content-Type: text/plain; charset=UTF-8');
  while (ob_get_level()>0) ob_end_clean();
  echo $msg;
  exit;
}
function norm_space($s){ return trim(preg_replace('/\s+/u',' ', (string)$s)); }
function canon_conn($s){ $s = preg_replace('/[^A-Za-z0-9]+/', '', (string)$s); return strtoupper($s ?? ''); }

// --- Blocklist setup (fix for in_array() warning) ---------------------------
// Match the table’s blocklist exactly. Keep empty if none.
// If you maintain a list elsewhere, you can overwrite this before use.
$BLOCKED = []; // e.g., ['CEN2458800'];
// ---------------------------------------------------------------------------

try {
    if (empty($_SESSION['hris'])) bail('Please log in.');

    $month = $_GET['month'] ?? ''; // expect 'YYYY-MM'
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) bail('Invalid month. Use YYYY-MM.');

    $start = DateTime::createFromFormat('Y-m-d', $month.'-01');
    if (!$start) bail('Invalid month.');
    $end = clone $start; $end->modify('last day of this month');

    // Pull connections + per-upload totals to compute discount ratio
    $sql = "
      SELECT
        m.id AS upload_id,
        c.connection_no,
        c.subtotal AS conn_subtotal,
        IFNULL(ch.discount_total,0) AS discount_total,
        IFNULL(tot.full_subtotal,0) AS full_subtotal,
        IFNULL(b.allocated_to,'') AS branch_name
      FROM tbl_admin_slt_monthly_data m
      JOIN tbl_admin_slt_monthly_data_connections c ON c.upload_id = m.id
      LEFT JOIN tbl_admin_slt_monthly_data_charges ch ON ch.upload_id = m.id
      LEFT JOIN (
          SELECT upload_id, SUM(subtotal) AS full_subtotal
          FROM tbl_admin_slt_monthly_data_connections
          GROUP BY upload_id
      ) tot ON tot.upload_id = m.id
      LEFT JOIN tbl_admin_slt_branches b ON b.connection_number = c.connection_no
      WHERE m.bill_period_start <= ? AND m.bill_period_end >= ?
      ORDER BY m.bill_period_start DESC, m.id DESC, c.connection_no ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) bail('Query prepare failed.');
    $endStr = $end->format('Y-m-d');
    $startStr = $start->format('Y-m-d');
    mysqli_stmt_bind_param($stmt, 'ss', $endStr, $startStr);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    $uploadIdsSet = [];

    while ($r = mysqli_fetch_assoc($res)) {
        $conn_no_raw = norm_space($r['connection_no']);
        $canon = canon_conn($conn_no_raw);

        // Guarded in_array() so $BLOCKED can safely be empty
        if (!empty($BLOCKED) && in_array($canon, $BLOCKED, true)) continue; // enforce blocklist

        $upload_id      = (int)$r['upload_id'];
        $conn_no        = $conn_no_raw;
        $branch_name    = norm_space($r['branch_name'] ?? '');
        $conn_subtotal  = (float)$r['conn_subtotal'];
        $discount_total = (float)$r['discount_total'];
        $full_subtotal  = (float)$r['full_subtotal'];

        // Discount ratio per upload (0 if denominator = 0)
        $ratio = ($full_subtotal > 0.0) ? ($discount_total / $full_subtotal) : 0.0;

        // Per-connection values before tax
        $disc_amt   = $conn_subtotal * $ratio;
        $after_disc = $conn_subtotal - $disc_amt;

        $rows[] = [
            'upload_id'      => $upload_id,
            'connection_no'  => $conn_no,
            'branch_name'    => $branch_name,
            'conn_subtotal'  => $conn_subtotal,
            'ratio'          => $ratio,   // internal only
            'disc_amt'       => $disc_amt,
            'after_disc'     => $after_disc,
            'tax_ratio'      => 0.0,      // internal only
            'tax'            => 0.00,
            'total'          => 0.00
        ];
        $uploadIdsSet[$upload_id] = true;
    }
    mysqli_stmt_close($stmt);

    // Compute tax component exactly as in the table (sum across included uploads)
    $afterDiscTotal = 0.0;
    foreach ($rows as $r) $afterDiscTotal += $r['after_disc'];

    $taxComponent = 0.0;
    $uploadIds = array_keys($uploadIdsSet);
    if ($uploadIds) {
        $idList = implode(',', array_map('intval', $uploadIds));
        // NOTE: Keep this expression aligned with the table version
        $sqlTax = "
            SELECT
              SUM(IFNULL(ch.tax_total, 0)) AS tax_component
            FROM tbl_admin_slt_monthly_data_charges ch
            WHERE ch.upload_id IN ($idList)
        ";
        $resTax = mysqli_query($conn, $sqlTax);
        if ($resTax && ($rowTax = mysqli_fetch_assoc($resTax))) {
            $taxComponent = (float)$rowTax['tax_component'];
        }
        if ($resTax) mysqli_free_result($resTax);
    }

    $taxRatio = ($afterDiscTotal > 0.0) ? ($taxComponent / $afterDiscTotal) : 0.0;

    // Allocate tax & compute totals, plus grand totals
    $sumConn  = 0.0; $sumDisc = 0.0; $sumAfter = 0.0; $sumTax = 0.0; $sumTotal = 0.0;
    foreach ($rows as $i => $r) {
        $taxAlloc = $r['after_disc'] * $taxRatio;
        $finalTot = $r['after_disc'] + $taxAlloc;

        $rows[$i]['tax_ratio'] = $taxRatio; // internal
        $rows[$i]['tax']       = $taxAlloc;
        $rows[$i]['total']     = $finalTot;

        $sumConn  += $r['conn_subtotal'];
        $sumDisc  += $r['disc_amt'];
        $sumAfter += $r['after_disc'];
        $sumTax   += $taxAlloc;
        $sumTotal += $finalTot;
    }

    // --- log the download (HRIS, month, IP, rows) ---
    @mkdir(__DIR__ . '/logs', 0777, true);
    $logLine = sprintf(
        "[%s] hris=%s action=download_slt_report_csv month=%s ip=%s rows=%d\n",
        date('Y-m-d H:i:s'),
        (string)($_SESSION['hris'] ?? 'unknown'),
        $month,
        (string)($_SERVER['REMOTE_ADDR'] ?? '-'),
        count($rows)
    );
    @file_put_contents(__DIR__.'/logs/downloads.log', $logLine, FILE_APPEND);

    // --- stream CSV (columns & values mirror the HTML table) ---
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="slt-report-'.$month.'.csv"');
    // UTF-8 BOM (Excel-friendly)
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Header (same as table)
    fputcsv($out, [
        'Connection Number',
        'Connection Sub Total',
        'Discount Amount',
        'After Discount',
        'Tax',
        'Total'
    ]);

    // Rows
    foreach ($rows as $r) {
        $dispConn = trim($r['connection_no'] . ($r['branch_name'] !== '' ? ' - ' . $r['branch_name'] : ''));
        fputcsv($out, [
            $dispConn,
            number_format($r['conn_subtotal'], 2, '.', ''),
            number_format($r['disc_amt'],      2, '.', ''),
            number_format($r['after_disc'],    2, '.', ''),
            number_format($r['tax'],           2, '.', ''),
            number_format($r['total'],         2, '.', '')
        ]);
    }

    // Totals row (parity with table footer)
    fputcsv($out, []);
    fputcsv($out, [
        'Totals:',
        number_format($sumConn,  2, '.', ''),
        number_format($sumDisc,  2, '.', ''),
        number_format($sumAfter, 2, '.', ''),
        number_format($sumTax,   2, '.', ''),
        number_format($sumTotal, 2, '.', '')
    ]);

    fclose($out);
    exit;

} catch (Throwable $e) {
    bail($e->getMessage());
}
