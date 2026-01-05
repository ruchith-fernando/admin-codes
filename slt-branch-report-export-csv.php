<?php
// slt-branch-report-export-csv.php
// Streams a CSV grouped by BRANCH for a selected month (YYYY-MM).
// CSV shows only Branch + Total; all other calculations remain in code but are hidden.
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

// Optional blocklist placeholder (not used here)
$BLOCKED = [];

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
      ORDER BY m.bill_period_start DESC, m.id DESC
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
        $upload_id     = (int)$r['upload_id'];
        $branch_name   = norm_space($r['branch_name'] ?? '');
        $conn_subtotal = (float)$r['conn_subtotal'];
        $discount_total= (float)$r['discount_total'];
        $full_subtotal = (float)$r['full_subtotal'];

        // Discount ratio per upload (0 if denominator = 0)
        $ratio     = ($full_subtotal > 0.0) ? ($discount_total / $full_subtotal) : 0.0;

        // Per-connection values before tax
        $disc_amt   = $conn_subtotal * $ratio;
        $after_disc = $conn_subtotal - $disc_amt;

        $rows[] = [
            'upload_id'      => $upload_id,
            'branch_name'    => $branch_name, // group key
            'conn_subtotal'  => $conn_subtotal,
            'disc_amt'       => $disc_amt,
            'after_disc'     => $after_disc,
            'tax'            => 0.00,         // fill later
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

        $rows[$i]['tax']   = $taxAlloc;
        $rows[$i]['total'] = $finalTot;

        $sumConn  += $r['conn_subtotal'];
        $sumDisc  += $r['disc_amt'];
        $sumAfter += $r['after_disc'];
        $sumTax   += $taxAlloc;
        $sumTotal += $finalTot;
    }

    // --- GROUP BY BRANCH NAME ---
    $groups = []; // branch_name => sums
    foreach ($rows as $r) {
        $b = $r['branch_name'] !== '' ? $r['branch_name'] : '(Unassigned)';
        if (!isset($groups[$b])) {
            $groups[$b] = [
                'conn_subtotal' => 0.0,
                'disc_amt'      => 0.0,
                'after_disc'    => 0.0,
                'tax'           => 0.0,
                'total'         => 0.0,
            ];
        }
        $groups[$b]['conn_subtotal'] += $r['conn_subtotal'];
        $groups[$b]['disc_amt']      += $r['disc_amt'];
        $groups[$b]['after_disc']    += $r['after_disc'];
        $groups[$b]['tax']           += $r['tax'];
        $groups[$b]['total']         += $r['total'];
    }

    // Sort branches alphabetically (case-insensitive)
    uksort($groups, static function($a,$b){ return strcasecmp($a,$b); });

    // --- log the download (HRIS, month, IP, groups) ---
    @mkdir(__DIR__ . '/logs', 0777, true);
    $logLine = sprintf(
        "[%s] hris=%s action=download_slt_branch_report_csv_slim month=%s ip=%s groups=%d\n",
        date('Y-m-d H:i:s'),
        (string)($_SESSION['hris'] ?? 'unknown'),
        $month,
        (string)($_SERVER['REMOTE_ADDR'] ?? '-'),
        count($groups)
    );
    @file_put_contents(__DIR__.'/logs/downloads.log', $logLine, FILE_APPEND);

    // --- stream CSV (GROUPED, only Branch + Total) ---
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="slt-branch-report-' . $month . '.csv"');
    // UTF-8 BOM (Excel-friendly)
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Header (slim view)
    fputcsv($out, ['Branch', 'Total']);

    // Rows (each branch one line)
    foreach ($groups as $branch => $g) {
        fputcsv($out, [
            $branch,
            number_format($g['total'], 2, '.', '')
        ]);
    }

    // Totals row
    fputcsv($out, []);
    fputcsv($out, ['Totals:', number_format($sumTotal, 2, '.', '')]);

    fclose($out);
    exit;

} catch (Throwable $e) {
    bail($e->getMessage());
}
