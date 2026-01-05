<?php
require_once 'connections/connection.php';

$q = mysqli_query($conn, "
  SELECT
    a.assign_id,
    a.installed_at,
    a.branch_code,
    b.branch_name,
    a.vendor_id AS assign_vendor_id,
    v1.vendor_name AS assign_vendor_name,

    m.machine_id,
    m.serial_no,
    m.model_name,
    m.vendor_id AS machine_vendor_id,
    v2.vendor_name AS machine_vendor_name,

    a.remarks
  FROM tbl_admin_photocopy_machine_assignments a
  INNER JOIN tbl_admin_photocopy_machines m ON m.machine_id = a.machine_id
  LEFT JOIN tbl_admin_branches b ON b.branch_code = a.branch_code
  LEFT JOIN tbl_admin_vendors v1 ON v1.vendor_id = a.vendor_id
  LEFT JOIN tbl_admin_vendors v2 ON v2.vendor_id = m.vendor_id
  WHERE a.removed_at IS NULL
  ORDER BY a.installed_at DESC, a.assign_id DESC
");

if (!$q) {
  echo "<div class='alert alert-danger'>Query failed.</div>";
  exit;
}

echo "<table class='table'>
<thead>
<tr>
  <th>Serial</th>
  <th>Model</th>
  <th>Branch</th>
  <th>Vendor</th>
  <th>Installed</th>
  <th>Remarks</th>
  <th>Action</th>
</tr>
</thead><tbody>";

while ($r = mysqli_fetch_assoc($q)) {
  $vendor = $r['assign_vendor_name'] ?: $r['machine_vendor_name'] ?: '-';
  $branch = ($r['branch_name'] ?: '') . " (" . htmlspecialchars($r['branch_code']) . ")";
  $serial = htmlspecialchars($r['serial_no']);
  $model  = htmlspecialchars($r['model_name'] ?? '');
  $inst   = htmlspecialchars($r['installed_at'] ?? '');
  $rem    = htmlspecialchars($r['remarks'] ?? '');

  echo "<tr>
    <td>{$serial}</td>
    <td>{$model}</td>
    <td>".htmlspecialchars($branch)."</td>
    <td>".htmlspecialchars($vendor)."</td>
    <td>{$inst}</td>
    <td>{$rem}</td>
    <td class='actions'>
      <button type='button' class='btn btn-danger pc_remove_btn' data-id='".(int)$r['assign_id']."'>Remove</button>
    </td>
  </tr>";
}

echo "</tbody></table>";
