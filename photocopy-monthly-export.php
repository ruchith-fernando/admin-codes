<?php
// photocopy-monthly-export.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$monthRaw = trim($_GET['month'] ?? '');
if ($monthRaw === '') { http_response_code(400); echo "No month selected"; exit; }

$ts = strtotime($monthRaw);
if (!$ts) { http_response_code(400); echo "Invalid month value"; exit; }

$monthStart = date('Y-m-01', $ts);
$monthEnd   = date('Y-m-t',  $ts);

$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$fy_start = ($mn < 4) ? ($y - 1) : $y;
$fy_end   = $fy_start + 1;

$budget_year = sprintf('%04d-04_to_%04d-03', $fy_start, $fy_end);

$monthEsc = mysqli_real_escape_string($conn, $monthStart);
$endEsc   = mysqli_real_escape_string($conn, $monthEnd);
$byEsc    = mysqli_real_escape_string($conn, $budget_year);

function variance_display($budget, $actual) {
    $budget = (float)$budget;
    $actual = (float)$actual;
    if ($actual > $budget) return "(" . number_format($actual - $budget, 2, '.', '') . ")";
    return number_format($budget - $actual, 2, '.', '');
}

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

$sql = "
SELECT
  a.resolved_branch_code AS branch_code,
  COALESCE(br.branch_name, a.resolved_branch_code) AS branch_name,
  COALESCE(NULLIF(a.model_name,''), 'Machine') AS machine_name,
  a.serial_no,
  a.resolved_remarks AS remarks,
  a.copy_count,
  a.total_amount AS machine_actual,
  COALESCE(tot.branch_actual_total, 0) AS branch_actual,
  COALESCE(bud.amount, 0) AS budget_amount
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
  SELECT resolved_branch_code, ROUND(SUM(total_amount),2) AS branch_actual_total
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
if (!$res) { http_response_code(500); echo "SQL error: " . mysqli_error($conn); exit; }

$filename = "photocopy_report_" . $monthStart . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');

fputcsv($out, [
  'Branch Code','Branch Name','Machine Name','Machine Serial','Remarks',
  'Copies','Machine Actual','Branch Actual','Budget (Monthly)','Variance'
]);

$prevBranch = null;
$totalBudget = 0.0;
$totalActual = 0.0;

while ($r = mysqli_fetch_assoc($res)) {

    $branchCode = (string)($r['branch_code'] ?? '');
    $isFirstRowOfBranch = ($branchCode !== $prevBranch);

    $budget = (float)($r['budget_amount'] ?? 0);
    $branchActual = (float)($r['branch_actual'] ?? 0);

    // mimic rowspan: only show branch-level cells on first row per branch
    $outBranchCode   = $isFirstRowOfBranch ? $branchCode : '';
    $outBranchName   = $isFirstRowOfBranch ? ($r['branch_name'] ?? '') : '';
    $outBranchActual = $isFirstRowOfBranch ? number_format($branchActual, 2, '.', '') : '';
    $outBudget       = $isFirstRowOfBranch ? number_format($budget, 2, '.', '') : '';
    $outVariance     = $isFirstRowOfBranch ? variance_display($budget, $branchActual) : '';

    // totals should only count once per branch (like HTML)
    if ($isFirstRowOfBranch) {
        $totalBudget += $budget;
        $totalActual += $branchActual;
    }

    fputcsv($out, [
      $outBranchCode,
      $outBranchName,
      $r['machine_name'] ?? '',
      $r['serial_no'] ?? '',
      $r['remarks'] ?? '',
      (int)($r['copy_count'] ?? 0),
      number_format((float)($r['machine_actual'] ?? 0), 2, '.', ''),
      $outBranchActual,
      $outBudget,
      $outVariance
    ]);

    $prevBranch = $branchCode;
}

// Total row (like the HTML bottom row)
fputcsv($out, [
  'Total','','','','','', '',
  number_format($totalActual, 2, '.', ''),
  number_format($totalBudget, 2, '.', ''),
  variance_display($totalBudget, $totalActual)
]);

fclose($out);

userlog("⬇️ Photocopy CSV Export | Month: {$monthStart} | FY: {$budget_year} | User: " . ($_SESSION['name'] ?? 'Unknown'));
exit;
