<?php
// report-branch-expense-data-newspaper.php
include 'connections/connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn->set_charset('utf8mb4');

$month  = trim($_GET['month'] ?? '');
$branch = trim($_GET['branch'] ?? '');

if ($month === '') {
  echo '<div class="alert alert-warning mb-0"><b>⚠️ Please select an Applicable Month.</b></div>';
  exit;
}

// Clean month text (NBSP + CR/LF + trim)
$cleanMonthA = "TRIM(REPLACE(REPLACE(REPLACE(a.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";
$cleanMonthB = "TRIM(REPLACE(REPLACE(REPLACE(b.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";

$budgetTable = 'tbl_admin_budget_newspaper_branch';

$branchFilterSql = "";
$params = [$month];
$types  = "s";

if ($branch !== '') {
  $branchFilterSql = " AND CAST(a.enterd_brn AS UNSIGNED) = CAST(? AS UNSIGNED) ";
  $params[] = $branch;
  $types   .= "s";
}

$sql = "
  SELECT
    a.enterd_brn      AS branch_code,
    a.enterd_brn_name AS branch_name,
    a.tran_db_cr_flg AS tran_db_cr_flg,
    SUM(COALESCE(a.debits,0)) AS actual_amount,
    COALESCE(MAX(b.budget_amount), 0) AS budget_amount,
    SUM(COALESCE(a.debits,0)) - COALESCE(MAX(b.budget_amount),0) AS variance
  FROM tbl_admin_actual_branch_gl_newspaper a
  LEFT JOIN $budgetTable b
    ON CAST(b.branch_code AS UNSIGNED) = CAST(a.enterd_brn AS UNSIGNED)
   AND $cleanMonthB = $cleanMonthA
  WHERE $cleanMonthA = ?
    $branchFilterSql
  AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
  GROUP BY a.enterd_brn, a.enterd_brn_name
  ORDER BY CAST(a.enterd_brn AS UNSIGNED) ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo '<div class="alert alert-danger mb-0"><b>❌ Prepare failed:</b> '.htmlspecialchars($conn->error).'</div>';
  exit;
}

$bind = [];
$bind[] = $types;
for ($i=0; $i<count($params); $i++) $bind[] = &$params[$i];
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
  echo '<div class="alert alert-danger mb-0"><b>❌ Query failed:</b> '.htmlspecialchars($stmt->error).'</div>';
  exit;
}

$rows = [];
$totActual = 0; $totBudget = 0; $totVar = 0;

while ($r = $res->fetch_assoc()) {
  $rows[] = $r;
  $totActual += (float)$r['actual_amount'];
  $totBudget += (float)$r['budget_amount'];
  $totVar    += (float)$r['variance'];
}
$stmt->close();
?>

<div class="alert alert-info">
  <b>Summary</b><br>
  Month: <b><?= htmlspecialchars($month) ?></b>
  <?php if ($branch !== ''): ?> | Branch: <b><?= htmlspecialchars($branch) ?></b><?php endif; ?>
  <hr class="my-2">
  Rows: <b><?= count($rows) ?></b> |
  Total Actual: <b><?= number_format($totActual, 2) ?></b> |
  Total Budget: <b><?= number_format($totBudget, 2) ?></b> |
  Variance: <b><?= number_format($totVar, 2) ?></b>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-hover align-middle w-100">
    <thead class="table-light">
      <tr>
        <th>Branch Code</th>
        <th>Branch Name</th>
        <th class="text-end">Actual</th>
        <th class="text-end">Budget</th>
        <th class="text-end">Variance</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="text-center text-muted">No data found for selected filters.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['branch_code']) ?></td>
          <td><?= htmlspecialchars($r['branch_name']) ?></td>
          <td class="text-end"><?= number_format((float)$r['actual_amount'], 2) ?></td>
          <td class="text-end"><?= number_format((float)$r['budget_amount'], 2) ?></td>
          <td class="text-end"><?= number_format((float)$r['variance'], 2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <tfoot class="table-light">
      <tr>
        <th colspan="2" class="text-end">TOTAL</th>
        <th class="text-end"><?= number_format($totActual, 2) ?></th>
        <th class="text-end"><?= number_format($totBudget, 2) ?></th>
        <th class="text-end"><?= number_format($totVar, 2) ?></th>
      </tr>
    </tfoot>
  </table>
</div>
