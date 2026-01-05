<?php
// report-branch-expense-data.php
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

// WHERE + prepared params
$where = "WHERE a.applicable_month = ?";
$types = "s";
$params = [$month];

if ($branch !== '') {
  $where .= " AND a.enterd_brn = ?";
  $types .= "s";
  $params[] = $branch;
}

$sql = "
  SELECT
    a.enterd_brn      AS branch_code,
    a.enterd_brn_name AS branch_name,
    a.tran_db_cr_flg AS tran_db_cr_flg,
    COALESCE(NULLIF(TRIM(a.tranbat_narr_dtl1),''), '') AS narr1,
    COALESCE(NULLIF(TRIM(a.tranbat_narr_dtl2),''), '') AS narr2,
    COALESCE(NULLIF(TRIM(a.tranbat_narr_dtl3),''), '') AS narr3,

    SUM(a.debits)     AS actual_amount,
    MAX(COALESCE(b.budget_amount, 0)) AS budget_amount,
    SUM(a.debits) - MAX(COALESCE(b.budget_amount, 0)) AS variance
  FROM tbl_admin_actual_branch_gl_postage a
  LEFT JOIN tbl_admin_budget_postage b
    ON b.branch_code      = a.enterd_brn
    AND b.applicable_month = a.applicable_month
  $where
    AND UPPER(TRIM(tran_db_cr_flg)) = 'D'

  GROUP BY
    a.enterd_brn,
    a.enterd_brn_name,
    narr1, narr2, narr3
  ORDER BY
    CAST(a.enterd_brn AS UNSIGNED) ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo '<div class="alert alert-danger mb-0"><b>❌ Prepare failed:</b> '.htmlspecialchars($conn->error).'</div>';
  exit;
}

// bind_param needs references
$bind = [];
$bind[] = $types;
for ($i=0; $i<count($params); $i++) $bind[] = &$params[$i];
call_user_func_array([$stmt, 'bind_param'], $bind);

if (!$stmt->execute()) {
  echo '<div class="alert alert-danger mb-0"><b>❌ Execute failed:</b> '.htmlspecialchars($stmt->error).'</div>';
  exit;
}

$res = $stmt->get_result();

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

<!-- Wrap + fit-to-width styling -->
<style>
  /* Make the table behave better on narrow screens */
  .report-table{
    table-layout: fixed;          /* prevents wide cells from blowing out width */
    width: 100%;
  }

  /* Smaller font + wrapping for branch columns */
  .report-branch{
    font-size: 0.85rem;
    white-space: normal !important;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  /* Wrap narration/remarks */
  .report-narr{
    white-space: normal !important;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.2;
    font-size: 0.9rem;
  }

  /* Numbers stay tidy */
  .report-num{
    white-space: nowrap;
  }
</style>

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
  <table class="table table-bordered table-hover table-sm align-middle w-100 report-table">
    <!-- Strongly control widths to fit screen -->
    <colgroup>
      <col style="width:10%">
      <col style="width:18%">
      <!-- <col style="width:42%"> -->
      <col style="width:10%">
      <col style="width:10%">
      <col style="width:10%">
    </colgroup>

    <thead class="table-light">
      <tr>
        <th>Branch Code</th>
        <th>Branch Name</th>
        <!-- <th>Narration</th> -->
        <th class="text-end">Actual</th>
        <th class="text-end">Budget</th>
        <th class="text-end">Variance</th>
      </tr>
    </thead>

    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr><td colspan="6" class="text-center text-muted">No data found for selected filters.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            // Build ONE cell narration (only non-empty lines)
            $parts = array_filter([
              $r['narr1'] ?? '',
              $r['narr2'] ?? '',
              $r['narr3'] ?? ''
            ], fn($x) => trim($x) !== '');

            $narrationHtml = $parts
              ? implode('<br>', array_map('htmlspecialchars', $parts))
              : '<span class="text-muted">-</span>';
          ?>
          <tr>
            <td class="report-branch"><?= htmlspecialchars($r['branch_code']) ?></td>
            <td class="report-branch"><?= htmlspecialchars($r['branch_name']) ?></td>
            <!-- <td class="report-narr"><?= $narrationHtml ?></td> -->

            <td class="text-end report-num"><?= number_format((float)$r['actual_amount'], 2) ?></td>
            <td class="text-end report-num"><?= number_format((float)$r['budget_amount'], 2) ?></td>
            <td class="text-end report-num"><?= number_format((float)$r['variance'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>

    <tfoot class="table-light">
      <tr>
        <th colspan="2" class="text-end">TOTAL</th>
        <th class="text-end report-num"><?= number_format($totActual, 2) ?></th>
        <th class="text-end report-num"><?= number_format($totBudget, 2) ?></th>
        <th class="text-end report-num"><?= number_format($totVar, 2) ?></th>
      </tr>
    </tfoot>
  </table>
</div>
