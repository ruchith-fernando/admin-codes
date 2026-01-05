<?php
// ajax-water-chart.php
require_once 'connections/connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Colombo');

// If the DB connection failed, return an empty dataset so the chart doesn't break
if ($conn->connect_error) {
  echo json_encode([
    'labels' => [],
    'budget' => [],
    'actual' => [],
    'error'  => 'DB connection failed'
  ]);
  exit;
}

/*
  Query params this endpoint understands:

  - fy           : "2024" or "2024-2025" (financial year start year)
  - fy_offset    : relative offset from current FY (e.g. -1 for previous FY)
  - include_zero : 1 to keep months even when actuals are 0 / missing
  - budget_mode  : "monthly" (default) or "yearly" to control budget line
*/
$fyParam       = $_GET['fy'] ?? null;
$fyOffsetParam = isset($_GET['fy_offset']) ? intval($_GET['fy_offset']) : null;
$includeZero   = (isset($_GET['include_zero']) && $_GET['include_zero'] === '1');
$budgetMode    = $_GET['budget_mode'] ?? 'monthly';

/**
 * Our FY is Apr -> Mar.
 * This returns the FY start year for "today" in Asia/Colombo time.
 */
function currentFyStartYear(): int {
  $y = (int)date('Y');
  $m = (int)date('n'); // 1..12
  return ($m >= 4) ? $y : ($y - 1);
}

/**
 * Builds the 12 month labels we use everywhere:
 * "April 2024" ... "March 2025"
 * (This must match the month_applicable text stored in the DB.)
 */
function buildFyMonthsLabels(int $fyStartYear): array {
  $months = [];
  $cursor = new DateTime(sprintf('%04d-04-01', $fyStartYear));
  $end    = new DateTime(sprintf('%04d-03-01', $fyStartYear + 1));

  while ($cursor <= $end) {
    $months[] = $cursor->format('F Y');
    $cursor->modify('+1 month');
  }

  return $months; // 12 months
}

/**
 * total_amount sometimes comes as a formatted string (commas, blanks, etc.).
 * Normalize it into a float or return null if it's not usable.
 */
function parseAmount($raw) {
  $t = trim((string)$raw);
  if ($t === '') return null;

  $clean = str_replace(',', '', $t);
  if (!is_numeric($clean)) return null;

  return (float)$clean;
}

/* ---------------------------
   Decide which FY to use
---------------------------- */
$fyStartYear = null;

if ($fyParam) {
  // Accept "2024-2025" or just "2024"
  if (preg_match('/^\d{4}-\d{4}$/', $fyParam)) {
    [$y1, $y2] = array_map('intval', explode('-', $fyParam));
    // Keep it sane even if someone passes "2024-2026"
    if ($y2 !== $y1 + 1) $y2 = $y1 + 1;
    $fyStartYear = $y1;
  } elseif (preg_match('/^\d{4}$/', $fyParam)) {
    $fyStartYear = (int)$fyParam;
  } else {
    // If fy is garbage, just fall back to current FY
    $fyStartYear = currentFyStartYear();
  }
} elseif ($fyOffsetParam !== null) {
  // Handy for arrows/buttons in the UI: 0 = current FY, -1 = previous, etc.
  $fyStartYear = currentFyStartYear() + $fyOffsetParam;
} else {
  $fyStartYear = currentFyStartYear();
}

$months = buildFyMonthsLabels($fyStartYear);

/* =========================================================
   1) Master mapping: what connections should exist per branch?
   We only count active water types.
   Key format: "water_type_id|connection_no"
========================================================= */
$master = []; // branch_code => ['required_keys'=>[], 'required_count'=>N]

$map_sql = "
  SELECT bw.branch_code, bw.water_type_id, bw.connection_no
  FROM tbl_admin_branch_water bw
  INNER JOIN tbl_admin_water_types wt
    ON wt.water_type_id = bw.water_type_id
  WHERE wt.is_active = 1
  ORDER BY bw.branch_code, bw.water_type_id, bw.connection_no
";

$map_res = $conn->query($map_sql);
if ($map_res) {
  while ($r = $map_res->fetch_assoc()) {
    $code = (string)$r['branch_code'];
    $tid  = (int)($r['water_type_id'] ?? 0);
    $cno  = (int)($r['connection_no'] ?? 1);

    if ($cno <= 0) $cno = 1;
    $key = $tid . '|' . $cno;

    if (!isset($master[$code])) $master[$code] = ['required_keys' => []];
    $master[$code]['required_keys'][] = $key;
  }
}

// Clean up duplicates and store required_count so we don’t keep recounting later
foreach ($master as $code => $m) {
  $uniq = array_values(array_unique($m['required_keys']));
  $master[$code]['required_keys']  = $uniq;
  $master[$code]['required_count'] = count($uniq);
}

/* =========================================================
   2) Which branches should be included in this chart?
   Rule:
   - Branch must have a budget > 0 for the FY
   - Branch must also exist in the master mapping (so we know what "complete" means)
========================================================= */
$budgetBranches = [];
$fyEsc = mysqli_real_escape_string($conn, (string)$fyStartYear);

