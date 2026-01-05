<?php
// security-monthly-fetch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month   = $_POST['month']   ?? '';
$firm_id = isset($_POST['firm_id']) ? (int)$_POST['firm_id'] : 0;

if (!$month) {
    echo json_encode([
        'table'           => '<div class="alert alert-danger">Invalid month.</div>',
        'missing'         => [],
        'missing_by_firm' => [],
        'provisions'      => [],
        'pending'         => [],
        'pending_count'   => 0,
    ]);
    exit;
}

$month_esc = mysqli_real_escape_string($conn, $month);

$rows      = [];
$missing   = [];
$missing_by_firm = [];

// Pending tracking
$pending_branches = [];

// Overall totals
$tot_budget_shifts = 0;
$tot_actual_shifts = 0;
$tot_budget_amount = 0.0;
$tot_actual_amount = 0.0;
$tot_difference    = 0.0;

// Summary by category
$summary = [
    'branches' => [
        'label'          => 'Branches',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
    'head_office' => [
        'label'          => 'Head Office',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
    'yards' => [
        'label'          => 'Yards',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
    'police' => [
        'label'          => 'Police',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
    'pawning' => [
        'label'          => 'Additional Security',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
    'radio' => [
        'label'          => 'Radio Transmission',
        'budget_shifts'  => 0,
        'actual_shifts'  => 0,
        'budget_amount'  => 0.0,
        'actual_amount'  => 0.0,
        'difference'     => 0.0,
    ],
];

// Helper: category by branch code
function security_get_category_key($branch_code) {
    $num = (int)$branch_code;

    // ONLY 9531 is Head Office
    if ($num === 9531) {
        return 'head_office';
    }
    if ($num >= 2000 && $num <= 2013) {
        return 'yards';
    }
    if ($num === 2014) {
        return 'police';
    }
    if ($num === 2015) {
        return 'pawning';
    }
    if ($num === 2016) {
        return 'radio';
    }
    return 'branches'; // 9530 will now land here
}

// Helper: is 2000-branch (invoice-style)
function is_2000_branch($conn, $branch_code) {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $q = mysqli_query($conn, "
            SELECT branch_code 
            FROM tbl_admin_security_2000_branches
            WHERE active = 'yes'
        ");
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $cache[$r['branch_code']] = true;
            }
        }
    }
    return isset($cache[$branch_code]);
}

// Table header
$table_start = '<div class="table-responsive">
<table class="table table-bordered">
<thead>
<tr>
  <th>Branch Code</th>
  <th>Branch</th>
  <th>Security Firm</th>
  <th>Month</th>

  <th>Budgeted<br>Shifts</th>
  <th>Actual<br>Shifts</th>

  <th>Rate</th>

  <th>Budgeted<br>Amount</th>
  <th>Actual<br>Amount</th>

  <th>Difference</th>
</tr>
</thead>
<tbody>';


/* ==========================================================
   MODE A: VIEW ALL FIRMS  (firm_id <= 0)
   ========================================================== */
if ($firm_id <= 0) {

    // Pre-load all pending branches from original actual table
    $pending_branch_codes = [];
    $pend_q = mysqli_query($conn, "
        SELECT DISTINCT branch_code
        FROM tbl_admin_actual_security_firmwise
        WHERE month_applicable = '{$month_esc}'
          AND approval_status = 'pending'
    ");
    if ($pend_q) {
        while ($p = mysqli_fetch_assoc($pend_q)) {
            $pending_branch_codes[] = (string)$p['branch_code'];
        }
    }
    $pending_branch_codes = array_unique($pending_branch_codes);

    // Budget rows (all branches)
    $budget_query = mysqli_query($conn, "SELECT 
            b.branch_code,
            b.branch,
            b.no_of_shifts,
            b.rate,
            m.firm_id        AS mapped_firm_id,
            f.firm_name,
            f.id             AS firm_real_id
        FROM tbl_admin_budget_security b
        LEFT JOIN tbl_admin_branch_firm_map m 
               ON m.branch_code = b.branch_code
              AND m.active = 'yes'
        LEFT JOIN tbl_admin_security_firms f
               ON f.id = m.firm_id
        WHERE b.month_applicable = '{$month_esc}'
        ORDER BY 
            CAST(b.branch_code AS UNSIGNED),
            b.branch_code");

    while ($b = mysqli_fetch_assoc($budget_query)) {

        $branch_code     = $b['branch_code'];
        $branch          = $b['branch'];
        $rate            = (float)$b['rate'];
        $budget_shifts   = (int)$b['no_of_shifts'];
        $budget_amount   = $rate * $budget_shifts;
        $mapped_firm_id  = (int)($b['mapped_firm_id'] ?? 0);
        $firm_name       = $b['firm_name'] ?? '';
        $firm_real_id    = (int)($b['firm_real_id'] ?? 0);

        if (stripos($branch, 'Point Close') !== false) {
            continue;
        }

        $branch_int       = (int)$branch_code;
        $is2000           = is_2000_branch($conn, $branch_code);

        // ✅ Allocation codes (no rate display; compare by amount)
        $isAllocation     = in_array($branch_int, [2014, 2015, 2016], true);

        $skip_shift_count = $isAllocation || $is2000;


        $branch_code_esc = mysqli_real_escape_string($conn, $branch_code);
        $whereFirm       = $mapped_firm_id ? " AND a.firm_id = {$mapped_firm_id}" : "";

        $row_class      = '';
        $has_actual     = false;
        $actual_shifts  = 0;
        $actual_amount  = 0.0;
        $difference     = 0 - $budget_amount;
        $reason_text    = '';

        // ---------- NON-2000 BRANCHES: original table ----------
        if (!$is2000) {
            $actual_q = mysqli_query($conn, "
                SELECT 
                    a.actual_shifts, 
                    a.total_amount, 
                    a.provision,
                    r.reason
                FROM tbl_admin_actual_security_firmwise a
                LEFT JOIN tbl_admin_reason r ON a.reason_id = r.id
                WHERE a.branch_code = '{$branch_code_esc}'
                  AND a.month_applicable = '{$month_esc}'
                  AND a.approval_status = 'approved'
                  {$whereFirm}
                LIMIT 1
            ");

            if ($actual = mysqli_fetch_assoc($actual_q)) {
                $has_actual    = true;
                $actual_shifts = (int)$actual['actual_shifts'];
                $actual_amount = (float)$actual['total_amount'];
                $difference    = $actual_amount - $budget_amount;
                $provision     = $actual['provision'];
                $reason_text   = $actual['reason'] ?? '';

                if ($provision === 'yes') {
                    $row_class = 'table-warning';
                }
            }

        // ---------- 2000 BRANCHES: invoice table ----------
        } else {

            $inv_q = mysqli_query($conn, "
                SELECT
                    COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount ELSE 0 END), 0) AS total_approved,
                    SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_rows,
                    SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END)  AS pending_rows,
                    SUM(CASE WHEN approval_status = 'approved' AND provision = 'yes' THEN 1 ELSE 0 END) AS approved_provisions
                FROM tbl_admin_actual_security_2000_invoices
                WHERE branch_code = '{$branch_code_esc}'
                  AND month_applicable = '{$month_esc}'
            ");

            $inv            = mysqli_fetch_assoc($inv_q);
            $approved_rows  = (int)($inv['approved_rows'] ?? 0);
            $pending_rows   = (int)($inv['pending_rows'] ?? 0);
            $actual_amount  = (float)($inv['total_approved'] ?? 0.0);
            $has_actual     = $approved_rows > 0;
            $actual_shifts  = 0; // no shifts for invoice-based
            $difference     = $actual_amount - $budget_amount;

            if (($inv['approved_provisions'] ?? 0) > 0) {
                $row_class = 'table-warning';
            }

            // pending branch list based on invoices
            if ($pending_rows > 0) {
                $pending_label = "{$branch_code} - {$branch}" . ($firm_name ? " ({$firm_name})" : "");
                $pending_branches[$branch_code] = $pending_label;
            }

            // missing only if no approved and no pending
            if (!$has_actual && $pending_rows === 0) {
                $label = "{$branch_code} - {$branch}" . ($firm_name ? " ({$firm_name})" : "");
                $missing[] = $label;

                $fid = $firm_real_id ?: 0;
                if (!isset($missing_by_firm[$fid])) {
                    $missing_by_firm[$fid] = [
                        'firm_id'    => $fid,
                        'firm_name'  => $firm_name ?: 'Unassigned Firm',
                        'branches'   => []
                    ];
                }
                $missing_by_firm[$fid]['branches'][] = "{$branch_code} - {$branch}";
            }
        }

        // ---------- Totals ----------
        if (!$skip_shift_count) {
            $tot_budget_shifts += $budget_shifts;
            $tot_actual_shifts += $actual_shifts;
        }
        $tot_budget_amount += $budget_amount;
        $tot_actual_amount += $actual_amount;
        $tot_difference    += $difference;

        // Summary by category
        $cat_key = security_get_category_key($branch_code);
        if (isset($summary[$cat_key])) {
            if (!$skip_shift_count) {
                $summary[$cat_key]['budget_shifts'] += $budget_shifts;
                $summary[$cat_key]['actual_shifts'] += $actual_shifts;
            }
            $summary[$cat_key]['budget_amount'] += $budget_amount;
            $summary[$cat_key]['actual_amount'] += $actual_amount;
            $summary[$cat_key]['difference']    += $difference;
        }

        // Reason icon (only for non-2000, shifts > budget)
        $reason_icon = '';
        if (!$is2000 && $has_actual && $actual_shifts > $budget_shifts && !empty($reason_text)) {
            $reason_icon = " <button type='button' class='btn btn-sm btn-outline-info reason-view-btn ms-1' data-reason=\""
                . htmlspecialchars($reason_text, ENT_QUOTES)
                . "\"><i class='bi bi-info-circle'></i></button>";
        }

        // Actual amount display
        $actual_amount_display = number_format($actual_amount, 2);

        if (!$has_actual) {
            if ($is2000) {
                // For 2000 branches, we already handled pending/missing
                // Actual cell text for no approved:
                // - if pending (stored in $pending_branches), show Pending
                if (isset($pending_branches[$branch_code])) {
                    $actual_amount_display = "<span class='text-danger'>Pending record</span>";
                } else {
                    $actual_amount_display = "<span class='text-danger'>No record</span>";
                }
            } else {
                // Non-2000 use original pending_branch_codes
                $is_pending_any = in_array((string)$branch_code, $pending_branch_codes, true);
                if ($is_pending_any) {
                    $actual_amount_display = "<span class='text-danger'>Pending record</span>";
                } else {
                    $actual_amount_display = "<span class='text-danger'>No record</span>";
                }

                // Missing list for non-2000
                if (!$is_pending_any) {
                    $label = "{$branch_code} - {$branch}" . ($firm_name ? " ({$firm_name})" : "");
                    $missing[] = $label;

                    $fid = $firm_real_id ?: 0;
                    if (!isset($missing_by_firm[$fid])) {
                        $missing_by_firm[$fid] = [
                            'firm_id'    => $fid,
                            'firm_name'  => $firm_name ?: 'Unassigned Firm',
                            'branches'   => []
                        ];
                    }
                    $missing_by_firm[$fid]['branches'][] = "{$branch_code} - {$branch}";
                }
            }
        }

        // Row HTML
        // ✅ Over-budget highlighting
        $overBudget = false;

        // Allocation (2014/2015/2016): compare by AMOUNT
        if ($isAllocation) {
            if ($has_actual && $actual_amount > $budget_amount) {
                $overBudget = true;
            }
        } else {
            // Normal branches: compare by SHIFTS
            if (!$is2000 && $has_actual && $actual_shifts > $budget_shifts) {
                $overBudget = true;
            }
        }

        // table-danger = light red in Bootstrap
        if ($overBudget) {
            $row_class = 'table-danger';
        }

        $row = "<tr class='{$row_class}'>
        <td>{$branch_code}</td>
        <td>{$branch}</td>
        <td>" . htmlspecialchars($firm_name ?: '-', ENT_QUOTES) . "</td>
        <td>{$month}</td>
        <td>" . ($skip_shift_count ? '-' : (int)$budget_shifts) . "</td>
        <td>" . ($skip_shift_count ? '-' : (int)$actual_shifts) . "{$reason_icon}</td>
        <td>" . ($isAllocation ? '-' : number_format($rate, 2)) . "</td>
        <td>" . number_format($budget_amount, 2) . "</td>
        <td>{$actual_amount_display}</td>
        <td>" . number_format($difference, 2) . "</td>
        </tr>";

        $rows[] = $row;
    }

    // Provisions across all firms (both tables)
    $provision_rows = [];

    // From original table
    $prov_q1 = mysqli_query($conn, "
        SELECT 
            a.branch_code,
            a.branch,
            f.firm_name
        FROM tbl_admin_actual_security_firmwise a
        LEFT JOIN tbl_admin_branch_firm_map m 
               ON m.branch_code = a.branch_code
              AND m.active = 'yes'
        LEFT JOIN tbl_admin_security_firms f
               ON f.id = m.firm_id
        WHERE a.month_applicable = '{$month_esc}'
          AND a.provision = 'yes'
    ");
    if ($prov_q1) {
        while ($row = mysqli_fetch_assoc($prov_q1)) {
            $label = "{$row['branch_code']} - {$row['branch']}";
            if (!empty($row['firm_name'])) {
                $label .= " ({$row['firm_name']})";
            }
            $provision_rows[] = $label;
        }
    }

    // From 2000 invoice table
    $prov_q2 = mysqli_query($conn, "
        SELECT 
            i.branch_code,
            i.branch,
            f.firm_name
        FROM tbl_admin_actual_security_2000_invoices i
        LEFT JOIN tbl_admin_branch_firm_map m 
               ON m.branch_code = i.branch_code
              AND m.active = 'yes'
        LEFT JOIN tbl_admin_security_firms f
               ON f.id = m.firm_id
        WHERE i.month_applicable = '{$month_esc}'
          AND i.provision = 'yes'
    ");
    if ($prov_q2) {
        while ($row = mysqli_fetch_assoc($prov_q2)) {
            $label = "{$row['branch_code']} - {$row['branch']}";
            if (!empty($row['firm_name'])) {
                $label .= " ({$row['firm_name']})";
            }
            $provision_rows[] = $label;
        }
    }

/* ==========================================================
   MODE B: SINGLE FIRM (firm_id > 0)
   ========================================================== */
} else {

    // Pending for this firm from original table
    $pending_branch_codes = [];
    $pend_q = mysqli_query($conn, "
        SELECT DISTINCT branch_code
        FROM tbl_admin_actual_security_firmwise
        WHERE month_applicable = '{$month_esc}'
          AND approval_status = 'pending'
          AND firm_id = {$firm_id}
    ");
    if ($pend_q) {
        while ($p = mysqli_fetch_assoc($pend_q)) {
            $pending_branch_codes[] = (string)$p['branch_code'];
        }
    }
    $pending_branch_codes = array_unique($pending_branch_codes);

    // Budget rows only for branches mapped to this firm
    $budget_query = mysqli_query($conn, "SELECT 
            b.branch_code,
            b.branch,
            b.no_of_shifts,
            b.rate,
            m.firm_id   AS mapped_firm_id,
            f.firm_name,
            f.id        AS firm_real_id
        FROM tbl_admin_budget_security b
        INNER JOIN tbl_admin_branch_firm_map m 
                ON m.branch_code = b.branch_code
               AND m.active = 'yes'
               AND m.firm_id = {$firm_id}
        LEFT JOIN tbl_admin_security_firms f
               ON f.id = m.firm_id
        WHERE b.month_applicable = '{$month_esc}'
        ORDER BY 
            CAST(b.branch_code AS UNSIGNED),
            b.branch_code");

    while ($b = mysqli_fetch_assoc($budget_query)) {
        $branch_code     = $b['branch_code'];
        $branch          = $b['branch'];
        $rate            = (float)$b['rate'];
        $budget_shifts   = (int)$b['no_of_shifts'];
        $budget_amount   = $rate * $budget_shifts;
        $mapped_firm_id  = (int)($b['mapped_firm_id'] ?? 0);
        $firm_name       = $b['firm_name'] ?? '';
        $firm_real_id    = (int)($b['firm_real_id'] ?? 0);

        if (stripos($branch, 'Point Close') !== false) {
            continue;
        }

        $branch_int       = (int)$branch_code;
        $is2000           = is_2000_branch($conn, $branch_code);
        $skip_shift_count = in_array($branch_int, [2014, 2015, 2016], true) || $is2000;

        $branch_code_esc = mysqli_real_escape_string($conn, $branch_code);
        $whereFirm       = $mapped_firm_id ? " AND a.firm_id = {$mapped_firm_id}" : "";

        $row_class      = '';
        $has_actual     = false;
        $actual_shifts  = 0;
        $actual_amount  = 0.0;
        $difference     = 0 - $budget_amount;
        $reason_text    = '';

        // Non-2000 branches – original table
        if (!$is2000) {

            $actual_q = mysqli_query($conn, "
                SELECT 
                    a.actual_shifts, 
                    a.total_amount, 
                    a.provision,
                    r.reason
                FROM tbl_admin_actual_security_firmwise a
                LEFT JOIN tbl_admin_reason r ON a.reason_id = r.id
                WHERE a.branch_code = '{$branch_code_esc}'
                  AND a.month_applicable = '{$month_esc}'
                  AND a.approval_status = 'approved'
                  {$whereFirm}
                LIMIT 1
            ");

            if ($actual = mysqli_fetch_assoc($actual_q)) {
                $has_actual    = true;
                $actual_shifts = (int)$actual['actual_shifts'];
                $actual_amount = (float)$actual['total_amount'];
                $difference    = $actual_amount - $budget_amount;
                $provision     = $actual['provision'];
                $reason_text   = $actual['reason'] ?? '';

                if ($provision === 'yes') {
                    $row_class = 'table-warning';
                }
            }

        } else {
            // 2000 branches – invoice table
            $inv_q = mysqli_query($conn, "
                SELECT
                    COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount ELSE 0 END), 0) AS total_approved,
                    SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_rows,
                    SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END)  AS pending_rows,
                    SUM(CASE WHEN approval_status = 'approved' AND provision = 'yes' THEN 1 ELSE 0 END) AS approved_provisions
                FROM tbl_admin_actual_security_2000_invoices
                WHERE branch_code = '{$branch_code_esc}'
                  AND month_applicable = '{$month_esc}'
                  AND firm_id = {$firm_id}
            ");

            $inv            = mysqli_fetch_assoc($inv_q);
            $approved_rows  = (int)($inv['approved_rows'] ?? 0);
            $pending_rows   = (int)($inv['pending_rows'] ?? 0);
            $actual_amount  = (float)($inv['total_approved'] ?? 0.0);
            $has_actual     = $approved_rows > 0;
            $actual_shifts  = 0;
            $difference     = $actual_amount - $budget_amount;

            if (($inv['approved_provisions'] ?? 0) > 0) {
                $row_class = 'table-warning';
            }

            if ($pending_rows > 0) {
                $pending_label = "{$branch_code} - {$branch}";
                $pending_branches[$branch_code] = $pending_label;
            }

            if (!$has_actual && $pending_rows === 0) {
                $missing[] = "{$branch_code} - {$branch}";
            }
        }

        // Totals
        if (!$skip_shift_count) {
            $tot_budget_shifts += $budget_shifts;
            $tot_actual_shifts += $actual_shifts;
        }
        $tot_budget_amount += $budget_amount;
        $tot_actual_amount += $actual_amount;
        $tot_difference    += $difference;

        // Summary by category
        $cat_key = security_get_category_key($branch_code);
        if (isset($summary[$cat_key])) {
            if (!$skip_shift_count) {
                $summary[$cat_key]['budget_shifts'] += $budget_shifts;
                $summary[$cat_key]['actual_shifts'] += $actual_shifts;
            }
            $summary[$cat_key]['budget_amount'] += $budget_amount;
            $summary[$cat_key]['actual_amount'] += $actual_amount;
            $summary[$cat_key]['difference']    += $difference;
        }

        // Reason icon for non-2000
        $reason_icon = '';
        if (!$is2000 && $has_actual && $actual_shifts > $budget_shifts && !empty($reason_text)) {
            $reason_icon = " <button type='button' class='btn btn-sm btn-outline-info reason-view-btn ms-1' data-reason=\""
                . htmlspecialchars($reason_text, ENT_QUOTES)
                . "\"><i class='bi bi-info-circle'></i></button>";
        }

        // Actual amount display
        $actual_amount_display = number_format($actual_amount, 2);

        if (!$has_actual) {
            if ($is2000) {
                if (isset($pending_branches[$branch_code])) {
                    $actual_amount_display = "<span class='text-danger'>Pending record</span>";
                } else {
                    $actual_amount_display = "<span class='text-danger'>No record</span>";
                }
            } else {
                $is_pending_any = in_array((string)$branch_code, $pending_branch_codes, true);
                if ($is_pending_any) {
                    $actual_amount_display = "<span class='text-danger'>Pending record</span>";
                } else {
                    $actual_amount_display = "<span class='text-danger'>No record</span>";
                    $missing[] = "{$branch_code} - {$branch}";
                }
            }
        }

        $row = "<tr class='{$row_class}'>
        <td>{$branch_code}</td>
        <td>{$branch}</td>
        <td>" . htmlspecialchars($firm_name ?: '-', ENT_QUOTES) . "</td>
        <td>{$month}</td>
        <td>" . ($skip_shift_count ? '-' : (int)$budget_shifts) . "</td>
        <td>" . ($skip_shift_count ? '-' : (int)$actual_shifts) . "{$reason_icon}</td>
        <td>" . number_format($rate, 2) . "</td>
        <td>" . number_format($budget_amount, 2) . "</td>
        <td>{$actual_amount_display}</td>
        <td>" . number_format($difference, 2) . "</td>
        </tr>";

        $rows[] = $row;
    }

    // Provisions for this firm only
    $provision_rows = [];

    $prov_q1 = mysqli_query($conn, "
        SELECT branch_code, branch 
        FROM tbl_admin_actual_security_firmwise 
        WHERE month_applicable = '{$month_esc}'
          AND firm_id = {$firm_id}
          AND provision = 'yes'
    ");
    if ($prov_q1) {
        while ($row = mysqli_fetch_assoc($prov_q1)) {
            $provision_rows[] = "{$row['branch_code']} - {$row['branch']}";
        }
    }

    $prov_q2 = mysqli_query($conn, "
        SELECT branch_code, branch 
        FROM tbl_admin_actual_security_2000_invoices 
        WHERE month_applicable = '{$month_esc}'
          AND firm_id = {$firm_id}
          AND provision = 'yes'
    ");
    if ($prov_q2) {
        while ($row = mysqli_fetch_assoc($prov_q2)) {
            $provision_rows[] = "{$row['branch_code']} - {$row['branch']}";
        }
    }
}

/* ==========================================================
   TOTAL ROW
   ========================================================== */
$totals_row = "<tr class='table-secondary fw-bold'>
<td colspan='4' class='text-end'>Total</td>
<td>".number_format($tot_budget_shifts,0)."</td>
<td>".number_format($tot_actual_shifts,0)."</td>
<td>-</td>
<td>".number_format($tot_budget_amount,2)."</td>
<td>".number_format($tot_actual_amount,2)."</td>
<td>".number_format($tot_difference,2)."</td>
</tr>";

/* ==========================================================
   SUMMARY BY CATEGORY
   ========================================================== */
$summary_html  = '<div class="mt-3">';
$summary_html .= '<h6>Summary by Category</h6>';
$summary_html .= '<div class="table-responsive"><table class="table table-bordered table-sm summary-compact">';
$summary_html .= '<thead><tr>
<th class="sum-cat">Category</th>
<th class="sum-num">Budgeted Shifts</th>
<th class="sum-num">Actual Shifts</th>
<th class="sum-num">Budgeted Amt</th>
<th class="sum-num">Actual Amt</th>
<th class="sum-num">Variance</th>
</tr></thead><tbody>';


$order = ['branches','head_office','yards','police','pawning','radio'];

$sum_cat_budget_shifts = 0;
$sum_cat_actual_shifts = 0;
$sum_cat_budget_amount = 0.0;
$sum_cat_actual_amount = 0.0;
$sum_cat_difference    = 0.0;

foreach ($order as $key) {
    if (!isset($summary[$key])) continue;
    $s = $summary[$key];

    $sum_cat_budget_shifts += $s['budget_shifts'];
    $sum_cat_actual_shifts += $s['actual_shifts'];
    $sum_cat_budget_amount += $s['budget_amount'];
    $sum_cat_actual_amount += $s['actual_amount'];
    $sum_cat_difference    += $s['difference'];

    $summary_html .= '<tr>';
    $summary_html .= '<td class="sum-cat">'.htmlspecialchars($s['label']).'</td>';
    $summary_html .= '<td>'.number_format($s['budget_shifts'],0).'</td>';
    $summary_html .= '<td>'.number_format($s['actual_shifts'],0).'</td>';
    $summary_html .= '<td>'.number_format($s['budget_amount'],2).'</td>';
    $summary_html .= '<td>'.number_format($s['actual_amount'],2).'</td>';
    $summary_html .= '<td>'.number_format($s['difference'],2).'</td>';
    $summary_html .= '</tr>';
}

// Summary total row
$summary_html .= "<tr class='table-secondary fw-bold'>";
$summary_html .= "<td>Total</td>";
$summary_html .= "<td>".number_format($sum_cat_budget_shifts,0)."</td>";
$summary_html .= "<td>".number_format($sum_cat_actual_shifts,0)."</td>";
$summary_html .= "<td>".number_format($sum_cat_budget_amount,2)."</td>";
$summary_html .= "<td>".number_format($sum_cat_actual_amount,2)."</td>";
$summary_html .= "<td>".number_format($sum_cat_difference,2)."</td>";
$summary_html .= "</tr>";

$summary_html .= '</tbody></table></div></div>';

/* ==========================================================
   FINAL TABLE HTML
   ========================================================== */
$table = $table_start
       . implode('', $rows)
       . $totals_row
       . "</tbody></table></div>"
       . $summary_html;

/* ==========================================================
   PENDING INFO
   ========================================================== */
$pending_list  = array_values($pending_branches);
$pending_count = count($pending_branches);

/* ==========================================================
   JSON OUTPUT
   ========================================================== */
echo json_encode([
    'table'           => $table,
    'missing'         => $missing,
    'missing_by_firm' => $missing_by_firm,
    'provisions'      => $provision_rows,
    'pending'         => $pending_list,
    'pending_count'   => $pending_count
]);
