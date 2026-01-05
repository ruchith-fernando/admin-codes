  <?php
  // telephone-budget-fetch.php
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');

  require_once 'connections/connection.php';
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

  $user_id = $_SESSION['hris'] ?? ''; // 👈 HRIS from session
  $category_for_selection = 'Telephone Bills';

  /* ===== CONFIG ===== */
  $BUDGET = [
    'table'      => 'tbl_admin_budget_telephone',
    'period_col' => 'budget_month',
  ];

  $CDMA = [
    'monthly' => 'tbl_admin_cdma_monthly_data',
    'charges' => 'tbl_admin_cdma_monthly_data_charges',
    'conns'   => 'tbl_admin_cdma_monthly_data_connections',
    'col' => [
      'bill_start' => 'bill_period_start',
      'bill_end'   => 'bill_period_end',
      'upload_id'  => 'upload_id',
      'subtotal'   => 'subtotal',
      'tax_total'  => 'tax_total',
    ],
  ];

  $SLT = [
    'monthly' => 'tbl_admin_slt_monthly_data',
    'charges' => 'tbl_admin_slt_monthly_data_charges',
    'conns'   => 'tbl_admin_slt_monthly_data_connections',
    'col' => [
      'bill_start' => 'bill_period_start',
      'bill_end'   => 'bill_period_end',
      'upload_id'  => 'upload_id',
      'subtotal'   => 'subtotal',
      'tax_total'  => 'tax_total',
    ],
  ];

  /* ===== Helpers ===== */
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

  function fy_months($from='2025-04-01', $to='2026-03-01'){
    $out=[]; $d=new DateTime($from); $end=new DateTime($to);
    while($d <= $end){ $out[]=$d->format('F Y'); $d->modify('+1 month'); }
    return $out;
  }

  function month_bounds($label){
    $s = DateTime::createFromFormat('F Y', $label);
    if(!$s) return [null,null,null,null];
    $s->modify('first day of this month');
    $e=clone $s; $e->modify('last day of this month');
    return [$s->format('Y-m-d'), $e->format('Y-m-d'), $s->format('Y-m'), $s->format('Y-m-01')];
  }

  function fetch_budget_shared(mysqli $conn, array $BUDGET, string $month_label): float {
    $tbl = $BUDGET['table']; $pc = $BUDGET['period_col'];
    $st = $conn->prepare("SELECT SUM(budget_amount) b FROM $tbl WHERE $pc=?");
    $st->bind_param('s', $month_label);
    $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    return (float)($r['b'] ?? 0);
  }

  /* ===== Dialog actuals from figures table ===== */
  function dialog_actual(mysqli $conn, string $month_label): float {
    // Convert "January 2025" → "January-2025"
    $m_dash = str_replace(' ', '-', $month_label); 
    
    $sql = "SELECT dialog_bill_amount 
            FROM tbl_admin_dialog_figures 
            WHERE billing_month = ?";
    $st = $conn->prepare($sql);
    $st->bind_param('s', $m_dash);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    
    return (float)($r['dialog_bill_amount'] ?? 0.0);
  }

  /* ===== Ratio-based actual for CDMA/SLT ===== */
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

  /* ===== Build report ===== */
  $months = fy_months('2025-04-01', '2026-03-01');

  $rows = [];
  $tot_budget = $tot_actual = $tot_dialog = $tot_cdma = $tot_slt = 0.0;

  /* ✅ PER-USER selection (HRIS) */
  $selStmt = null;
  if ($user_id !== '') {
    $selStmt = $conn->prepare("
      SELECT is_selected 
      FROM tbl_admin_dashboard_month_selection 
      WHERE category=? AND month_name=? AND user_id=? 
      LIMIT 1
    ");
  }

  foreach ($months as $mlbl) {
    [$start,$end,$ym,$ymd01] = month_bounds($mlbl);

    $budget   = fetch_budget_shared($conn, $BUDGET, $mlbl);
    $a_dialog = dialog_actual($conn, $mlbl);
    $a_cdma   = ratio_actual($conn, $CDMA['monthly'], $CDMA['charges'], $CDMA['conns'], $CDMA['col'], $start, $end);
    $a_slt    = ratio_actual($conn, $SLT['monthly'],  $SLT['charges'],  $SLT['conns'],  $SLT['col'], $start, $end);

    if ((float)$a_dialog === 0.0 && (float)$a_cdma === 0.0 && (float)$a_slt === 0.0) continue;

    $actual = $a_dialog + $a_cdma + $a_slt;
    $diff   = $budget - $actual;
    $var    = ($budget > 0) ? round(($diff / $budget) * 100) : null;

    $checked = '';
    if ($selStmt) {
      $selStmt->bind_param('sss', $category_for_selection, $mlbl, $user_id);
      $selStmt->execute();
      $r = $selStmt->get_result()->fetch_assoc();
      $checked = (($r['is_selected'] ?? '') === 'yes') ? 'checked' : '';
    }

    $rows[] = [
      'month'   => $mlbl,
      'budget'  => $budget,
      'dialog'  => $a_dialog,
      'cdma'    => $a_cdma,
      'slt'     => $a_slt,
      'actual'  => $actual,
      'diff'    => $diff,
      'var'     => $var,
      'checked' => $checked,
    ];

    if ($checked) {
      $tot_budget += $budget;
      $tot_dialog += $a_dialog;
      $tot_cdma   += $a_cdma;
      $tot_slt    += $a_slt;
      $tot_actual += $actual;
    }
  }
  if ($selStmt) $selStmt->close();

  $tot_diff = $tot_budget - $tot_actual;
  $tot_var  = ($tot_budget > 0) ? round(($tot_diff / $tot_budget) * 100) : null;
  ?>
  <style>
  .table .form-switch { padding-left: 0; min-height: 0; }
  .form-switch .form-check-input { width: 2.6em; height: 1.3em; cursor: pointer; }
  .toggle-cell .toggle-wrap { display: inline-flex; align-items: center; gap: .5rem; }
  .wide-table { min-width: 1100px; }
  .table td:nth-child(2),
  .table th:nth-child(2) {
      text-align: left !important;
  }
  </style>

  <div class="table-responsive">
    <table class="table table-bordered table-sm text-center wide-table">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th class="text-start">Month</th>
          <th>Budgeted Amount (Rs)</th>
          <th>Dialog (Rs)</th>
          <th>CDMA (Rs)</th>
          <th>SLT (Rs)</th>
          <th>Total Actual (Rs)</th>
          <th>Difference (Rs)</th>
          <th>Variance (%)</th>
          <th>Select / Remark</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; foreach ($rows as $row): ?>
        <tr
          class="report-row"
          data-category="<?= e($category_for_selection) ?>"
          data-record="<?= e($row['month']) ?>"
          data-budget="<?= e($row['budget']) ?>"
          data-dialog="<?= e($row['dialog']) ?>"
          data-cdma="<?= e($row['cdma']) ?>"
          data-slt="<?= e($row['slt']) ?>"
          data-actual="<?= e($row['actual']) ?>"
        >
          <td><?= $i++ ?></td>
          <td class="text-start"><?= e($row['month']) ?></td>
          <td><?= number_format($row['budget'], 2) ?></td>
          <td><?= number_format($row['dialog'], 2) ?></td>
          <td><?= number_format($row['cdma'],   2) ?></td>
          <td><?= number_format($row['slt'],    2) ?></td>
          <td class="fw-semibold"><?= number_format($row['actual'], 2) ?></td>
          <td class="<?= $row['diff'] < 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($row['diff'], 2) ?></td>
          <td class="<?= ($row['var'] !== null && $row['var'] < 0) ? 'text-danger fw-bold' : '' ?>">
            <?= $row['var'] !== null ? $row['var'].'%' : 'N/A' ?>
          </td>
          <td class="toggle-cell">
            <div class="toggle-wrap">
              <div class="form-check form-switch m-0">
                <input type="checkbox"
                      class="form-check-input month-checkbox"
                      role="switch"
                      id="month_switch_<?= e(str_replace(' ', '_', $row['month'])) ?>"
                      data-category="<?= e($category_for_selection) ?>"
                      data-month="<?= e($row['month']) ?>"
                      <?= $row['checked'] ?>>
              </div>
              <button class="btn btn-sm btn-outline-secondary open-remarks"
                      data-category="<?= e($category_for_selection) ?>"
                      data-record="<?= e($row['month']) ?>">💬</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <tr class="fw-bold table-light">
          <td colspan="2" class="text-end">Total</td>
          <td id="t-total-budget"><?= number_format($tot_budget, 2) ?></td>
          <td id="t-total-dialog"><?= number_format($tot_dialog, 2) ?></td>
          <td id="t-total-cdma"><?= number_format($tot_cdma,   2) ?></td>
          <td id="t-total-slt"><?= number_format($tot_slt,    2) ?></td>
          <td id="t-total-actual"><?= number_format($tot_actual, 2) ?></td>
          <td id="t-total-diff" class="<?= $tot_diff < 0 ? 'text-danger' : '' ?>"><?= number_format($tot_diff, 2) ?></td>
          <td id="t-total-var"><?= $tot_var !== null ? $tot_var.'%' : 'N/A' ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>

  <button id="update-telephone-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
