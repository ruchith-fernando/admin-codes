<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$q = mysqli_query($conn, "
    SELECT *
    FROM tbl_admin_branch_water
    ORDER BY branch_code ASC
");

userlog("📄 Branch Master Loaded by ".$_SESSION['name']);

if(mysqli_num_rows($q) == 0){
    echo "<div class='alert alert-info'>No branches found.</div>";
    exit;
}

echo "
<div class='table-responsive'>
<table class='table table-bordered table-hover align-middle'>
<thead class='table-light'>
<tr>
    <th>Branch Code</th>
    <th>Branch Name</th>
    <th>Vendor</th>
    <th>Water Type</th>
    <th>Bottle / Machine / NWSDB Details</th>
    <th>Updated At</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
";

while($r = mysqli_fetch_assoc($q)){

    // Fallback vendor name
    $vendorName = $r['vendor_name'] ?? '';
    $vendor = trim((string)$vendorName) !== ''
        ? esc($vendorName)
        : "<span class='text-muted small'>N/A</span>";


    // Build details column
    $details = "";

    if($r['water_type'] === "BOTTLE"){
        $details = "
        <div><b>Bottle Rate:</b> Rs. ".number_format((float)$r['bottle_rate'],2)."</div>
        <div><b>Cooler:</b> Rs. ".number_format((float)$r['cooler_rental_rate'],2)."</div>
        <div><b>SSCL:</b> ".$r['sscl_percentage']."%</div>
        <div><b>VAT:</b> ".$r['vat_percentage']."%</div>
        ";
    }

    else if($r['water_type'] === "MACHINE"){
        $details = "
        <div><b>No. of Machines:</b> ".esc($r['no_of_machines'])."</div>
        <div><b>Monthly Charge:</b> Rs. ".number_format((float)$r['monthly_charge'],2)."</div>
        <div><b>SSCL:</b> ".$r['sscl_percentage']."%</div>
        <div><b>VAT:</b> ".$r['vat_percentage']."%</div>
        ";
    }

    else if($r['water_type'] === "NWSDB"){
        $details = "<div class='text-muted small'>Normal Water Billing</div>";
    }

    echo "
    <tr>
        <td>".esc($r['branch_code'])."</td>
        <td>".esc($r['branch_name'])."</td>
        <td style='max-width:150px; white-space:normal; word-break:break-word;'>$vendor</td>
        <td>".esc($r['water_type'])."</td>
        <td style='font-size: 13px;'>$details</td>
        <td>".esc($r['updated_at'])."</td>
        <td>
            <button 
                class='btn btn-warning btn-sm edit-branch-btn'
                data-id='".esc($r['id'])."'>
                Edit
            </button>
        </td>
    </tr>";
}

echo "</tbody></table></div>";
?>
