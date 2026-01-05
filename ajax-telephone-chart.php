<?php
// ajax-telephone-chart.php
require_once 'connections/connection.php';

// Absolutely disable caching of this JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

/* ---------- CONFIG (matches telephone-budget-fetch.php) ---------- */
$BUDGET = [
  'table'      => 'tbl_admin_budget_telephone',
  'period_col' => 'budget_month',
  'amount_col' => 'budget_amount',
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

/* ---------- Helpers ---------- */
function month_bounds($label){
  $s = DateTime::createFromFormat('F Y', $label);
  if(!$s) return [null,null,null,null];
  $s->modify('first day of this month'); 
  $e=clone $s; $e->modify('last day of this month');
  return [$s->format('Y-m-d'), $e->format('Y-m-d'), $s->format('Y-m'), $s->format('Y-m-01')];
}
function prepareSeries(array $labels, array $map){
  $out = [];
  foreach ($labels as $m) {
    $v = (float)($map[$m] ?? 0);
    $out[] = ($v > 0) ? round($v) : null;
  }
  return $out;
}

/* Dialog actuals from tbl_admin_dialog_figures */
function dialog_actual(mysqli $conn, string $month_label): float {
  $m_dash = str_replace(' ', '-', $month_label); // "January 2025" → "January-2025"
  $sql = "SELECT dialog_bill_amount FROM tbl_admin_dialog_figures WHERE billing_month = ?";
  $st = $conn->prepare($sql);
  $st->bind_param('s', $m_dash);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  return (float)($r['dialog_bill_amount'] ?? 0.0);
}

/* Ratio-based actual for CDMA/SLT */
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

/* ---------- Build budget labels ---------- */
$labels = [];
$budget_map = [];

$q = "
  SELECT {$BUDGET['period_col']} AS m, SUM({$BUDGET['amount_col']}) AS amt
  FROM {$BUDGET['table']}
  GROUP BY {$BUDGET['period_col']}
  ORDER BY STR_TO_DATE({$BUDGET['period_col']}, '%M %Y')
";
$res = $conn->query($q);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $m = $row['m'];
    if (!isset($budget_map[$m])) { $labels[] = $m; }
    $budget_map[$m] = (float)$row['amt'];
  }
}

if (empty($labels)) {
  echo json_encode([
    'labels'       => [],
    'budget'       => [],
    'total_actual' => [],
    'dialog_total' => [],
    'cdma_total'   => [],
    'slt_total'    => [],
  ]);
  exit;
}

/* ---------- Compute actuals ---------- */
$total_actual_map = $dialog_map = $cdma_map = $slt_map = [];

foreach ($labels as $mlbl) {
  list($start,$end) = month_bounds($mlbl);
  if (!$start || !$end) continue;

  $a_dialog = dialog_actual($conn, $mlbl);
  $a_cdma   = ratio_actual($conn, $CDMA['monthly'], $CDMA['charges'], $CDMA['conns'], $CDMA['col'], $start, $end);
  $a_slt    = ratio_actual($conn, $SLT['monthly'],  $SLT['charges'],  $SLT['conns'],  $SLT['col'], $start, $end);

  $dialog_map[$mlbl] = $a_dialog;
  $cdma_map[$mlbl]   = $a_cdma;
  $slt_map[$mlbl]    = $a_slt;
  $total_actual_map[$mlbl] = $a_dialog + $a_cdma + $a_slt;
}

/* ---------- Trim trailing months with no data ---------- */
$lastActualIndex = null;
foreach ($labels as $i => $m) {
  if (!empty($total_actual_map[$m]) && (float)$total_actual_map[$m] > 0) {
    $lastActualIndex = $i;
  }
}
if ($lastActualIndex !== null) {
  $labels = array_slice($labels, 0, $lastActualIndex + 1);
}

/* ---------- Payload ---------- */
$payload = [
  'labels'       => array_values($labels),
  'budget'       => prepareSeries($labels, $budget_map),
  'total_actual' => prepareSeries($labels, $total_actual_map),
  'dialog_total' => prepareSeries($labels, $dialog_map),
  'cdma_total'   => prepareSeries($labels, $cdma_map),
  'slt_total'    => prepareSeries($labels, $slt_map),
];

echo json_encode($payload);
