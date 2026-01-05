<?php
// report-branch-expense-download-tea.php
include 'connections/connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
$conn->set_charset('utf8mb4');

$month  = trim($_GET['month'] ?? '');
$branch = trim($_GET['branch'] ?? '');

if ($month === '') {
  http_response_code(400);
  echo "Month is required";
  exit;
}

$cleanMonthA = "TRIM(REPLACE(REPLACE(REPLACE(a.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";
$cleanMonthB = "TRIM(REPLACE(REPLACE(REPLACE(b.applicable_month, CHAR(194,160), ' '), CHAR(13), ''), CHAR(10), ''))";

$branchFilterSql = "";
$types  = "ss";      // month (actual), month (budget)
$params = [$month, $month];

if ($branch !== '') {
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
  http_response_code(500);
  echo "Prepare failed: " . $conn->error;
  exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();

$res = $stmt->get_result();
if (!$res) {
  http_response_code(500);
  echo "Query failed: " . $stmt->error;
  exit;
}

// ---------- CSV OUTPUT ----------
$safeMonth  = preg_replace('/[^A-Za-z0-9 _-]/', '', $month);
$safeBranch = preg_replace('/[^A-Za-z0-9 _-]/', '', $branch);
$filename = "tea-branch-report_{$safeMonth}" . ($safeBranch !== '' ? "_branch-{$safeBranch}" : "") . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// optional: UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// header row
fputcsv($out, ['Branch ID', 'Branch Name', 'Actual', 'Budget', 'Variance']);

$totActual = 0; $totBudget = 0; $totVar = 0;

while ($r = $res->fetch_assoc()) {
  $actual = (float)$r['actual_amount'];
  $budget = (float)$r['budget_amount'];
  $var    = (float)$r['variance'];

  $totActual += $actual;
  $totBudget += $budget;
  $totVar    += $var;

  fputcsv($out, [
    $r['branch_code'],
    $r['branch_name'],
    number_format($actual, 2, '.', ''),
    number_format($budget, 2, '.', ''),
    number_format($var, 2, '.', ''),
  ]);
}

// totals row
fputcsv($out, []);
fputcsv($out, ['TOTAL', '', number_format($totActual, 2, '.', ''), number_format($totBudget, 2, '.', ''), number_format($totVar, 2, '.', '')]);

fclose($out);
exit;
