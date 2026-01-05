<?php
// water-pending-load.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$month = $_POST['month'] ?? '';
if(!$month){
    exit("<div class='alert alert-warning'>No month selected.</div>");
}

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/**
 * Normalise mode from water_type_code / water_type_name
 * Returns: BOTTLE | MACHINE | NWSDB | OTHER
 */
function get_mode($row){
    $code = strtoupper(trim((string)($row['water_type_code'] ?? '')));
    $name = strtoupper(trim((string)($row['water_type_name'] ?? '')));

    $raw = $code . ' ' . $name;

    if (strpos($raw, 'BOTTLE') !== false) return 'BOTTLE';
    if (strpos($raw, 'MACH') !== false || strpos($raw, 'COOLER') !== false) return 'MACHINE';
    if (strpos($raw, 'TAP') !== false || strpos($raw, 'NWSDB') !== false || strpos($raw, 'LINE') !== false) return 'NWSDB';

    return 'OTHER';
}

/**
 * Display Units / Bottles / Amount column
 */
function fmt_display($row) {

    $mode = get_mode($row);
    $qty  = $row['usage_qty'];
    $amt  = preg_replace('/[^0-9.\-]/', '', (string)$row['total_amount']);

    if ($mode === 'BOTTLE') {
        return esc($qty) . " Bottles";
    }
    if ($mode === 'NWSDB') {
        return esc($qty) . " Units";
    }
    if ($mode === 'MACHINE') {
        return "Rs. " . number_format((float)$amt, 2);
    }

    return "-";
}

/*
    Join:
      - tbl_admin_actual_water (t1)
      - tbl_admin_branch_water (bw) for vendor_id
      - tbl_admin_water_vendors (wv) for vendor_name
      - tbl_admin_water_types (wt) for water_type_name / code
*/
$q = mysqli_query($conn, "
    SELECT 
        t1.*,
        bw.vendor_id,
        wv.vendor_name,
        wt.water_type_name,
        wt.water_type_code
    FROM tbl_admin_actual_water AS t1
    LEFT JOIN tbl_admin_branch_water AS bw
        ON bw.branch_code   = t1.branch_code
       AND bw.water_type_id = t1.water_type_id
    LEFT JOIN tbl_admin_water_vendors AS wv
        ON wv.vendor_id = bw.vendor_id
    LEFT JOIN tbl_admin_water_types AS wt
        ON wt.water_type_id = t1.water_type_id
    WHERE t1.month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
      AND (t1.approval_status = 'pending' OR t1.approval_status IS NULL)
    ORDER BY t1.entered_at DESC
");

if(!$q || mysqli_num_rows($q) == 0){
    echo "<div class='alert alert-info'>No pending approvals for <b>".esc($month)."</b>.</div>";
    exit;
}

echo "<div class='table-responsive'>
<table class='table table-bordered table-hover align-middle'>
<thead class='table-light'>
<tr>
<th>Branch Code</th>
<th>Branch</th>
<th>Vendor Name</th>
<th>From Date</th>
<th>To Date</th>
<th>Days</th>
<th class='text-end'>Units / Bottles / Amount</th>
<th>Total Amount</th>
<th>Provision</th>
<th>Entered By</th>
<th>Entered HRIS</th>
<th>Entered At</th>
<th>Actions</th>
</tr>
</thead><tbody>";

while($r = mysqli_fetch_assoc($q)){

    // SAFE trim() comparisons
    $entered_hris         = trim((string)($r['entered_hris'] ?? ''));
    $current_hris_clean   = trim((string)($current_hris ?? ''));
    $is_own               = ($entered_hris === $current_hris_clean);

    // SAFE vendor name
    $vendorName = trim((string)($r['vendor_name'] ?? ''));

    echo "<tr>
        <td>".esc($r['branch_code'])."</td>
        <td>".esc($r['branch'])."</td>
        <td>". ($vendorName !== '' ? esc($vendorName) : "National Water Supply and Drainage Board") ."</td>
        <td>".esc($r['from_date'])."</td>
        <td>".esc($r['to_date'])."</td>
        <td>".esc($r['number_of_days'])."</td>
        <td>".fmt_display($r)."</td>
        <td>Rs. ".number_format((float)str_replace(',', '', $r['total_amount']), 2)."</td>
        <td>".($r['is_provision']==='yes'?'Yes':'No')."</td>
        <td>".esc($r['entered_name'])."</td>
        <td>".esc($r['entered_hris'])."</td>
        <td>".esc($r['entered_at'])."</td>
        <td>";

    if($is_own){
        echo "<span class='text-muted small fst-italic'>Own entry</span>";
    } else {
        echo "
        <button class='btn btn-success btn-sm approve-btn'
            data-id='".esc($r['id'])."'
            data-branch='".esc($r['branch'])."'
            data-month='".esc($r['month_applicable'])."'>Approve</button>";
    }
    echo "</td></tr>";
}

echo "</tbody></table></div>";
?>
