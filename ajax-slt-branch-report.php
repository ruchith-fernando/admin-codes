<?php
// ajax-slt-branch-report.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

ob_start();
ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

header('Content-Type: text/html; charset=UTF-8');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function norm_space($s){ return trim(preg_replace('/\s+/u',' ', (string)$s)); }
function fmt_ratio($x, $dec = 8){ return number_format((float)$x, $dec, '.', ','); } // kept for potential debugging

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

    // Pull connections, the upload’s full subtotal, and the upload’s discount_total to compute ratio
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
        $discount_total= (float)$r['discount_total'];   // positive figure saved at upload time
        $full_subtotal = (float)$r['full_subtotal'];

        // Discount ratio per upload (internal only), 0 if denominator is zero
        $ratio     = ($full_subtotal > 0.0) ? ($discount_total / $full_subtotal) : 0.0;

        // Per-connection values
        $disc_amt   = $conn_subtotal * $ratio;
        $after_disc = $conn_subtotal - $disc_amt;

        // Temp placeholders; tax allocated later after we know the global tax ratio
        $rows[] = [
            'upload_id'      => $upload_id,
            'branch_name'    => $branch_name,
            'conn_subtotal'  => $conn_subtotal,
            'disc_amt'       => $disc_amt,
            'after_disc'     => $after_disc,
            'tax'            => 0.00,
            'total'          => 0.00
        ];

        $uploadIdsSet[$upload_id] = true;
    }
    mysqli_stmt_close($stmt);

    if (!$rows) {
        echo '<div class="alert alert-info">No data found for the selected month.</div>';
        exit;
    }

    // -----------------------------
    // TAX COMPONENT & TAX RATIO
    // -----------------------------
    // 1) After Discount Total (Σ after_disc)
    $afterDiscTotal = 0.0;
    foreach ($rows as $r) { $afterDiscTotal += $r['after_disc']; }

    // 2) Tax Component (sum taxes across included uploads for this month)
    $uploadIds    = array_keys($uploadIdsSet);
    $taxComponent = 0.0;

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

    // 3) Global Tax Ratio
    $taxRatio = ($afterDiscTotal > 0.0) ? ($taxComponent / $afterDiscTotal) : 0.0;

    // 4) Allocate tax and compute totals per row; track grand totals
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
    // If branch_name is empty, group under "(Unassigned)"
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

    // --- UI (only Branch + Total; audit line & other columns hidden) ---
    ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Branch</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $branch => $g): ?>
            <tr>
              <td class="text-nowrap"><?= e($branch); ?></td>
              <td class="text-end"><?= number_format($g['total'], 2, '.', ','); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <th class="text-end">Totals:</th>
            <th class="text-end"><?= number_format($sumTotal, 2, '.', ','); ?></th>
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
