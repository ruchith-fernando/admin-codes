<?php
// ajax-water-rate-list.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$sql = "
    SELECT
        rp.rate_profile_id,
        wt.water_type_name,
        wt.water_type_code,
        v.vendor_name,
        rp.bottle_rate,
        rp.cooler_rental_rate,
        rp.sscl_percentage,
        rp.vat_percentage,
        rp.effective_from,
        rp.is_active
    FROM tbl_admin_water_rate_profiles rp
    JOIN tbl_admin_water_types wt  ON rp.water_type_id = wt.water_type_id
    JOIN tbl_admin_water_vendors v ON rp.vendor_id     = v.vendor_id
    ORDER BY wt.water_type_name, v.vendor_name
";

$res = $conn->query($sql);

$html = "
<table class='table table-bordered table-striped table-sm'>
  <thead class='table-light'>
    <tr>
      <th style='width:40px;'>#</th>
      <th>Water Type</th>
      <th>Vendor</th>
      <th style='width:100px;'>Bottle Rate</th>
      <th style='width:120px;'>Cooler Rate</th>
      <th style='width:80px;'>SSCL %</th>
      <th style='width:80px;'>VAT %</th>
      <th style='width:120px;'>Effective From</th>
      <th style='width:70px;'>Active</th>
      <th style='width:70px;'>Action</th>
    </tr>
  </thead>
  <tbody>
";

$idx = 0;
while ($row = $res->fetch_assoc()) {
    $idx++;
    $html .= "
      <tr>
        <td>{$idx}</td>
        <td>".htmlspecialchars($row['water_type_name'])."</td>
        <td>".htmlspecialchars($row['vendor_name'])."</td>
        <td>".number_format((float)$row['bottle_rate'], 2)."</td>
        <td>".number_format((float)$row['cooler_rental_rate'], 2)."</td>
        <td>".number_format((float)$row['sscl_percentage'], 2)."</td>
        <td>".number_format((float)$row['vat_percentage'], 2)."</td>
        <td>".htmlspecialchars($row['effective_from'])."</td>
        <td>".($row['is_active'] ? 'Yes' : 'No')."</td>
        <td>
          <button class='btn btn-sm btn-outline-primary wrp-btn-edit'
                  data-id='{$row['rate_profile_id']}'>
            Edit
          </button>
        </td>
      </tr>
    ";
}

if ($idx === 0) {
    $html .= "<tr><td colspan='10' class='text-center'>No rate profiles defined.</td></tr>";
}

$html .= "</tbody></table>";

echo json_encode(['success' => true, 'html' => $html]);
