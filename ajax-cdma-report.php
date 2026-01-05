<?php
// ajax-cdma-report.php
// Returns the table HTML for a selected month (YYYY-MM). No navigation, pure fragment.

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
    // 1) Build per-contract aggregates for the month
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

    $contractTotals = [];
    while ($a = mysqli_fetch_assoc($resAgg)) {
        $contract = norm_space($a['contract_number'] ?? '');
        $connTot  = (float)($a['connection_total'] ?? 0);
        $taxTot   = (float)($a['tax_total'] ?? 0);
        $ratio    = ($connTot != 0.0) ? ($taxTot / $connTot) : 0.0;
        $contractTotals[$contract] = [
            'conn_total' => $connTot,
            'tax_total'  => $taxTot,
            'ratio'      => $ratio
        ];
    }
    mysqli_stmt_close($stmtAgg);

    // ----------------------------------------------------------------------
    // 2) Fetch detailed connection rows for the month
    // ----------------------------------------------------------------------
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
        ON d.subscription_number = CASE
              WHEN c.connection_no IS NULL THEN ''
              WHEN CHAR_LENGTH(c.connection_no) = 0 THEN ''
              ELSE SUBSTRING(c.connection_no, 1, CHAR_LENGTH(c.connection_no) - 1)
           END
      WHERE m.bill_period_start <= ? AND m.bill_period_end >= ?
      ORDER BY m.bill_period_start DESC, m.id DESC, c.connection_no ASC
    ";
    $stmtRows = mysqli_prepare($conn, $sqlRows);
    mysqli_stmt_bind_param($stmtRows, 'ss', $endStr, $startStr);
    mysqli_stmt_execute($stmtRows);
    $resRows = mysqli_stmt_get_result($stmtRows);

    $rows = [];
    $sumSub   = 0.0;
    $sumTax   = 0.0;
    $sumTotal = 0.0;

    while ($r = mysqli_fetch_assoc($resRows)) {
        $connNoRaw = norm_space($r['connection_no'] ?? '');
        $connNo = (strlen($connNoRaw) > 0) ? substr($connNoRaw, 0, -1) : $connNoRaw;
        $allocatedTo = norm_space($r['allocated_to'] ?? '');
        $contract    = norm_space($r['contract_number'] ?? '');
        $sub         = (float)($r['subtotal'] ?? 0);

        $ratio  = isset($contractTotals[$contract]) ? (float)$contractTotals[$contract]['ratio'] : 0.0;
        $taxAmt = $sub * $ratio;
        $total  = $sub + $taxAmt;

        $rows[] = [
            'connection_no'   => $connNo,
            'allocated_to'    => $allocatedTo,
            'contract_number' => $contract,
            'subtotal'        => $sub,
            'tax_ratio'       => $ratio,
            'tax'             => $taxAmt,
            'total'           => $total
        ];
        $sumSub   += $sub;
        $sumTax   += $taxAmt;
        $sumTotal += $total;
    }

    mysqli_stmt_close($stmtRows);

    if (!$rows) {
        echo '<div class="alert alert-info">No data found for the selected month.</div>';
        exit;
    }
    ?>

    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Connection Number</th>
            <th>Contract Number</th>
            <th class="text-end">Connection Sub Total</th>
            <th class="text-end">Tax</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-nowrap">
                <?php
                  $label = $r['connection_no'];
                  if ($r['allocated_to'] !== '') {
                      $label .= ' — ' . $r['allocated_to'];
                  }
                  echo e($label);
                ?>
              </td>
              <td class="text-nowrap"><?= e($r['contract_number']); ?></td>
              <td class="text-end"><?= number_format($r['subtotal'], 2, '.', ','); ?></td>
              <td class="text-end"><?= number_format($r['tax'], 2, '.', ','); ?></td>
              <td class="text-end"><?= number_format($r['total'], 2, '.', ','); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <th class="text-end" colspan="2">Totals:</th>
            <th class="text-end"><?= number_format($sumSub, 2, '.', ','); ?></th>
            <th class="text-end"><?= number_format($sumTax, 2, '.', ','); ?></th>
            <th class="text-end"><?= number_format($sumTotal, 2, '.', ','); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

<?php
// ✅ Detailed user log for AJAX CDMA report view
try {
    require_once 'includes/userlog.php';
    $hris = $_SESSION['hris'] ?? 'UNKNOWN';
    $username = $_SESSION['name'] ?? getUserInfo();

    $monthLabel = 'N/A';
    if (!empty($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $dt = DateTime::createFromFormat('Y-m', $month);
        if ($dt) $monthLabel = $dt->format('F Y');
    }

    $rowCount = isset($rows) ? count($rows) : 0;
    $sumSubLog   = isset($sumSub) ? number_format($sumSub, 2, '.', ',') : '0.00';
    $sumTaxLog   = isset($sumTax) ? number_format($sumTax, 2, '.', ',') : '0.00';
    $sumTotalLog = isset($sumTotal) ? number_format($sumTotal, 2, '.', ',') : '0.00';

    $actionMessage = sprintf(
        '✅ Viewed CDMA Report (AJAX) | Month: %s | Rows Displayed: %d | Subtotal: Rs. %s | Tax: Rs. %s | Total: Rs. %s | HRIS: %s | User: %s',
        $monthLabel,
        $rowCount,
        $sumSubLog,
        $sumTaxLog,
        $sumTotalLog,
        $hris,
        $username
    );
    userlog($actionMessage);
} catch (Throwable $e) {}
?>

<?php
} catch (Throwable $e) {
    http_response_code(500);
    while (ob_get_level()>0) ob_end_clean();
    echo '<pre class="alert alert-danger" style="white-space:pre-wrap;">'.$e->getMessage()."</pre>";
}
