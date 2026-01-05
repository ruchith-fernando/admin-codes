<?php
// ajax-cdma-report-group-by-contract.php
// Grouped summary by CONTRACT NUMBER for a selected month (YYYY-MM).

// Disable caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');


ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

header('Content-Type: text/html; charset=UTF-8');

// Normalize connection charset/collation to avoid mix issues
if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn, 'utf8mb4'); }
@mysqli_query($conn, "SET collation_connection = 'utf8mb4_unicode_ci'");

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function norm_space($s){ return trim(preg_replace('/\s+/u',' ', (string)$s)); }

try {
    if (empty($_SESSION['hris'])) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Please log in.</div>';
        exit;
    }

    $month = $_GET['month'] ?? ''; // expect 'YYYY-MM'
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        echo '<div class="text-muted">Please select a valid month.</div>';
        exit;
    }

    $start = DateTime::createFromFormat('Y-m-d', $month.'-01');
    if (!$start) { echo '<div class="text-muted">Please select a valid month.</div>'; exit; }
    $end = clone $start; $end->modify('last day of this month');
    $endStr = $end->format('Y-m-d');
    $startStr = $start->format('Y-m-d');

    // ----------------------------------------------------------------------
    // 1) Build per-contract aggregates for the month to compute tax ratio
    //    ratio(contract) = SUM(tax_total) / SUM(connection subtotals)
    // ----------------------------------------------------------------------
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

    // ----------------------------------------------------------------------
    // 2) Fetch rows and group by contract_number
    // ----------------------------------------------------------------------
    $sqlRows = "
      SELECT
        m.id AS upload_id,
        c.connection_no,
        c.contract_number,
        c.subtotal
      FROM tbl_admin_cdma_monthly_data m
      JOIN tbl_admin_cdma_monthly_data_connections c
        ON c.upload_id = m.id
      WHERE m.bill_period_start <= ? AND m.bill_period_end >= ?
      ORDER BY m.bill_period_start DESC, m.id DESC, c.contract_number ASC, c.connection_no ASC
    ";
    $stmtRows = mysqli_prepare($conn, $sqlRows);
    mysqli_stmt_bind_param($stmtRows, 'ss', $endStr, $startStr);
    mysqli_stmt_execute($stmtRows);
    $resRows = mysqli_stmt_get_result($stmtRows);

    $groups = []; // contract_number => ['count'=>n, 'subtotal'=>x, 'tax'=>y, 'total'=>z]
    $grandSub = $grandTax = $grandTotal = 0.0;
    $grandCount = 0;

    while ($r = mysqli_fetch_assoc($resRows)) {
        $contract  = norm_space($r['contract_number'] ?? '');
        $groupKey  = ($contract !== '') ? $contract : 'Unknown';

        $sub       = (float)($r['subtotal'] ?? 0);
        $ratio     = isset($contractRatio[$contract]) ? (float)$contractRatio[$contract] : 0.0;
        $taxAmt    = $sub * $ratio;
        $total     = $sub + $taxAmt;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = ['count'=>0, 'subtotal'=>0.0, 'tax'=>0.0, 'total'=>0.0];
        }
        $groups[$groupKey]['count']    += 1;
        $groups[$groupKey]['subtotal'] += $sub;
        $groups[$groupKey]['tax']      += $taxAmt;
        $groups[$groupKey]['total']    += $total;

        $grandCount += 1;
        $grandSub   += $sub;
        $grandTax   += $taxAmt;
        $grandTotal += $total;
    }
    mysqli_stmt_close($stmtRows);

    if (!$groups) {
        echo '<div class="alert alert-info">No data found for the selected month.</div>';
        exit;
    }

    // Sort by contract number ASC (natural). To sort by total desc, replace with uasort comparing ['total'].
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    ?>

    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Contract Number</th>
            <th class="text-end">Connections</th>
            <th class="text-end">Subtotal</th>
            <th class="text-end">Tax</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $contract => $v): ?>
            <tr>
              <td class="text-nowrap"><?= e($contract); ?></td>
              <td class="text-end"><?= number_format($v['count']); ?></td>
              <td class="text-end"><?= number_format($v['subtotal'], 2, '.', ','); ?></td>
              <td class="text-end"><?= number_format($v['tax'], 2, '.', ','); ?></td>
              <td class="text-end"><?= number_format($v['total'], 2, '.', ','); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <th class="text-end">Grand Totals:</th>
            <th class="text-end"><?= number_format($grandCount); ?></th>
            <th class="text-end"><?= number_format($grandSub, 2, '.', ','); ?></th>
            <th class="text-end"><?= number_format($grandTax, 2, '.', ','); ?></th>
            <th class="text-end"><?= number_format($grandTotal, 2, '.', ','); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php

} catch (Throwable $e) {
    http_response_code(500);
    while (ob_get_level()>0) ob_end_clean();
    echo '<pre class="alert alert-danger" style="white-space:pre-wrap;">'.$e->getMessage()."</pre>";
}