$resBB = $conn->query("
  SELECT DISTINCT branch_code
  FROM tbl_admin_budget_water
  WHERE budget_year = '{$fyEsc}'
    AND amount IS NOT NULL
    AND amount > 0
");

if ($resBB) {
  while ($r = $resBB->fetch_assoc()) {
    $budgetBranches[(string)$r['branch_code']] = true;
  }
}

$eligibleBranches = []; // branch_code => master data
foreach ($master as $code => $m) {
  if (isset($budgetBranches[$code])) {
    $eligibleBranches[$code] = $m;
  }
}

if (!count($eligibleBranches)) {
  echo json_encode([
    'labels' => [],
    'budget' => [],
    'actual' => [],
    'error'  => 'No eligible branches (budget>0 + master mapping).'
  ]);
  exit;
}

/* ---------------------------
   Budget line:
   - yearly_budget is the total FY budget for eligible branches
   - monthly_budget is a flat split (yearly / 12)
---------------------------- */
$branchList = array_keys($eligibleBranches);
$branchIn = "'" . implode("','", array_map(
  fn($b) => mysqli_real_escape_string($conn, $b),
  $branchList
)) . "'";

$yearly_budget = 0.0;

$bres = $conn->query("
  SELECT SUM(amount) AS budget_amount
  FROM tbl_admin_budget_water
  WHERE budget_year = '{$fyEsc}'
    AND branch_code IN ({$branchIn})
");

if ($bres && $b = $bres->fetch_assoc()) {
  $yearly_budget = (float)($b['budget_amount'] ?? 0);
}

$monthly_budget = ($yearly_budget > 0) ? ($yearly_budget / 12.0) : 0.0;

/* =========================================================
   3) Pull all actual rows in one go for:
   - selected FY months
   - eligible branches
   We'll decide "complete & approved" later in PHP.
========================================================= */
$monthIn = "'" . implode("','", array_map(
  fn($m) => mysqli_real_escape_string($conn, $m),
  $months
)) . "'";

$actualRows = [];      // [month][branch][key] = ['status'=>..., 'amount'=>...]
$monthsWithRows = [];  // month => true (helps us skip empty months like the report)

$ares = $conn->query("
  SELECT month_applicable, branch_code, water_type_id, connection_no, approval_status, total_amount
  FROM tbl_admin_actual_water
  WHERE month_applicable IN ({$monthIn})
    AND branch_code IN ({$branchIn})
");

if ($ares) {
  while ($a = $ares->fetch_assoc()) {
    $mName = trim((string)$a['month_applicable']);
    $code  = (string)$a['branch_code'];
    $tid   = (int)($a['water_type_id'] ?? 0);
    $cno   = (int)($a['connection_no'] ?? 1);

    if ($cno <= 0) $cno = 1;

    $key = $tid . '|' . $cno;
    $st  = strtolower(trim((string)($a['approval_status'] ?? '')));
    $amt = parseAmount($a['total_amount'] ?? '');

    $monthsWithRows[$mName] = true;
    $actualRows[$mName][$code][$key] = [
      'status' => $st,
      'amount' => $amt
    ];
  }
}

/* =========================================================
   4) Build chart data
   How "actual" is calculated:
   - For a branch/month to count, ALL required connections must be present
     and approved, with valid (>0) amounts.
   - If even one required connection is pending/missing/rejected, the branch
     doesn't contribute for that month.
========================================================= */
$labels       = [];
$budgetSeries = [];
$actualSeries = [];

foreach ($months as $mName) {

  // If the month has no rows at all, skip it (unless include_zero=1)
  if (!$includeZero && !isset($monthsWithRows[$mName])) {
    continue;
  }

  $actual_sum_completed = 0.0;

  foreach ($eligibleBranches as $code => $mdata) {
    $required = $mdata['required_keys'] ?? [];
    $reqCount = (int)($mdata['required_count'] ?? 0);
    if ($reqCount <= 0) continue;

    $approvedOk    = 0;
    $pendingCount  = 0;
    $missingCount  = 0;
    $sumApproved   = 0.0;

    foreach ($required as $reqKey) {
      $row = $actualRows[$mName][$code][$reqKey] ?? null;

      // No row saved for this required connection => not complete
      if (!$row) {
        $missingCount++;
        continue;
      }

      $st = $row['status'] ?? '';

      // Deleted/pending doesn't count as "done"
      if ($st === 'deleted') { $missingCount++; continue; }
      if ($st === 'pending') { $pendingCount++; continue; }

      if ($st === 'approved') {
        $am = $row['amount'];

        // Treat empty/zero amounts as incomplete as well
        if ($am !== null && $am > 0) {
          $approvedOk++;
          $sumApproved += $am;
        } else {
          $missingCount++;
        }
        continue;
      }

      // rejected/anything unexpected => not complete
      $missingCount++;
    }

    // Only count the branch if every required connection is properly approved
    $isFullyApproved = ($approvedOk === $reqCount && $pendingCount === 0 && $missingCount === 0);

    if ($isFullyApproved) {
      $actual_sum_completed += $sumApproved;
    }
  }

  $actual = (float)$actual_sum_completed;

  // Same behavior as the report: skip months with 0 actual (unless include_zero=1)
  if (!$includeZero && $actual <= 0) {
    continue;
  }

  $budgetVal = ($budgetMode === 'yearly') ? $yearly_budget : $monthly_budget;

  $labels[]       = $mName;
  $budgetSeries[] = round((float)$budgetVal, 2);
  $actualSeries[] = round((float)$actual, 2);
}

echo json_encode([
  'labels' => $labels,
  'budget' => $budgetSeries,
  'actual' => $actualSeries
]);
