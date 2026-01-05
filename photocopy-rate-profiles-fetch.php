<?php
// photocopy-rate-profiles-fetch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;

$where = "";
if ($vendor_id > 0) {
    $where = "WHERE rp.vendor_id = " . (int)$vendor_id;
}

$sql = "
SELECT
  rp.rate_profile_id,
  rp.vendor_id,
  v.vendor_name,
  rp.model_match,
  rp.copy_rate,
  rp.sscl_percentage,
  rp.vat_percentage,
  rp.effective_from,
  rp.effective_to,
  rp.is_active
FROM tbl_admin_photocopy_rate_profiles rp
INNER JOIN tbl_admin_vendors v ON v.vendor_id = rp.vendor_id
{$where}
ORDER BY v.vendor_name, (rp.model_match IS NULL OR rp.model_match='') DESC, rp.model_match, rp.effective_from DESC, rp.rate_profile_id DESC
";

$res = mysqli_query($conn, $sql);

$rows = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function money($n){ return number_format((float)$n, 2); }

$table = "
<div class='table-responsive'>
<table class='table table-bordered pc-table'>
  <thead class='table-light'>
    <tr>
      <th>ID</th>
      <th>Vendor</th>
      <th>Model Match</th>
      <th class='text-end'>Copy Rate</th>
      <th class='text-end'>SSCL %</th>
      <th class='text-end'>VAT %</th>
      <th>Effective From</th>
      <th>Effective To</th>
      <th>Active</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
";

if (empty($rows)) {
    $table .= "<tr><td colspan='10' class='text-center text-muted'>No rate profiles found.</td></tr>";
} else {
    foreach ($rows as $r) {
        $model = trim((string)$r['model_match']);
        $modelShow = ($model === '') ? "<span class='badge bg-secondary'>DEFAULT</span>" : h($model);

        $active = ((int)$r['is_active'] === 1) ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-secondary'>No</span>";

        $btnEdit = "<button type='button' class='btn btn-sm btn-outline-primary pc_rate_edit'
          data-rate_profile_id='".(int)$r['rate_profile_id']."'
          data-vendor_id='".(int)$r['vendor_id']."'
          data-model_match='".h($model)."'
          data-copy_rate='".h($r['copy_rate'])."'
          data-sscl_percentage='".h($r['sscl_percentage'])."'
          data-vat_percentage='".h($r['vat_percentage'])."'
          data-effective_from='".h($r['effective_from'])."'
          data-effective_to='".h($r['effective_to'])."'
          data-is_active='".(int)$r['is_active']."'
        >Edit</button>";

        $btnDeact = "<button type='button' class='btn btn-sm btn-outline-danger pc_rate_deactivate ms-2'
          data-rate_profile_id='".(int)$r['rate_profile_id']."'
        >Deactivate</button>";

        $table .= "
        <tr>
          <td>".(int)$r['rate_profile_id']."</td>
          <td class='wrap'>".h($r['vendor_name'])."</td>
          <td class='wrap'>{$modelShow}</td>
          <td class='text-end'>".money($r['copy_rate'])."</td>
          <td class='text-end'>".money($r['sscl_percentage'])."</td>
          <td class='text-end'>".money($r['vat_percentage'])."</td>
          <td>".h($r['effective_from'])."</td>
          <td>".h($r['effective_to'])."</td>
          <td>{$active}</td>
          <td>{$btnEdit}{$btnDeact}</td>
        </tr>
        ";
    }
}

$table .= "</tbody></table></div>";

userlog("📌 Photocopy Rate Profiles View | VendorFilter: " . ($vendor_id ?: "ALL"));

echo json_encode([
    "table" => $table
]);
