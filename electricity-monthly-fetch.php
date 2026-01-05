<?php
// electricity-monthly-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

function elog($m){
  @file_put_contents('electricity.log', "[".date('Y-m-d H:i:s')."] FETCH: $m\n", FILE_APPEND);
}

$month = isset($_POST['month']) ? trim($_POST['month']) : '';
if ($month === '') { echo json_encode(['table'=>'','missing'=>[],'provisions'=>[]]); exit; }

elog("month=$month");

// --- helpers ---
function fy_from_month(string $monthStr): int {
  $ts = strtotime('1 ' . $monthStr);
  if ($ts === false) return (int)date('Y');
  $y = (int)date('Y', $ts);
  $m = (int)date('n', $ts);
  return ($m >= 4) ? $y : ($y - 1);
}
function norm_code(?string $c): string {
  $c = trim((string)$c);
  $c = str_replace(' ', '', $c);
  $c = str_replace('.', '-', $c);
  return $c;
}

// special groups
$green_path   = ['2009'];
$yards        = ['2001','2003','2004','2007','2012','2023','2023-1'];
$bungalows    = ['2017','2018'];

/**
 * Sorting priority:
 * 1. Normal branches (natural order)
 * 2. Green Path (2009)
 * 3. Yards (fixed list order)
 * 4. Bungalows (fixed list order)
 * 5. 2000 always last
 */
function branch_order_key($code) {
  global $green_path,$yards,$bungalows;
  $norm = norm_code($code);

  if ($norm === '2000') return [99, 0, 0];

  if (!in_array($norm,$green_path) && !in_array($norm,$yards) && !in_array($norm,$bungalows)) {
    $parts = explode('-', $norm);
    $main = is_numeric($parts[0]) ? (int)$parts[0] : $parts[0];
    $sub  = isset($parts[1]) ? (is_numeric($parts[1]) ? (int)$parts[1] : $parts[1]) : -1;
    return [1, $main, $sub];
  }

  if (in_array($norm,$green_path)) return [2, array_search($norm,$green_path), 0];
  if (in_array($norm,$yards)) return [3, array_search($norm,$yards), 0];
  if (in_array($norm,$bungalows)) return [4, array_search($norm,$bungalows), 0];

  return [98, 0, 0];
}

// FY
$fy = fy_from_month($month);

// -------- Branch master --------
$branches = [];
$qb = mysqli_query($conn, "SELECT branch_code, branch_name, account_no FROM tbl_admin_branch_electricity");
if ($qb) {
  while ($b = mysqli_fetch_assoc($qb)) {
    $branches[norm_code($b['branch_code'])] = [
      'code' => $b['branch_code'],
      'name' => $b['branch_name'],
      'account_no' => $b['account_no']
    ];
  }
}

// -------- Actuals --------
$actuals = [];
$month_sql = mysqli_real_escape_string($conn, $month);
$qa = mysqli_query($conn, "
  SELECT branch_code, branch, account_no, actual_units, total_amount, is_provision, provision_reason
  FROM tbl_admin_actual_electricity
  WHERE month_applicable = '{$month_sql}'
");
if ($qa) {
  while ($a = mysqli_fetch_assoc($qa)) {
    $norm = norm_code($a['branch_code']);
    $a['orig_code'] = $a['branch_code'];
    $actuals[$norm] = $a;
  }
}

// -------- Budgets --------
$budgets = [];
$fy_sql = mysqli_real_escape_string($conn, (string)$fy);

$qb2 = mysqli_query($conn, "
  SELECT branch_code, amount
  FROM tbl_admin_budget_electricity
  WHERE budget_year = '{$fy_sql}'
");

if ($qb2) {
  while ($bb = mysqli_fetch_assoc($qb2)) {
    $norm = norm_code($bb['branch_code']);
    // Each 'amount' in the table is the monthly budget for that branch.
    // Multiply by 12 to get the full-year budget, same as main calculation.
    $budgets[$norm] = (float)$bb['amount'] * 12;
  }
}


// -------- Build rows --------
$rows = [];
$total_actual = 0.0; $total_budget = 0.0;
$provisions = [];

foreach ($branches as $norm => $info) {
  $a = $actuals[$norm] ?? null;
  $code_disp   = $a['orig_code'] ?? $info['code'];
  $branch_disp = $a['branch'] ?? $info['name'];

  $units = $a ? trim((string)$a['actual_units']) : '';
  $amt   = $a ? (float)str_replace(',', '', (string)$a['total_amount']) : 0.0;
  $bud   = isset($budgets[$norm]) ? (float)$budgets[$norm] : 0.0;
  $var   = $bud - $amt;

  $total_actual += $amt;
  $total_budget += $bud;

  if ($a && ($a['is_provision'] ?? 'no') === 'yes') {
    $provisions[] = ($branch_disp ?: $code_disp) . " ($code_disp)";
  }

  $rows[] = [
    'code' => $code_disp,
    'branch' => $branch_disp,
    'account_no' => $a['account_no'] ?? $info['account_no'],
    'units' => $units,
    'actual' => $amt,
    'budget' => $bud,
    'variance' => $var,
    'is_provision' => $a['is_provision'] ?? 'no',
    'provision_reason' => $a['provision_reason'] ?? ''
  ];
}

// -------- Sort rows --------
usort($rows, function($x,$y){
  $kx = branch_order_key($x['code']);
  $ky = branch_order_key($y['code']);
  return $kx <=> $ky;
});

// -------- Missing branches --------
$missing = [];
foreach ($branches as $norm => $info) {
  if (!isset($actuals[$norm])) {
    $missing[] = $info['name'] . " (" . $info['code'] . ")";
  }
}

// -------- Render table --------
ob_start(); ?>
<table class="table table-striped table-bordered">
  <thead class="table-light">
    <tr>
      <th>Branch Code</th>
      <th>Branch</th>
      <th>Account No</th>
      <th class="text-end">Units</th>
      <th class="text-end">Actual Amount</th>
      <th class="text-end">Budget Amount</th>
      <th class="text-end">Variance (Budget - Actual)</th>
      <th class="text-center">Provision</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="text-center">No records for <?= htmlspecialchars($month) ?></td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= ($r['is_provision']==='yes' ? 'table-warning' : '') ?>">
          <td><?= htmlspecialchars($r['code']) ?></td>
          <td>
            <?= htmlspecialchars($r['branch']) ?>
            <?php if ($r['is_provision']==='yes'): ?>
              <span class="badge bg-warning text-dark ms-2">Provision</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($r['account_no']) ?></td>
          <td class="text-end"><?= htmlspecialchars($r['units']) ?></td>
          <td class="text-end"><?= number_format($r['actual'], 2) ?></td>
          <td class="text-end"><?= number_format($r['budget'], 2) ?></td>
          <td class="text-end"><?= number_format($r['variance'], 2) ?></td>
          <td class="text-center"><?= htmlspecialchars($r['is_provision']==='yes' ? 'yes' : 'no') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
  <tfoot class="table-light">
    <tr>
      <th colspan="4" class="text-end">Totals:</th>
      <th class="text-end"><?= number_format($total_actual, 2) ?></th>
      <th class="text-end"><?= number_format($total_budget, 2) ?></th>
      <th class="text-end"><?= number_format($total_budget - $total_actual, 2) ?></th>
      <th></th>
    </tr>
  </tfoot>
</table>
<?php
$html = ob_get_clean();

echo json_encode([
  'table' => $html,
  'missing' => $missing,
  'provisions' => $provisions
]);
