<?php
// photocopy-monthly-fetch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$monthRaw = trim($_POST['month'] ?? '');
if ($monthRaw === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

$ts = strtotime($monthRaw);
if (!$ts) {
    echo json_encode(['error' => 'Invalid month value']);
    exit;
}

$monthStart = date('Y-m-01', $ts);
$monthEnd   = date('Y-m-t',  $ts);

// FY string used in budget table
$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$fy_start = ($mn < 4) ? ($y - 1) : $y;
$fy_end   = $fy_start + 1;

$budget_year = sprintf('%04d-04_to_%04d-03', $fy_start, $fy_end);

$monthEsc = mysqli_real_escape_string($conn, $monthStart);
$endEsc   = mysqli_real_escape_string($conn, $monthEnd);
$byEsc    = mysqli_real_escape_string($conn, $budget_year);

/* -------------------------------------------------------
   CSS (DROP-IN)
   - Full width, fixed layout so it fits screen
   - Wrap ONLY Branch Name + Remarks
   - Keep Machine Name + Serial as single line (ellipsis)
------------------------------------------------------- */
$css = "
<style>
  .pc-report-table{
    width:100%;
    table-layout: fixed;
  }

  .pc-report-table th,
  .pc-report-table td{
    vertical-align: top;
    padding: .35rem .45rem;
    font-size: .875rem;
  }

  /* Wrap only where we WANT wrapping */
  .pc-report-table .wrap{
    white-space: normal;
    overflow-wrap: break-word;
    word-break: normal;
  }

  /* Keep single-line + neat */
  .pc-report-table .nowrap{
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pc-report-table td.num, .pc-report-table th.num{
    text-align:right;
    white-space: nowrap;
  }

  .pc-report-table .var-over { color:#dc3545; font-weight:600; }

  .pc-report-table tbody tr.over-budget-row > *{
    background-color:#ffecec !important;
  }
</style>
";

function money_fmt($n) { return number_format((float)$n, 2); }

function variance_fmt($budget, $actual) {
    $budget = (float)$budget;
    $actual = (float)$actual;

    if ($actual > $budget) {
        $d = $actual - $budget;
        return "<span class='var-over'>(" . money_fmt($d) . ")</span>";
    }
    $d = $budget - $actual;
    return money_fmt($d);
}

/* -------------------------------------------------------
   Mapping: latest assignment per machine overlapping the month
------------------------------------------------------- */
$mapSql = "
  SELECT a.machine_id, a.branch_code, a.remarks
  FROM tbl_admin_photocopy_machine_assignments a
  INNER JOIN (
    SELECT machine_id, MAX(assign_id) AS max_assign_id
    FROM tbl_admin_photocopy_machine_assignments
    WHERE installed_at <= '{$endEsc}'
      AND (removed_at IS NULL OR removed_at >= '{$monthEsc}')
    GROUP BY machine_id
  ) x ON x.max_assign_id = a.assign_id
";

/* -------------------------------------------------------
   Pull machine rows + branch totals
------------------------------------------------------- */
$sql = "
SELECT
  a.resolved_branch_code AS branch_code,
  COALESCE(br.branch_name, a.resolved_branch_code) AS branch_name,
  COALESCE(NULLIF(a.model_name,''), 'Machine') AS machine_name,
  a.serial_no,
  a.resolved_remarks AS remarks,
  a.copy_count,
  a.total_amount AS machine_amount,
  COALESCE(bud.amount, 0) AS budget_amount,
  COALESCE(tot.branch_actual_total, 0) AS branch_actual_total
FROM (
  SELECT
    act.machine_id,
    act.model_name,
    act.serial_no,
    act.copy_count,
    act.total_amount,
    COALESCE(m.branch_code, act.branch_code) AS resolved_branch_code,
    COALESCE(NULLIF(m.remarks,''), act.excel_branch_location) AS resolved_remarks
  FROM tbl_admin_actual_photocopy act
  LEFT JOIN ({$mapSql}) m ON m.machine_id = act.machine_id
  WHERE act.month_applicable = '{$monthEsc}'
) a
LEFT JOIN tbl_admin_branches br
  ON br.branch_code = a.resolved_branch_code
LEFT JOIN tbl_admin_budget_photocopy bud
  ON bud.branch_code = a.resolved_branch_code
 AND bud.budget_year = '{$byEsc}'
 AND bud.applicable_month = '{$monthEsc}'
LEFT JOIN (
  SELECT resolved_branch_code, SUM(total_amount) AS branch_actual_total
  FROM (
    SELECT
      act2.total_amount,
      COALESCE(m2.branch_code, act2.branch_code) AS resolved_branch_code
    FROM tbl_admin_actual_photocopy act2
    LEFT JOIN ({$mapSql}) m2 ON m2.machine_id = act2.machine_id
    WHERE act2.month_applicable = '{$monthEsc}'
  ) z
  GROUP BY resolved_branch_code
) tot
  ON tot.resolved_branch_code = a.resolved_branch_code
ORDER BY CAST(a.resolved_branch_code AS UNSIGNED), a.resolved_branch_code, a.machine_id
";

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['error' => 'SQL error: ' . mysqli_error($conn)]);
    exit;
}

/* -------------------------------------------------------
   Group rows by branch
------------------------------------------------------- */
$branches = [];
while ($r = mysqli_fetch_assoc($res)) {
    $code = (string)($r['branch_code'] ?? '');
    if ($code === '') $code = 'UNKNOWN';

    if (!isset($branches[$code])) {
        $branches[$code] = [
            'branch_name'   => $r['branch_name'] ?? '',
            'budget_amount' => (float)($r['budget_amount'] ?? 0),
            'branch_actual' => (float)($r['branch_actual_total'] ?? 0),
            'rows'          => [],
        ];
    }

    $branches[$code]['rows'][] = [
        'machine_name'   => $r['machine_name'] ?? '',
        'serial_no'      => $r['serial_no'] ?? '',
        'remarks'        => $r['remarks'] ?? '',
        'copy_count'     => (int)($r['copy_count'] ?? 0),
        'machine_amount' => (float)($r['machine_amount'] ?? 0),
    ];
}

uksort($branches, 'strnatcmp');

$total_budget_all = 0.0;
$total_actual_all = 0.0;

/* -------------------------------------------------------
   Table (DROP-IN)
   - Uses colgroup widths
   - Wrap for Branch Name + Remarks
   - No-wrap + ellipsis for Machine Name + Serial
------------------------------------------------------- */
$table = $css . "
<table class='table table-bordered pc-report-table'>
  <colgroup>
    <col style='width:7%'>
    <col style='width:13%'>
    <col style='width:16%'>
    <col style='width:11%'>
    <col style='width:12%'>
    <col style='width:6%'>
    <col style='width:9%'>
    <col style='width:9%'>
    <col style='width:9%'>
    <col style='width:8%'>
  </colgroup>

  <thead class='table-light'>
    <tr>
      <th class='nowrap'>Branch Code</th>
      <th class='wrap'>Branch Name</th>
      <th class='nowrap'>Machine Name</th>
      <th class='nowrap'>Machine Serial</th>
      <th class='wrap'>Remarks</th>
      <th class='num nowrap'>Copies</th>
      <th class='num nowrap'>Machine Actual</th>
      <th class='num nowrap'>Branch Actual</th>
      <th class='num nowrap'>Budget (Monthly)</th>
      <th class='num nowrap'>Variance</th>
    </tr>
  </thead>
  <tbody>
";

foreach ($branches as $code => $b) {
    $rows = $b['rows'];
    if (empty($rows)) continue;

    $branchName   = $b['branch_name'];
    $budgetAmount = (float)$b['budget_amount'];
    $branchActual = (float)$b['branch_actual'];

    // Totals should be branch-level, otherwise you double-count
    $total_budget_all += $budgetAmount;
    $total_actual_all += $branchActual;

    $rowspan    = count($rows);
    $overBudget = ($branchActual > $budgetAmount);
    $rowClass   = $overBudget ? "over-budget-row" : "";

    $safeCode = htmlspecialchars($code, ENT_QUOTES);
    $safeName = htmlspecialchars($branchName, ENT_QUOTES);

    // first row
    $r0 = $rows[0];

    $table .= "
    <tr class='{$rowClass}'>
      <td rowspan='{$rowspan}' class='nowrap'>{$safeCode}</td>
      <td rowspan='{$rowspan}' class='wrap'>{$safeName}</td>

      <td class='nowrap'>" . htmlspecialchars($r0['machine_name'], ENT_QUOTES) . "</td>
      <td class='nowrap'>" . htmlspecialchars($r0['serial_no'], ENT_QUOTES) . "</td>
      <td class='wrap'>"   . htmlspecialchars($r0['remarks'], ENT_QUOTES) . "</td>

      <td class='num'>" . number_format((int)$r0['copy_count']) . "</td>
      <td class='num'>" . money_fmt($r0['machine_amount']) . "</td>

      <td rowspan='{$rowspan}' class='num'>" . money_fmt($branchActual) . "</td>
      <td rowspan='{$rowspan}' class='num'>" . money_fmt($budgetAmount) . "</td>
      <td rowspan='{$rowspan}' class='num'>" . variance_fmt($budgetAmount, $branchActual) . "</td>
    </tr>
    ";

    // rest rows
    for ($i = 1; $i < $rowspan; $i++) {
        $ri = $rows[$i];
        $table .= "
        <tr class='{$rowClass}'>
          <td class='nowrap'>" . htmlspecialchars($ri['machine_name'], ENT_QUOTES) . "</td>
          <td class='nowrap'>" . htmlspecialchars($ri['serial_no'], ENT_QUOTES) . "</td>
          <td class='wrap'>"   . htmlspecialchars($ri['remarks'], ENT_QUOTES) . "</td>
          <td class='num'>" . number_format((int)$ri['copy_count']) . "</td>
          <td class='num'>" . money_fmt($ri['machine_amount']) . "</td>
        </tr>
        ";
    }
}

// totals row (show total actual, total budget, variance)
$table .= "
  <tr class='table-secondary fw-bold'>
    <td colspan='7'>Total</td>
    <td class='num'>" . money_fmt($total_actual_all) . "</td>
    <td class='num'>" . money_fmt($total_budget_all) . "</td>
    <td class='num'>" . variance_fmt($total_budget_all, $total_actual_all) . "</td>
  </tr>
  </tbody>
</table>
";

userlog("📊 Photocopy Report View | Month: {$monthStart} | FY: {$budget_year} | User: " . ($_SESSION['name'] ?? 'Unknown'));

echo json_encode(['table' => $table]);
