<?php
// report-branch-expense-data-tea.php
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

/**
 * Clean month text in SQL (NBSP + CR/LF + trim)
 * Keep normal spaces (because "November 2025" needs the space).
 */
$cleanMonthA = "TRIM(REPLACE(REPLACE(REPLACE(a.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";
$cleanMonthB = "TRIM(REPLACE(REPLACE(REPLACE(b.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";

$branchFilterSql = "";
$types  = "ss";          // month for actual subquery, month for budget subquery
$params = [$month, $month];

if ($branch !== '') {
  // numeric-safe compare so "001" matches "1" if your tables differ
  $branchFilterSql = " AND CAST(a.enterd_brn AS UNSIGNED) = CAST(? AS UNSIGNED) ";
  $types  .= "s";
  $params[] = $branch;
}

$sql = "
  SELECT
    a.branch_code,
    a.branch_name,
    a.actual_amount,
    COALESCE(b.budget_amount, 0) AS budget_amount,
    (a.actual_amount - COALESCE(b.budget_amount, 0)) AS variance
  FROM
    (
      SELECT
        a.enterd_brn AS branch_code,
        a.enterd_brn_name AS branch_name,
        SUM(COALESCE(a.debits,0)) AS actual_amount
      FROM tbl_admin_actual_branch_gl_tea a
      WHERE $cleanMonthA = ?
      $branchFilterSql
      AND UPPER(TRIM(tran_db_cr_flg)) = 'D'
      GROUP BY a.enterd_brn, a.enterd_brn_name
    ) a
  LEFT JOIN
    (
      SELECT
        b.branch_code,
        SUM(COALESCE(b.budget_amount,0)) AS budget_amount
      FROM tbl_admin_budget_tea_branch b
      WHERE $cleanMonthB = ?
      GROUP BY b.branch_code
    ) b
    ON CAST(b.branch_code AS UNSIGNED) = CAST(a.branch_code AS UNSIGNED)
  ORDER BY CAST(a.branch_code AS UNSIGNED) ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo '<div class="alert alert-danger mb-0"><b>❌ Prepare failed:</b> '.htmlspecialchars($conn->error).'</div>';
  exit;
}

$stmt->bind_param($types, ...$params);
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
        <th>Branch ID</th>
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
