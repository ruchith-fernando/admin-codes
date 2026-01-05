<?php
// ajax-get-water-branch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($branch_code === '') { echo json_encode(['success' => false, 'message' => 'No branch code provided.']); exit; }
if ($month === '') { echo json_encode(['success' => false, 'message' => 'No month provided.']); exit; }

/* =========================================================
   ✅ Block branches with NO budget or budget = 0
========================================================= */
// ✅ Financial Year budget_year = FY start year (Apr→Mar)
$ts = strtotime("1 " . $month);
$y  = (int)date("Y", $ts);
$mn = (int)date("n", $ts);
$budget_year = ($mn < 4) ? ($y - 1) : $y;

$bcEsc = mysqli_real_escape_string($conn, $branch_code);
$byEsc = mysqli_real_escape_string($conn, (string)$budget_year);

$budRes = mysqli_query($conn, "
    SELECT COALESCE(SUM(amount),0) AS bud
    FROM tbl_admin_budget_water
    WHERE branch_code = '{$bcEsc}'
      AND budget_year = '{$byEsc}'
");
$budRow = $budRes ? mysqli_fetch_assoc($budRes) : null;
$budAmt = (float)($budRow['bud'] ?? 0);

if ($budAmt <= 0) {
    userlog("⛔ Water Branch Lookup BLOCKED (No budget/0 budget) | Branch: {$branch_code} | Month: {$month} | BudgetYear: {$budget_year}");
    echo json_encode([
        'success' => false,
        'message' => "This branch has no budget / budget is 0 for {$budget_year}. You cannot enter water data for this branch."
    ]);
    exit;
}

/* ---------------------------------------------------------
   1) Load mapping lines (branch + type + connection_no)
--------------------------------------------------------- */
$branch_sql = "
    SELECT 
        bw.branch_code,
        bw.branch_name,
        bw.water_type_id,
        bw.connection_no,
        wt.water_type_name,
        wt.water_type_code,

        bw.account_number,
        bw.no_of_machines,
        bw.monthly_charge         AS bw_monthly_charge,
        bw.bottle_rate            AS bw_bottle_rate,
        bw.cooler_rental_rate     AS bw_cooler_rental,
        bw.sscl_percentage        AS bw_sscl,
        bw.vat_percentage         AS bw_vat,
        bw.vendor_id,

        wv.vendor_name,

        rp.unit_desc,
        rp.base_rate,
        rp.bottle_rate            AS rp_bottle_rate,
        rp.monthly_charge         AS rp_monthly_charge,
        rp.cooler_rental_rate     AS rp_cooler_rental,
        rp.sscl_percentage        AS rp_sscl,
        rp.vat_percentage         AS rp_vat
    FROM tbl_admin_branch_water bw
    INNER JOIN tbl_admin_water_types wt 
        ON wt.water_type_id = bw.water_type_id
    LEFT JOIN tbl_admin_water_vendors wv
        ON wv.vendor_id = bw.vendor_id
    LEFT JOIN tbl_admin_water_rate_profiles rp
        ON rp.water_type_id = bw.water_type_id
       AND rp.vendor_id     = bw.vendor_id
       AND rp.is_active     = 1
       AND (rp.effective_from IS NULL OR rp.effective_from <= CURDATE())
       AND (rp.effective_to   IS NULL OR rp.effective_to   >= CURDATE())
    WHERE bw.branch_code = '{$bcEsc}'
    ORDER BY wt.water_type_name, bw.connection_no
";

$branch_res = mysqli_query($conn, $branch_sql);

if (!$branch_res || mysqli_num_rows($branch_res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Branch not found or no water types linked.']);
    exit;
}

$types = [];
$branch_name = null;

while ($r = mysqli_fetch_assoc($branch_res)) {
    $branch_name = $r['branch_name'];

    $mode = strtoupper($r['water_type_code'] ?: $r['water_type_name']);

    // Prefer rate profile values when set; fallback to branch-level
    $monthly_charge = ($r['rp_monthly_charge'] !== null && $r['rp_monthly_charge'] != 0)
        ? (float)$r['rp_monthly_charge']
        : (float)($r['bw_monthly_charge'] ?? 0);

    $bottle_rate = ($r['rp_bottle_rate'] !== null && $r['rp_bottle_rate'] != 0)
        ? (float)$r['rp_bottle_rate']
        : (float)($r['bw_bottle_rate'] ?? 0);

    $cooler_rental = ($r['rp_cooler_rental'] !== null && $r['rp_cooler_rental'] != 0)
        ? (float)$r['rp_cooler_rental']
        : (float)($r['bw_cooler_rental'] ?? 0);

    $sscl = ($r['rp_sscl'] !== null && $r['rp_sscl'] != 0)
        ? (float)$r['rp_sscl']
        : (float)($r['bw_sscl'] ?? 0);

    $vat = ($r['rp_vat'] !== null && $r['rp_vat'] != 0)
        ? (float)$r['rp_vat']
        : (float)($r['bw_vat'] ?? 0);

    $types[] = [
        'water_type_id'   => (int)$r['water_type_id'],
        'connection_no'   => (int)($r['connection_no'] ?? 1),
        'water_type_name' => $r['water_type_name'],
        'water_type_code' => $r['water_type_code'],
        'mode'            => $mode,

        'account_number'  => $r['account_number'] ?? '',
        'vendor_name'     => $r['vendor_name'] ?? '',
        'unit_desc'       => $r['unit_desc'] ?? '',

        'no_of_machines'  => (int)($r['no_of_machines'] ?? 1),
        'monthly_charge'  => $monthly_charge,

        'bottle_rate'     => $bottle_rate,
        'cooler_rental'   => $cooler_rental,

        'sscl'            => $sscl,
        'vat'             => $vat,
    ];
}

/* ---------------------------------------------------------
   2) Existing entries for this branch+month
   ✅ If existing is_provision=yes => keep it AVAILABLE (edit/convert)
--------------------------------------------------------- */
$monthEsc = mysqli_real_escape_string($conn, $month);

$actual_sql = "
    SELECT water_type_id, connection_no, approval_status, is_provision,
           from_date, to_date, usage_qty, total_amount
    FROM tbl_admin_actual_water
    WHERE branch_code = '{$bcEsc}'
      AND month_applicable = '{$monthEsc}'
";
$actual_res = mysqli_query($conn, $actual_sql);

$existing = []; // key type|conn => info
if ($actual_res) {
    while ($a = mysqli_fetch_assoc($actual_res)) {
        $k = (int)$a['water_type_id'] . '|' . (int)($a['connection_no'] ?? 1);
        $existing[$k] = [
            'status'       => strtolower(trim($a['approval_status'] ?? '')),
            'is_provision' => strtolower(trim($a['is_provision'] ?? 'no')),
            'from_date'    => $a['from_date'] ?? '',
            'to_date'      => $a['to_date'] ?? '',
            'qty'          => $a['usage_qty'],
            'amount'       => $a['total_amount'],
        ];
    }
}

/* ---------------------------------------------------------
   3) Keep only NOT-YET-USED lines,
      BUT keep provision lines (for edit/convert)
--------------------------------------------------------- */
$available = [];
foreach ($types as $t) {
    $k = (int)$t['water_type_id'] . '|' . (int)($t['connection_no'] ?? 1);

    $ex = $existing[$k] ?? null;
    if ($ex) {
        $status = $ex['status'];
        $isProv = $ex['is_provision'];

        // rejected/deleted -> treat as not entered
        if (in_array($status, ['rejected', 'deleted'], true)) {
            $available[] = $t;
            continue;
        }

        // ✅ provision -> keep available + send prefill
        if ($isProv === 'yes') {
            $t['existing_provision'] = 'yes';
            $t['prov_from_date'] = $ex['from_date'];
            $t['prov_to_date']   = $ex['to_date'];
            $t['prov_qty']       = $ex['qty'];
            $t['prov_amount']    = $ex['amount'];
            $available[] = $t;
            continue;
        }

        // actual already entered -> hide
        continue;
    }

    // no existing -> available
    $available[] = $t;
}

userlog("🔍 Water Branch Lookup | Branch: {$branch_code} | Month: {$month} | BudgetYear: {$budget_year} | Available lines: " . count($available));

echo json_encode([
    'success'           => true,
    'branch_code'       => $branch_code,
    'branch_name'       => $branch_name,
    'types'             => $available,
    'all_types_entered' => empty($available),
]);
